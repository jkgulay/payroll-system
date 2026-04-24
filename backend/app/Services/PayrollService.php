<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveOut;
use App\Models\Holiday;
use App\Models\GovernmentRate;
use App\Models\PayrollItemLeaveOut;
use App\Models\SalaryAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\CompanySettingService;
use Illuminate\Support\Facades\Cache;

class PayrollService
{
    private const DAILY_OVERTIME_CAP_HOURS = 2.0;

    public function __construct(private CompanySettingService $settings) {}

    /**
     * Create a new payroll period
     */
    public function createPayroll(array $data)
    {
        return Payroll::create([
            'period_name' => $data['period_name'],
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'payment_date' => $data['payment_date'],
            'status' => 'draft',
            'created_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Reprocess/recalculate payroll (deletes old items and recalculates)
     * Note: Transaction should be managed by the caller (PayrollController)
     */
    public function reprocessPayroll(Payroll $payroll, ?array $employeeIds = null)
    {
        // Delete existing payroll items
        PayrollItem::where('payroll_id', $payroll->id)->delete();

        // Process payroll again
        $this->processPayroll($payroll, $employeeIds);

        return $payroll->fresh();
    }

    /**
     * Process payroll for specific employees (OPTIMIZED with eager loading)
     */
    public function processPayroll(Payroll $payroll, ?array $employeeIds = null)
    {
        try {
            // OPTIMIZATION: Load all employees with ALL payroll-related data in minimal queries
            $employees = $this->getEmployeesWithPayrollData($payroll, $employeeIds);

            // OPTIMIZATION: Load holidays ONCE for entire period (cached)
            $holidays = $this->getHolidaysForPeriod($payroll->period_start, $payroll->period_end);

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;
            $payrollItems = []; // Collect items for insert
            $allAdjustmentIds = []; // Collect one-time salary adjustment IDs to mark as consumed
            $allBonusIds = []; // Collect bonus IDs to mark as paid after successful insert
            $selectedOvertimeEmployeeIds = collect($payroll->overtime_employee_ids ?? [])
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            $dailyOvertimeCapConfig = $this->getOvertimeDailyCapConfig();

            foreach ($employees as $employee) {
                $includeOvertime = empty($selectedOvertimeEmployeeIds)
                    ? true
                    : in_array((int) $employee->id, $selectedOvertimeEmployeeIds, true);

                $item = $this->calculatePayrollItem($payroll, $employee, $holidays, [
                    'include_overtime' => $includeOvertime,
                    'daily_overtime_cap_config' => $dailyOvertimeCapConfig,
                ]);

                // Skip employees with ₱0 or negative gross pay
                // These employees have no complete attendance records, so they shouldn't be included in payroll
                if ($item['gross_pay'] <= 0) {
                    continue;
                }

                // Collect items instead of individual inserts
                $payrollItems[] = $item;

                // Collect approved one-time adjustment IDs from this item
                if (!empty($item['_adjustment_ids'])) {
                    $allAdjustmentIds = array_merge($allAdjustmentIds, $item['_adjustment_ids']);
                }

                // Collect bonus IDs from this item
                if (!empty($item['_bonus_ids'])) {
                    $allBonusIds = array_merge($allBonusIds, $item['_bonus_ids']);
                }

                $totalGross += $item['gross_pay'];
                $totalDeductions += $item['total_deductions'];
                $totalNet += $item['net_pay'];
            }

            // Insert payroll items using Eloquent create() to respect model casts
            // (allowances_breakdown and deductions_breakdown are cast to array and need JSON encoding)
            foreach ($payrollItems as $item) {
                $leaveOutEntries = $item['_leave_out_entries'] ?? [];

                // Remove internal tracking fields before creating
                unset($item['_adjustment_ids']);
                unset($item['_bonus_ids']);
                unset($item['_leave_out_entries']);

                $payrollItem = PayrollItem::create($item);

                if (!empty($leaveOutEntries)) {
                    $this->recordLeaveOutPayrollLinks($payroll, $payrollItem, $leaveOutEntries);
                }
            }

            // Mark approved one-time salary adjustments as consumed by this payroll.
            if (!empty($allAdjustmentIds)) {
                $this->markSalaryAdjustmentsAsAppliedToPayroll($allAdjustmentIds, $payroll);
            }

            // Mark bonuses as paid AFTER successful inserts
            if (!empty($allBonusIds)) {
                \App\Models\EmployeeBonus::whereIn('id', $allBonusIds)
                    ->update([
                        'payment_status' => 'paid',
                        'paid_at' => now(),
                    ]);
            }

            $payroll->update([
                'total_gross' => $totalGross,
                'total_deductions' => $totalDeductions,
                'total_net' => $totalNet,
            ]);

            return $payroll;
        } catch (\Exception $e) {
            Log::error('Error processing payroll: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * OPTIMIZATION: Load all employees with payroll-related data in minimal queries
     * Uses eager loading to prevent N+1 query problem
     */
    private function getEmployeesWithPayrollData(Payroll $payroll, ?array $employeeIds = null)
    {
        $query = Employee::query();

        $referencePeriodColumn = SalaryAdjustment::referencePeriodColumn();
        $appliedPayrollColumn = SalaryAdjustment::appliedPayrollColumn();

        if (is_array($employeeIds)) {
            if (empty($employeeIds)) {
                return collect();
            }

            $query->whereIn('id', $employeeIds);
        }

        return $query
            // Eager load ALL relationships at once - prevents N+1 queries
            ->with([
                // Attendances for payroll period only (with needed columns)
                'attendances' => function ($q) use ($payroll) {
                    $q->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
                        ->where('status', '!=', 'absent')
                        ->where('approval_status', 'approved')
                        ->whereNotNull('time_in')
                        ->whereNotNull('time_out')
                        ->select(
                            'id',
                            'employee_id',
                            'attendance_date',
                            'status',
                            'regular_hours',
                            'overtime_hours',
                            'undertime_hours',
                            'time_in',
                            'time_out',
                            'ot_time_out',
                            'ot_time_out_2'
                        );
                },
                // Active allowances for period
                // CRITERIA: status='approved', is_active=true, effective_date <= period_end,
                // (end_date IS NULL OR end_date >= period_start)
                'allowances' => function ($q) use ($payroll) {
                    $q->where('status', 'approved')
                        ->where('is_active', true)
                        ->where('effective_date', '<=', $payroll->period_end)
                        ->where(function ($query) use ($payroll) {
                            $query->whereNull('end_date')
                                ->orWhere('end_date', '>=', $payroll->period_start);
                        })
                        ->select(
                            'id',
                            'employee_id',
                            'allowance_type',
                            'allowance_name',
                            'is_taxable',
                            'amount'
                        );
                },
                // Approved one-time salary exception records awaiting payroll consumption.
                'salaryAdjustments' => function ($q) use ($payroll, $referencePeriodColumn, $appliedPayrollColumn) {
                    $q->where('status', 'applied')
                        ->whereNull($appliedPayrollColumn)
                        ->whereNotNull('reason')
                        ->whereRaw("TRIM(reason) <> ''")
                        ->whereNotNull($referencePeriodColumn)
                        ->whereRaw("TRIM({$referencePeriodColumn}) <> ''")
                        ->where($referencePeriodColumn, 'not like', 'APPROVAL:%')
                        ->where(function ($query) use ($payroll) {
                            $query->whereNull('effective_date')
                                ->orWhereDate('effective_date', '<=', $payroll->period_end);
                        });
                },
                // Approved leaves during payroll period that have not yet been payroll-locked.
                'leaves' => function ($q) use ($payroll) {
                    $q->where('status', 'approved')
                        ->whereDate('leave_date_to', '>=', $payroll->period_start)
                        ->whereDate('leave_date_from', '<=', $payroll->period_end)
                        ->where(function ($query) {
                            $query->whereNull('is_locked')
                                ->orWhere('is_locked', false);
                        })
                        ->with([
                            'leaveType:id,is_paid',
                            'leaveOut.payrollLinks:id,employee_leave_out_id,payroll_item_id,payroll_id',
                        ]);
                },
                // Active loans with balance
                'loans' => function ($q) use ($payroll) {
                    $q->where('status', 'active')
                        ->where('balance', '>', 0)
                        ->where(function ($query) use ($payroll) {
                            $query->whereNull('first_payment_date')
                                ->orWhere('first_payment_date', '<=', $payroll->period_end);
                        })
                        ->select(
                            'id',
                            'employee_id',
                            'payment_frequency',
                            'semi_monthly_amortization',
                            'monthly_amortization',
                            'first_payment_date',
                            'balance'
                        );
                },
                // Active deductions
                // CRITERIA: status='active', balance > 0, start_date <= period_end,
                // (end_date IS NULL OR end_date >= period_start)
                'deductions' => function ($q) use ($payroll) {
                    $q->where('status', 'active')
                        ->where('balance', '>', 0)
                        ->where('start_date', '<=', $payroll->period_end)
                        ->where(function ($query) use ($payroll) {
                            $query->whereNull('end_date')
                                ->orWhere('end_date', '>=', $payroll->period_start);
                        })
                        ->select(
                            'id',
                            'employee_id',
                            'deduction_type',
                            'deduction_name',
                            'description',
                            'amount_per_cutoff',
                            'balance',
                            'total_amount',
                            'installments'
                        );
                },
                // Pending bonuses with payment_date within payroll period
                'bonuses' => function ($q) use ($payroll) {
                    $q->where('payment_status', 'pending')
                        ->where('payment_date', '>=', $payroll->period_start)
                        ->where('payment_date', '<=', $payroll->period_end)
                        ->select(
                            'id',
                            'employee_id',
                            'bonus_type',
                            'bonus_name',
                            'amount',
                            'is_taxable'
                        );
                },
            ])
            // Eager load positionRate for getBasicSalary() / getMonthlyRate() (avoids N+1)
            ->with('positionRate')
            ->where(function ($q) use ($payroll) {
                $q->whereIn('activity_status', ['active', 'on_leave'])
                    ->orWhereHas('attendances', function ($subQ) use ($payroll) {
                        $subQ->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
                            ->where('status', '!=', 'absent');
                    });
            })
            ->select(
                'id',
                'employee_number',
                'first_name',
                'last_name',
                'basic_salary',
                'salary_type',
                'custom_pay_rate',
                'position_id',
                'has_sss',
                'has_philhealth',
                'has_pagibig',
                'custom_sss',
                'custom_philhealth',
                'custom_pagibig'
            )
            ->get();
    }

    /**
     * OPTIMIZATION: Load holidays ONCE for entire period with caching
     * Public so PayrollController::generatePayrollItems() can reuse the same recurring-holiday logic
     */
    public function getHolidaysForPeriod(string $startDate, string $endDate): array
    {
        $cacheVersion = (int) Cache::get('holidays_cache_version', 1);
        $cacheKey = "holidays:v{$cacheVersion}:{$startDate}:{$endDate}";

        return Cache::remember($cacheKey, 3600, function () use ($startDate, $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            // Get holidays: exact date matches + recurring holidays matching month/day range
            $holidays = Holiday::where('is_active', true)
                ->where(function ($q) use ($startDate, $endDate, $start, $end) {
                    // Exact date match within period
                    $q->whereBetween('date', [$startDate, $endDate])
                        // OR recurring holidays whose month/day falls within the period
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->where('is_recurring', true)
                                ->where(function ($q3) use ($start, $end) {
                                    // Match recurring holidays by month and day within period range
                                    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                                        $q3->orWhere(function ($q4) use ($date) {
                                            $q4->whereMonth('date', $date->month)
                                                ->whereDay('date', $date->day);
                                        });
                                    }
                                });
                        });
                })
                ->get();

            // Key by actual period date (for recurring holidays, use period year)
            $result = [];
            foreach ($holidays as $holiday) {
                if ($holiday->is_recurring) {
                    // For recurring holidays, create the date key using the period's year
                    $holidayDate = Carbon::parse($holiday->date);
                    for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                        if ($date->month === $holidayDate->month && $date->day === $holidayDate->day) {
                            $result[$date->format('Y-m-d')] = $holiday;
                        }
                    }
                } else {
                    $result[Carbon::parse($holiday->date)->format('Y-m-d')] = $holiday;
                }
            }

            return $result;
        });
    }

    /**
     * Calculate payroll item for a specific employee (OPTIMIZED)
     */
    public function calculatePayrollItem(Payroll $payroll, Employee $employee, array $holidays = [], array $options = [])
    {
        $includeOvertime = $options['include_overtime'] ?? true;
        $dailyOvertimeCapHours = $this->resolveDailyOvertimeCapHours($employee, $options, $includeOvertime);

        // OPTIMIZATION: Use preloaded attendances (already eager loaded)
        $attendances = $employee->attendances;

        // Calculate days worked and hours
        $regularDays = 0;
        $sundayRegularHours = 0; // Track Sunday regular hours (not days) separately - will be added to special_ot_hours
        $holidayDays = 0;
        $regularOtHours = 0;
        $sundayOtHours = 0;
        $regularHolidayOtHours = 0; // Regular holiday OT (weekday)
        $regularHolidaySundayOtHours = 0; // Regular holiday OT on Sunday
        $specialHolidayOtHours = 0; // Special holiday OT
        $holidayPay = 0;
        $totalUndertimeHours = 0;
        $dailyOvertimeByDate = [];

        // Resolve to a daily-equivalent rate used by payroll formulas.
        // - daily: getBasicSalary() is already a daily rate
        // - monthly: convert monthly base to per-day using configured working days
        // - hourly: convert hourly base to per-day using standard hours
        $baseRate = $employee->getBasicSalary();
        // Monthly basic salary for government contribution table lookups (SSS/PhilHealth/Pag-IBIG).
        // Using getMonthlyRate() is correct — contributions are based on BASIC salary, NOT gross pay.
        // Gross pay includes OT, allowances, and holiday premiums which must NOT inflate MSC/PMB.
        $monthlyBasicSalary = $employee->getMonthlyRate();
        $standardHours = config('payroll.standard_hours_per_day', 8);
        $workingDaysPerMonth = (float) config('payroll.working_days_per_month', 22);

        if ($employee->salary_type === 'monthly') {
            $rate = $workingDaysPerMonth > 0 ? ($baseRate / $workingDaysPerMonth) : 0;
        } elseif ($employee->salary_type === 'hourly') {
            $rate = $baseRate * $standardHours;
        } else {
            $rate = $baseRate;
        }

        $hourlyRate = $standardHours > 0 ? ($rate / $standardHours) : 0;

        // Process each attendance record
        foreach ($attendances as $attendance) {
            $attendanceDate = Carbon::parse($attendance->attendance_date);
            $attendanceOvertimeHours = $this->resolveAttendanceOvertimeHours(
                $attendance,
                $dailyOvertimeCapHours,
                $dailyOvertimeByDate
            );

            // Avoid double-penalizing half-day records: half-day pay is already
            // represented via regularDays += 0.5, so its attendance-side
            // shortage should not be deducted again as undertime in payroll.
            if ($attendance->undertime_hours > 0) {
                if ((string) ($attendance->status ?? '') !== 'half_day') {
                    $totalUndertimeHours += $attendance->undertime_hours;
                }
            }

            // Check if this date is Sunday (0 = Sunday in Carbon)
            $isSunday = $attendanceDate->dayOfWeek === 0;

            // OPTIMIZATION: Check holiday from preloaded array (O(1) lookup - no query)
            $dateKey = $attendanceDate->format('Y-m-d');
            $holiday = $holidays[$dateKey] ?? null;

            if ($holiday && ($attendance->status === 'present' || $attendance->status === 'half_day' || $attendance->status === 'late')) {
                // This is a holiday with attendance - calculate holiday pay
                $hoursWorked = $attendance->regular_hours ?? 8; // Default to 8 hours if not specified
                $payMultiplier = $holiday->getPayMultiplier($attendanceDate);
                $dayHolidayPay = $rate * $payMultiplier * ($hoursWorked / $standardHours);

                // Calculate holiday pay for the hours worked
                $holidayPay += $dayHolidayPay;
                // Half day on holiday counts as 0.5, present/late counts as 1
                $holidayDays += ($attendance->status === 'half_day') ? 0.5 : 1;

                // Handle holiday overtime with specific rates
                if ($includeOvertime && $attendanceOvertimeHours > 0) {
                    if ($holiday->type === 'regular') {
                        // Regular holiday overtime
                        if ($isSunday) {
                            // Regular holiday on Sunday: rate/8 × 1.3 × 1.3 × hours
                            $regularHolidaySundayOtHours += $attendanceOvertimeHours;
                        } else {
                            // Regular holiday weekday: rate/8 × 2 × 1.3 × hours
                            $regularHolidayOtHours += $attendanceOvertimeHours;
                        }
                    } else {
                        // Special holiday: rate/8 × 1.3 × 1.3 × hours
                        $specialHolidayOtHours += $attendanceOvertimeHours;
                    }
                }
            } else {
                // Regular working day or Sunday
                if ($attendance->status === 'present' || $attendance->status === 'late') {
                    if ($isSunday) {
                        // Sunday regular hours - track as hours, not days
                        // Use actual hours worked from attendance, default to 8
                        $hoursWorked = $attendance->regular_hours ?? $standardHours;
                        $sundayRegularHours += $hoursWorked;
                    } else {
                        $regularDays++;
                    }
                } elseif ($attendance->status === 'half_day') {
                    if ($isSunday) {
                        // Half day on Sunday - add 4 hours (half of standard 8)
                        $hoursWorked = min(
                            ($attendance->regular_hours ?? ($standardHours / 2)),
                            ($standardHours / 2)
                        );
                        $sundayRegularHours += $hoursWorked;
                    } else {
                        $regularDays += 0.5; // Half day counts as 0.5
                    }
                }

                // Add overtime - separate Sunday from regular days
                if ($includeOvertime && $attendanceOvertimeHours > 0) {
                    if ($isSunday) {
                        $sundayOtHours += $attendanceOvertimeHours;
                    } else {
                        $regularOtHours += $attendanceOvertimeHours;
                    }
                }
            }
        }

        // ===== PERIOD-LEVEL UNDERTIME-OVERTIME OFFSET =====
        // Apply offset across the entire payroll period (not per day)
        // Total OT cancels total UT: if OT > UT, UT becomes 0 and OT is reduced
        $totalOtHours = $regularOtHours + $sundayOtHours + $regularHolidayOtHours +
            $regularHolidaySundayOtHours + $specialHolidayOtHours;

        if ($totalUndertimeHours > 0 && $totalOtHours > 0) {
            $offsetAmount = min($totalUndertimeHours, $totalOtHours);

            // Reduce undertime first
            $totalUndertimeHours = round($totalUndertimeHours - $offsetAmount, 4);

            // Reduce OT starting from regular OT (lowest multiplier), then special types
            $remainingOffset = $offsetAmount;

            // 1. Offset from regular OT first
            if ($remainingOffset > 0 && $regularOtHours > 0) {
                $reduceBy = min($remainingOffset, $regularOtHours);
                $regularOtHours = round($regularOtHours - $reduceBy, 4);
                $remainingOffset -= $reduceBy;
            }

            // 2. Offset from Sunday OT
            if ($remainingOffset > 0 && $sundayOtHours > 0) {
                $reduceBy = min($remainingOffset, $sundayOtHours);
                $sundayOtHours = round($sundayOtHours - $reduceBy, 4);
                $remainingOffset -= $reduceBy;
            }

            // 3. Offset from special holiday OT
            if ($remainingOffset > 0 && $specialHolidayOtHours > 0) {
                $reduceBy = min($remainingOffset, $specialHolidayOtHours);
                $specialHolidayOtHours = round($specialHolidayOtHours - $reduceBy, 4);
                $remainingOffset -= $reduceBy;
            }

            // 4. Offset from regular holiday OT
            if ($remainingOffset > 0 && $regularHolidayOtHours > 0) {
                $reduceBy = min($remainingOffset, $regularHolidayOtHours);
                $regularHolidayOtHours = round($regularHolidayOtHours - $reduceBy, 4);
                $remainingOffset -= $reduceBy;
            }

            // 5. Offset from regular holiday Sunday OT
            if ($remainingOffset > 0 && $regularHolidaySundayOtHours > 0) {
                $reduceBy = min($remainingOffset, $regularHolidaySundayOtHours);
                $regularHolidaySundayOtHours = round($regularHolidaySundayOtHours - $reduceBy, 4);
                $remainingOffset -= $reduceBy;
            }
        }
        // ===== END OFFSET =====

        $leavePayrollAdjustment = $this->calculateLeavePayrollAdjustments(
            $payroll,
            $employee,
            $rate,
            $hourlyRate,
            $standardHours
        );

        $paidLeaveDays = (float) ($leavePayrollAdjustment['paid_days'] ?? 0);
        $unpaidLeaveDays = (float) ($leavePayrollAdjustment['unpaid_days'] ?? 0);
        $leaveWithoutPayDeduction = (float) ($leavePayrollAdjustment['unpaid_deduction'] ?? 0);
        $leaveOutEntries = $leavePayrollAdjustment['leave_out_entries'] ?? [];

        // Keep paid leaves in days-worked display while unpaid leave deduction
        // remains auditable through leave_out -> payroll linkage records.
        $regularDaysForDisplay = $regularDays + $paidLeaveDays;
        $payableRegularDays = $regularDaysForDisplay + $unpaidLeaveDays;

        // Calculate basic pay (excluding holiday days and Sunday)
        // Only weekday regular pay at 1.0x
        // Sunday regular hours are NOT included in basic pay - they go to special_ot_pay
        $basicPay = ($rate * $payableRegularDays);

        // Calculate overtime pay with different rates
        // Regular days: rate/8 × 1.25 × hours
        $regularOtMultiplier = (float) $this->settings->get(
            'payroll.overtime.regularDay',
            config('payroll.overtime.regular_multiplier', 1.25)
        );
        $regularOtPay = $regularOtHours * $hourlyRate * $regularOtMultiplier;

        // Sunday regular hours pay: rate/8 × 1.3 × hours
        // These are regular hours worked on Sunday (not OT), paid at 1.3x
        $sundayRegularPay = $sundayRegularHours * $hourlyRate * 1.3;

        // Sunday (rest day) OT: rate/8 × multiplier × hours
        // The multiplier from settings IS the full rate applied to hourly rate.
        // Default 1.3 = 30% premium for rest day OT (matching company payroll).
        // For DOLE Art. 93 & 87 compliance (130% × 130% = 169%), set to 1.69 in Payroll Configuration.
        $sundayOtMultiplier = (float) $this->settings->get('payroll.overtime.sunday', 1.3);
        $sundayOtPay = $sundayOtHours * $hourlyRate * $sundayOtMultiplier;

        // Regular holiday (weekday): rate/8 × 2 × 1.3 × hours
        $regularHolidayMultiplier = (float) $this->settings->get(
            'payroll.holidays.regularHoliday',
            1.3
        );
        $regularHolidayOtPay = $regularHolidayOtHours * $hourlyRate * 2 * $regularHolidayMultiplier;

        // Regular holiday on Sunday: rate/8 × 2 × 1.3 × 1.3 × hours
        $regularHolidaySundayMultiplier = (float) $this->settings->get(
            'payroll.holidays.regularHolidaySunday',
            1.3
        );
        $regularHolidaySundayOtPay = $regularHolidaySundayOtHours * $hourlyRate * 2 * 1.3 * $regularHolidaySundayMultiplier;

        // Special holiday: rate/8 × 1.3 × 1.3 × hours
        $specialHolidayMultiplier = (float) $this->settings->get(
            'payroll.holidays.specialHoliday',
            1.3
        );
        $specialHolidayOtPay = $specialHolidayOtHours * $hourlyRate * 1.3 * $specialHolidayMultiplier;

        // Calculate total Sunday hours and pay separately
        // Total Sunday hours = Sunday regular hours + Sunday OT hours
        $totalSundayHours = $sundayRegularHours + $sundayOtHours;
        $totalSundayPay = $sundayRegularPay + $sundayOtPay;

        // Total special OT hours and pay (EXCLUDES Sunday hours - they are tracked separately)
        // Only includes holiday OT hours
        $specialOtHours = $regularHolidayOtHours + $regularHolidaySundayOtHours + $specialHolidayOtHours;
        $specialOtPay = $regularHolidayOtPay + $regularHolidaySundayOtPay + $specialHolidayOtPay;
        $totalOtPay = $regularOtPay + $totalSundayPay + $specialOtPay;

        // OPTIMIZATION: Use preloaded relationships (no queries)
        $activeAllowances = $employee->allowances;
        $allowances = $activeAllowances->sum('amount') ?? 0;
        // Taxable allowance classification is deprecated for this module.
        $taxableAllowances = 0;

        $allowancesBreakdown = $activeAllowances->map(function ($allowance) {
            return [
                'id' => $allowance->id,
                'type' => $allowance->allowance_type,
                'name' => $allowance->allowance_name ?: $allowance->allowance_type,
                'amount' => (float) $allowance->amount,
            ];
        })->values()->all();

        // OPTIMIZATION: Use preloaded bonuses (pending bonuses with payment_date in period)
        $pendingBonuses = $employee->relationLoaded('bonuses')
            ? $employee->bonuses
            : collect();
        $bonusTotal = (float) $pendingBonuses->sum('amount');
        $taxableBonusTotal = 0;
        $bonusIds = [];

        foreach ($pendingBonuses as $bonus) {
            $allowancesBreakdown[] = [
                'id' => $bonus->id,
                'type' => 'bonus',
                'name' => $bonus->bonus_name ?: $bonus->bonus_type,
                'amount' => (float) $bonus->amount,
            ];

            if ((bool) ($bonus->is_taxable ?? true)) {
                $taxableBonusTotal += (float) $bonus->amount;
            }

            $bonusIds[] = $bonus->id;
        }

        $otherAllowances = $allowances + $bonusTotal;

        // Calculate salary adjustment from pending adjustments (OPTIMIZED - preloaded)
        // Business rule: "No. of Days" should count only regular weekday days.
        // Sunday and holiday work are shown in SUN/SPL. HOL columns instead.
        $totalDaysWorked = $regularDaysForDisplay;

        // ADJ. PREV. SALARY comes only from approved one-time
        // salary exception records awaiting payroll consumption.
        [$oneTimeAdjustment, $oneTimeAdjustmentIds] = $this->calculateApprovedOneTimeAdjustment(
            $payroll,
            $employee,
        );
        $salaryAdjustment = round($oneTimeAdjustment, 2);

        // Calculate undertime deduction
        // Formula: (rate / standard_hours) * undertime_hours
        $undertimeDeduction = $hourlyRate * $totalUndertimeHours;

        // Calculate gross pay (include holiday pay, overtime, salary adjustment)
        // Note: COLA removed, salary adjustment added (can be negative for deductions)
        // Undertime deduction is NOT subtracted here - it's treated as a deduction from gross to net
        $grossPay = $basicPay + $holidayPay + $totalOtPay + $otherAllowances + $salaryAdjustment;

        // Calculate government deductions (only if enabled for the employee)
        // Use custom contributions if set, otherwise calculate based on salary
        // IMPORTANT: Skip government contributions if gross pay is ₱0 or negative
        // Also respect payroll-level deduction flags
        $sss = 0;
        $philhealth = 0;
        $pagibig = 0;

        if ($grossPay > 0) {
            // Use payroll period end as the effective date so historical payrolls
            // use the contribution tables active during that payroll period.
            $contributionDate = $payroll->period_end;

            // SSS: Check both employee flag AND payroll flag
            if ($employee->has_sss && ($payroll->deduct_sss ?? true)) {
                // Use custom SSS if set (already semi-monthly amount), otherwise calculate
                $sss = $employee->custom_sss !== null
                    ? (float) $employee->custom_sss
                    : $this->calculateSSS($monthlyBasicSalary, $contributionDate);
            }

            // PhilHealth: Check both employee flag AND payroll flag
            if ($employee->has_philhealth && ($payroll->deduct_philhealth ?? true)) {
                // Use custom PhilHealth if set (already semi-monthly amount), otherwise calculate
                $philhealth = $employee->custom_philhealth !== null
                    ? (float) $employee->custom_philhealth
                    : $this->calculatePhilHealth($monthlyBasicSalary, $contributionDate);
            }

            // Pag-IBIG: Check both employee flag AND payroll flag
            if ($employee->has_pagibig && ($payroll->deduct_pagibig ?? true)) {
                // Use custom Pag-IBIG if set (already semi-monthly amount), otherwise calculate
                $pagibig = $employee->custom_pagibig !== null
                    ? (float) $employee->custom_pagibig
                    : $this->calculatePagibig($monthlyBasicSalary, $contributionDate);
            }
        }

        // Determine if this is semi-monthly payroll (typically 15 days or less)
        $periodStart = Carbon::parse($payroll->period_start);
        $periodEnd = Carbon::parse($payroll->period_end);
        $periodDays = $periodStart->diffInDays($periodEnd) + 1;
        $isSemiMonthly = $periodDays <= 16;

        // Compute withholding tax from dynamic GovernmentRate tax table.
        // Taxable base excludes non-taxable allowances.
        $taxableSupplementalIncome = $taxableAllowances + $taxableBonusTotal;
        $taxableGrossPay = $basicPay + $holidayPay + $totalOtPay +
            $taxableSupplementalIncome + $salaryAdjustment - $leaveWithoutPayDeduction;
        $taxableIncome = max($taxableGrossPay - $sss - $philhealth - $pagibig, 0);
        $withholdingTax = $this->calculateWithholdingTax($taxableIncome, $payroll->period_end, $isSemiMonthly);

        // For monthly loans on semi-monthly payrolls: only deduct on 2nd cutoff
        // 2nd cutoff = period ending on 16th or later of the month
        $isSecondCutoff = !$isSemiMonthly || $periodEnd->day >= 16;

        // OPTIMIZATION: Use preloaded loans when enabled for this payroll
        $activeLoans = ($payroll->deduct_loans ?? true) ? $employee->loans : collect();

        $loanDeduction = 0;
        foreach ($activeLoans as $loan) {
            if ($loan->first_payment_date && Carbon::parse($loan->first_payment_date)->gt($periodEnd)) {
                continue;
            }

            // Determine payment amount based on payroll period and loan payment frequency
            $amortization = 0;
            if ($loan->payment_frequency === 'semi_monthly') {
                // Semi-monthly loans: use semi_monthly_amortization for semi-monthly payrolls
                // For monthly payrolls, use monthly_amortization (2 payments combined)
                $amortization = $isSemiMonthly
                    ? ($loan->semi_monthly_amortization ?? 0)
                    : ($loan->monthly_amortization ?? 0);
            } else {
                // Monthly loans: deduct on 2nd cutoff only (period end day >= 16)
                // This ensures monthly loans are only deducted once per month
                if ($isSecondCutoff) {
                    $amortization = $loan->monthly_amortization ?? 0;
                }
            }

            // Cap the deduction to the remaining loan balance
            $loanDeduction += min($amortization, $loan->balance);
        }

        // Employee savings
        $employeeSavings = 0;

        // Cash advance (displayed separately in payroll register)
        $cashAdvance = 0;

        $deductOtherEmployeeDeductions = (bool) ($payroll->deduct_employee_deductions ?? true);
        $deductCashAdvance = (bool) ($payroll->deduct_cash_advance ?? true);
        $deductCashBond = (bool) ($payroll->deduct_cash_bond ?? true);
        $deductEmployeeSavings = (bool) ($payroll->deduct_employee_savings ?? true);

        // OPTIMIZATION: Use preloaded deductions only when at least one manual stream is enabled.
        $shouldUseManualDeductions =
            $deductOtherEmployeeDeductions ||
            $deductCashAdvance ||
            $deductCashBond ||
            $deductEmployeeSavings;
        $activeDeductions = $shouldUseManualDeductions ? $employee->deductions : collect();

        // Government deductions must come from GovernmentRate settings and
        // employee contribution toggles only, never from manual deductions.
        $governmentManagedDeductionTypes = ['sss', 'philhealth', 'pagibig', 'tax'];

        // Types that go into "Other Deductions" column (excluding cash advance)
        $otherDeductionTypes = ['damages'];
        $employeeSavingsTypes = ['cooperative'];

        $employeeDeductions = 0;
        $otherDeductions = 0;
        $deductionsBreakdown = [];
        $deductionRows = [];
        $scheduledCashAdvance = 0;

        foreach ($activeDeductions as $deduction) {
            $deductionType = (string) $deduction->deduction_type;

            if (in_array($deductionType, $governmentManagedDeductionTypes, true)) {
                continue;
            }

            if ($deductionType === 'cash_advance' && !$deductCashAdvance) {
                continue;
            }

            if ($deductionType === 'cash_bond' && !$deductCashBond) {
                continue;
            }

            if (in_array($deductionType, $employeeSavingsTypes, true) && !$deductEmployeeSavings) {
                continue;
            }

            $isGeneralEmployeeDeduction = !in_array(
                $deductionType,
                array_merge($governmentManagedDeductionTypes, ['cash_advance', 'cash_bond'], $employeeSavingsTypes),
                true
            );

            if ($isGeneralEmployeeDeduction && !$deductOtherEmployeeDeductions) {
                continue;
            }

            $amountPerCutoff = $deduction->amount_per_cutoff;
            if (!$amountPerCutoff && $deduction->installments > 0) {
                $amountPerCutoff = $deduction->total_amount / $deduction->installments;
            }

            $deductionAmount = min((float) $amountPerCutoff, (float) $deduction->balance);
            if ($deductionAmount <= 0) {
                continue;
            }

            $deductionRows[] = [
                'id' => $deduction->id,
                'type' => $deduction->deduction_type,
                'name' => $deduction->deduction_name,
                'amount' => $deductionAmount,
            ];

            if ($deductionType === 'cash_advance') {
                $scheduledCashAdvance += $deductionAmount;
            } elseif (in_array($deductionType, $employeeSavingsTypes, true)) {
                $employeeSavings += $deductionAmount;
            } elseif (in_array($deductionType, $otherDeductionTypes, true)) {
                $otherDeductions += $deductionAmount;
            } else {
                $employeeDeductions += $deductionAmount;
            }
        }

        if ($leaveWithoutPayDeduction > 0) {
            $otherDeductions += $leaveWithoutPayDeduction;
        }

        // Keep cash advance deductions from consuming more than available earnings.
        // This still respects each deduction balance, but avoids over-deducting take-home pay.
        $nonCashDeductions = $sss + $philhealth + $pagibig + $loanDeduction +
            $withholdingTax + $employeeSavings + $employeeDeductions +
            $otherDeductions + $undertimeDeduction;

        $maxCashAdvanceByPay = max($grossPay - $nonCashDeductions, 0);
        $cashAdvance = min($scheduledCashAdvance, $maxCashAdvanceByPay);

        $remainingCashAdvance = $cashAdvance;
        foreach ($deductionRows as $row) {
            $appliedAmount = $row['amount'];

            if ($row['type'] === 'cash_advance') {
                $appliedAmount = min($appliedAmount, $remainingCashAdvance);
                $remainingCashAdvance -= $appliedAmount;
            }

            if ($appliedAmount <= 0) {
                continue;
            }

            $deductionsBreakdown[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'name' => $row['name'],
                'amount' => $appliedAmount,
            ];
        }

        if ($leaveWithoutPayDeduction > 0) {
            foreach ($leaveOutEntries as $entry) {
                $entryAmount = round((float) ($entry['deduction_amount'] ?? 0), 2);
                if ($entryAmount <= 0) {
                    continue;
                }

                $deductionsBreakdown[] = [
                    'type' => 'leave_without_pay',
                    'name' => 'Leave Without Pay',
                    'amount' => $entryAmount,
                    'leave_out_id' => (int) ($entry['leave_out_id'] ?? 0),
                    'leave_id' => (int) ($entry['leave_id'] ?? 0),
                    'days' => (float) ($entry['applied_days'] ?? 0),
                    'hours' => (float) ($entry['applied_hours'] ?? 0),
                ];
            }
        }

        // Total deductions (including withholding tax and undertime deduction)
        $totalDeductions = $sss + $philhealth + $pagibig + $loanDeduction +
            $withholdingTax + $employeeSavings + $cashAdvance + $employeeDeductions +
            $otherDeductions + $undertimeDeduction;

        // Net pay = Gross pay - Total deductions
        $netPay = $grossPay - $totalDeductions;

        return [
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'rate' => $rate,
            'days_worked' => $totalDaysWorked,
            'regular_days' => $regularDaysForDisplay,
            'holiday_days' => $holidayDays,
            'holiday_pay' => $holidayPay,
            'basic_pay' => $basicPay,
            'regular_ot_hours' => $regularOtHours,
            'regular_ot_pay' => $regularOtPay,
            'special_ot_hours' => $specialOtHours,
            'special_ot_pay' => $specialOtPay,
            'sunday_hours' => $totalSundayHours,
            'sunday_pay' => $totalSundayPay,
            'other_allowances' => $otherAllowances,
            'salary_adjustment' => $salaryAdjustment,
            'allowances_breakdown' => $allowancesBreakdown,
            'gross_pay' => $grossPay,
            'undertime_hours' => $totalUndertimeHours,
            'undertime_deduction' => $undertimeDeduction,
            'sss' => $sss,
            'philhealth' => $philhealth,
            'pagibig' => $pagibig,
            'withholding_tax' => $withholdingTax,
            'employee_savings' => $employeeSavings,
            'cash_advance' => $cashAdvance,
            'loans' => $loanDeduction,
            'employee_deductions' => $employeeDeductions,
            'deductions_breakdown' => $deductionsBreakdown,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            '_adjustment_ids' => $oneTimeAdjustmentIds, // Internal: mark consumed after successful payroll save
            '_bonus_ids' => $bonusIds, // Internal: collected by processPayroll to mark bonuses as paid
            '_leave_out_entries' => $leaveOutEntries, // Internal: leave_out -> payroll linkage + lock operation
        ];
    }

    private function calculateLeavePayrollAdjustments(
        Payroll $payroll,
        Employee $employee,
        float $dailyRate,
        float $hourlyRate,
        float $standardHours
    ): array {
        $approvedLeaves = $this->resolveApprovedLeavesForPayroll($payroll, $employee);

        if ($approvedLeaves->isEmpty()) {
            return [
                'paid_days' => 0,
                'unpaid_days' => 0,
                'unpaid_deduction' => 0,
                'leave_out_entries' => [],
            ];
        }

        $paidDays = 0.0;
        $unpaidDays = 0.0;
        $unpaidDeduction = 0.0;
        $leaveOutEntries = [];

        foreach ($approvedLeaves as $leave) {
            $coverage = $this->resolveLeaveCoverageForPayroll($leave, $payroll, $standardHours);
            $dayEquivalent = (float) ($coverage['day_equivalent'] ?? 0);
            $appliedHours = (float) ($coverage['hours'] ?? 0);

            if ($dayEquivalent <= 0 && $appliedHours <= 0) {
                continue;
            }

            if ($leave->isWithPay()) {
                $paidDays += $dayEquivalent;
                continue;
            }

            $leaveOut = $this->ensureLeaveOutRecord($leave);
            if (!$leaveOut) {
                continue;
            }

            $alreadyLinked = $leaveOut->relationLoaded('payrollLinks')
                ? $leaveOut->payrollLinks->isNotEmpty()
                : $leaveOut->payrollLinks()->exists();

            if ($alreadyLinked) {
                continue;
            }

            $durationType = (string) ($coverage['duration_type'] ?? 'full_day');
            $deductionAmount = $durationType === 'hours'
                ? round($appliedHours * $hourlyRate, 2)
                : round($dayEquivalent * $dailyRate, 2);

            if ($deductionAmount <= 0 && $dayEquivalent <= 0 && $appliedHours <= 0) {
                continue;
            }

            $unpaidDays += $dayEquivalent;
            $unpaidDeduction += $deductionAmount;

            $leaveOutEntries[] = [
                'leave_id' => (int) $leave->id,
                'leave_out_id' => (int) $leaveOut->id,
                'applied_days' => round($dayEquivalent, 2),
                'applied_hours' => round($appliedHours, 2),
                'deduction_amount' => $deductionAmount,
            ];
        }

        return [
            'paid_days' => round($paidDays, 2),
            'unpaid_days' => round($unpaidDays, 2),
            'unpaid_deduction' => round($unpaidDeduction, 2),
            'leave_out_entries' => $leaveOutEntries,
        ];
    }

    private function resolveApprovedLeavesForPayroll(Payroll $payroll, Employee $employee)
    {
        if ($employee->relationLoaded('leaves')) {
            $periodStart = Carbon::parse($payroll->period_start)->toDateString();
            $periodEnd = Carbon::parse($payroll->period_end)->toDateString();

            return $employee->leaves->filter(function ($leave) use ($periodStart, $periodEnd) {
                if ((string) ($leave->status ?? '') !== 'approved') {
                    return false;
                }

                if ((bool) ($leave->is_locked ?? false)) {
                    return false;
                }

                $leaveStart = optional($leave->leave_date_from)->toDateString();
                $leaveEnd = optional($leave->leave_date_to)->toDateString();

                if (!$leaveStart || !$leaveEnd) {
                    return false;
                }

                return $leaveEnd >= $periodStart && $leaveStart <= $periodEnd;
            })->values();
        }

        return EmployeeLeave::query()
            ->with([
                'leaveType:id,is_paid',
                'leaveOut.payrollLinks:id,employee_leave_out_id,payroll_item_id,payroll_id',
            ])
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('leave_date_to', '>=', $payroll->period_start)
            ->whereDate('leave_date_from', '<=', $payroll->period_end)
            ->where(function ($query) {
                $query->whereNull('is_locked')
                    ->orWhere('is_locked', false);
            })
            ->get();
    }

    private function resolveLeaveCoverageForPayroll(EmployeeLeave $leave, Payroll $payroll, float $standardHours): array
    {
        $periodStart = Carbon::parse($payroll->period_start)->startOfDay();
        $periodEnd = Carbon::parse($payroll->period_end)->startOfDay();
        $leaveStart = Carbon::parse($leave->leave_date_from)->startOfDay();
        $leaveEnd = Carbon::parse($leave->leave_date_to)->startOfDay();

        $overlapStart = $leaveStart->greaterThan($periodStart) ? $leaveStart : $periodStart;
        $overlapEnd = $leaveEnd->lessThan($periodEnd) ? $leaveEnd : $periodEnd;

        if ($overlapEnd->lt($overlapStart)) {
            return [
                'duration_type' => (string) ($leave->duration_type ?? 'full_day'),
                'day_equivalent' => 0,
                'hours' => 0,
            ];
        }

        $durationType = in_array((string) $leave->duration_type, ['full_day', 'half_day', 'hours'], true)
            ? (string) $leave->duration_type
            : 'full_day';

        if ($durationType === 'half_day') {
            return [
                'duration_type' => $durationType,
                'day_equivalent' => 0.5,
                'hours' => round($standardHours / 2, 2),
            ];
        }

        if ($durationType === 'hours') {
            $hours = max((float) ($leave->duration_hours ?? 0), 0);

            return [
                'duration_type' => $durationType,
                'day_equivalent' => round($standardHours > 0 ? ($hours / $standardHours) : 0, 2),
                'hours' => round($hours, 2),
            ];
        }

        $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;

        return [
            'duration_type' => 'full_day',
            'day_equivalent' => (float) $overlapDays,
            'hours' => round($overlapDays * $standardHours, 2),
        ];
    }

    private function ensureLeaveOutRecord(EmployeeLeave $leave): ?EmployeeLeaveOut
    {
        $leaveOut = EmployeeLeaveOut::updateOrCreate(
            ['employee_leave_id' => $leave->id],
            [
                'employee_id' => $leave->employee_id,
                'leave_type_id' => $leave->leave_type_id,
                'leave_date_from' => optional($leave->leave_date_from)->toDateString(),
                'leave_date_to' => optional($leave->leave_date_to)->toDateString(),
                'duration_type' => $leave->duration_type ?? 'full_day',
                'quantity_days' => (float) ($leave->number_of_days ?? 0),
                'quantity_hours' => (float) ($leave->duration_hours ?? 0),
                'created_by' => $leave->approved_by,
            ]
        );

        return $leaveOut->loadMissing('payrollLinks:id,employee_leave_out_id,payroll_item_id,payroll_id');
    }

    public function recordLeaveOutPayrollLinks(Payroll $payroll, PayrollItem $payrollItem, array $leaveOutEntries): void
    {
        if (empty($leaveOutEntries)) {
            return;
        }

        $normalizedEntries = collect($leaveOutEntries)
            ->map(function ($entry) {
                return [
                    'leave_out_id' => (int) ($entry['leave_out_id'] ?? 0),
                    'leave_id' => (int) ($entry['leave_id'] ?? 0),
                    'deduction_amount' => round((float) ($entry['deduction_amount'] ?? 0), 2),
                    'applied_days' => round((float) ($entry['applied_days'] ?? 0), 2),
                    'applied_hours' => round((float) ($entry['applied_hours'] ?? 0), 2),
                ];
            })
            ->filter(fn($entry) => $entry['leave_out_id'] > 0)
            ->groupBy('leave_out_id')
            ->map(function ($group) {
                return [
                    'leave_out_id' => (int) $group->first()['leave_out_id'],
                    'leave_id' => (int) $group->first()['leave_id'],
                    'deduction_amount' => round((float) $group->sum('deduction_amount'), 2),
                    'applied_days' => round((float) $group->sum('applied_days'), 2),
                    'applied_hours' => round((float) $group->sum('applied_hours'), 2),
                ];
            })
            ->values();

        if ($normalizedEntries->isEmpty()) {
            return;
        }

        $leaveOutIds = $normalizedEntries->pluck('leave_out_id')->all();
        $existingLinks = PayrollItemLeaveOut::whereIn('employee_leave_out_id', $leaveOutIds)
            ->pluck('employee_leave_out_id')
            ->map(fn($id) => (int) $id)
            ->all();
        $existingLinkMap = array_flip($existingLinks);

        $lockedLeaveIds = [];

        foreach ($normalizedEntries as $entry) {
            $leaveOutId = (int) $entry['leave_out_id'];
            if (isset($existingLinkMap[$leaveOutId])) {
                continue;
            }

            PayrollItemLeaveOut::create([
                'employee_leave_out_id' => $leaveOutId,
                'payroll_item_id' => $payrollItem->id,
                'payroll_id' => $payroll->id,
                'deduction_amount' => $entry['deduction_amount'],
                'applied_days' => $entry['applied_days'],
                'applied_hours' => $entry['applied_hours'],
            ]);

            if ((int) $entry['leave_id'] > 0) {
                $lockedLeaveIds[] = (int) $entry['leave_id'];
            }
        }

        $lockedLeaveIds = collect($lockedLeaveIds)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (!empty($lockedLeaveIds)) {
            EmployeeLeave::query()
                ->whereIn('id', $lockedLeaveIds)
                ->update([
                    'is_locked' => true,
                    'locked_by_payroll_id' => $payroll->id,
                    'locked_at' => now(),
                ]);
        }
    }

    /**
     * Sum approved one-time salary exception records for the employee.
     *
     * Returns [totalAdjustment, adjustmentIds]
     */
    private function calculateApprovedOneTimeAdjustment(Payroll $payroll, Employee $employee): array
    {
        $referencePeriodColumn = SalaryAdjustment::referencePeriodColumn();
        $appliedPayrollColumn = SalaryAdjustment::appliedPayrollColumn();
        $adjustmentTypeColumn = SalaryAdjustment::adjustmentTypeColumn();

        if ($employee->relationLoaded('salaryAdjustments')) {
            $adjustments = $employee->salaryAdjustments;
        } else {
            $adjustments = SalaryAdjustment::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'applied')
                ->whereNull($appliedPayrollColumn)
                ->whereNotNull('reason')
                ->whereRaw("TRIM(reason) <> ''")
                ->whereNotNull($referencePeriodColumn)
                ->whereRaw("TRIM({$referencePeriodColumn}) <> ''")
                ->where($referencePeriodColumn, 'not like', 'APPROVAL:%')
                ->where(function ($query) use ($payroll) {
                    $query->whereNull('effective_date')
                        ->orWhereDate('effective_date', '<=', $payroll->period_end);
                })
                ->get(['id', 'employee_id', 'amount', $adjustmentTypeColumn]);
        }

        if ($adjustments->isEmpty()) {
            return [0.0, []];
        }

        $total = 0.0;
        $adjustmentIds = [];

        foreach ($adjustments as $adjustment) {
            $amount = (float) ($adjustment->amount ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $total += $adjustment->type === 'deduction'
                ? -abs($amount)
                : abs($amount);

            $adjustmentIds[] = (int) $adjustment->id;
        }

        return [round($total, 2), array_values(array_unique($adjustmentIds))];
    }

    private function markSalaryAdjustmentsAsAppliedToPayroll(array $adjustmentIds, Payroll $payroll): void
    {
        $appliedPayrollColumn = SalaryAdjustment::appliedPayrollColumn();

        $normalizedIds = collect($adjustmentIds)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedIds)) {
            return;
        }

        SalaryAdjustment::query()
            ->whereIn('id', $normalizedIds)
            ->where('status', 'applied')
            ->whereNull($appliedPayrollColumn)
            ->update([
                $appliedPayrollColumn => $payroll->id,
            ]);
    }

    private function resolveDailyOvertimeCapHours(Employee $employee, array $options, bool $includeOvertime): ?float
    {
        if (!$includeOvertime) {
            return null;
        }

        if (array_key_exists('daily_overtime_cap_hours', $options)) {
            $capHours = $options['daily_overtime_cap_hours'];
            if ($capHours === null) {
                return null;
            }

            return max((float) $capHours, 0);
        }

        $dailyCapConfig = $options['daily_overtime_cap_config'] ?? $this->getOvertimeDailyCapConfig();

        if (!$this->shouldApplyDailyOvertimeCap($employee, $dailyCapConfig)) {
            return null;
        }

        return max((float) ($dailyCapConfig['hours'] ?? self::DAILY_OVERTIME_CAP_HOURS), 0);
    }

    private function resolveAttendanceOvertimeHours($attendance, ?float $dailyOvertimeCapHours, array &$dailyOvertimeByDate = []): float
    {
        $overtimeHours = (float) ($attendance->overtime_hours ?? 0);

        if ($overtimeHours <= 0) {
            return 0;
        }

        if ($dailyOvertimeCapHours === null) {
            return $overtimeHours;
        }

        if ($dailyOvertimeCapHours <= 0) {
            return 0;
        }

        $attendanceDate = $attendance->attendance_date;
        if ($attendanceDate instanceof Carbon) {
            $dateKey = $attendanceDate->format('Y-m-d');
        } else {
            $dateKey = Carbon::parse($attendanceDate)->format('Y-m-d');
        }

        $alreadyAllocated = (float) ($dailyOvertimeByDate[$dateKey] ?? 0);
        $remainingForDay = max($dailyOvertimeCapHours - $alreadyAllocated, 0);
        if ($remainingForDay <= 0) {
            return 0;
        }

        $allowedOvertime = min($overtimeHours, $remainingForDay);
        $dailyOvertimeByDate[$dateKey] = round($alreadyAllocated + $allowedOvertime, 4);

        return $allowedOvertime;
    }

    private function getOvertimeDailyCapConfig(): array
    {
        return [
            'hours' => self::DAILY_OVERTIME_CAP_HOURS,
            'position_ids' => $this->normalizeIdArray(
                $this->settings->get('payroll.overtime.dailyCap.positionIds', [])
            ),
            'employee_ids' => $this->normalizeIdArray(
                $this->settings->get('payroll.overtime.dailyCap.employeeIds', [])
            ),
        ];
    }

    private function shouldApplyDailyOvertimeCap(Employee $employee, array $dailyCapConfig): bool
    {
        $positionIds = $dailyCapConfig['position_ids'] ?? [];
        $employeeIds = $dailyCapConfig['employee_ids'] ?? [];

        if (empty($positionIds) && empty($employeeIds)) {
            return false;
        }

        $employeeId = (int) $employee->id;
        if ($employeeId > 0 && in_array($employeeId, $employeeIds, true)) {
            return true;
        }

        $positionId = (int) ($employee->position_id ?? 0);

        return $positionId > 0 && in_array($positionId, $positionIds, true);
    }

    private function normalizeIdArray($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Calculate SSS contribution using GovernmentRate settings from /settings dialog
     * Falls back to 2025 hardcoded table if settings not configured
     *
     * @param float $monthlyBasicSalary  Employee's monthly basic salary (NOT gross pay).
     *                                   SSS looks up MSC (Monthly Salary Credit) from basic salary only.
     */
    private function calculateSSS($monthlyBasicSalary, $date = null)
    {
        // Use monthly basic salary directly for SSS MSC table lookup
        $monthlySalary = $monthlyBasicSalary;

        // Try to get from settings dialog configuration
        $contribution = GovernmentRate::getContributionForSalary('sss', $monthlySalary, $date);

        if ($contribution && $contribution['employee'] > 0) {
            // Found in settings - use monthly amount, divide by 2 for semi-monthly
            return round($contribution['employee'] / 2, 2);
        }

        // FALLBACK: 2025 SSS hardcoded table if settings not configured
        // This ensures backward compatibility
        if ($monthlySalary < 4250) return 180.00 / 2;
        if ($monthlySalary < 4750) return 202.50 / 2;
        if ($monthlySalary < 5250) return 225.00 / 2;
        if ($monthlySalary < 5750) return 247.50 / 2;
        if ($monthlySalary < 6250) return 270.00 / 2;
        if ($monthlySalary < 6750) return 292.50 / 2;
        if ($monthlySalary < 7250) return 315.00 / 2;
        if ($monthlySalary < 7750) return 337.50 / 2;
        if ($monthlySalary < 8250) return 360.00 / 2;
        if ($monthlySalary < 8750) return 382.50 / 2;
        if ($monthlySalary < 9250) return 405.00 / 2;
        if ($monthlySalary < 9750) return 427.50 / 2;
        if ($monthlySalary < 10250) return 450.00 / 2;
        if ($monthlySalary < 10750) return 472.50 / 2;
        if ($monthlySalary < 11250) return 495.00 / 2;
        if ($monthlySalary < 11750) return 517.50 / 2;
        if ($monthlySalary < 12250) return 540.00 / 2;
        if ($monthlySalary < 12750) return 562.50 / 2;
        if ($monthlySalary < 13250) return 585.00 / 2;
        if ($monthlySalary < 13750) return 607.50 / 2;
        if ($monthlySalary < 14250) return 630.00 / 2;
        if ($monthlySalary < 14750) return 652.50 / 2;
        if ($monthlySalary < 15250) return 675.00 / 2;
        if ($monthlySalary < 15750) return 697.50 / 2;
        if ($monthlySalary < 16250) return 720.00 / 2;
        if ($monthlySalary < 16750) return 742.50 / 2;
        if ($monthlySalary < 17250) return 765.00 / 2;
        if ($monthlySalary < 17750) return 787.50 / 2;
        if ($monthlySalary < 18250) return 810.00 / 2;
        if ($monthlySalary < 18750) return 832.50 / 2;
        if ($monthlySalary < 19250) return 855.00 / 2;
        if ($monthlySalary < 19750) return 877.50 / 2;
        if ($monthlySalary < 20250) return 900.00 / 2;
        if ($monthlySalary < 20750) return 922.50 / 2;
        if ($monthlySalary < 21250) return 945.00 / 2;
        if ($monthlySalary < 21750) return 967.50 / 2;
        if ($monthlySalary < 22250) return 990.00 / 2;
        if ($monthlySalary < 22750) return 1012.50 / 2;
        if ($monthlySalary < 23250) return 1035.00 / 2;
        if ($monthlySalary < 23750) return 1057.50 / 2;
        if ($monthlySalary < 24250) return 1080.00 / 2;
        if ($monthlySalary < 24750) return 1102.50 / 2;
        if ($monthlySalary >= 25000) return 1125.00 / 2;

        return 1125.00 / 2; // Maximum
    }

    /**
     * Calculate PhilHealth contribution using GovernmentRate settings from /settings dialog
     * Falls back to 2025 hardcoded formula if settings not configured
     *
     * @param float $monthlyBasicSalary  Employee's monthly basic salary (NOT gross pay).
     *                                   PhilHealth premium base is monthly basic salary per RA 11223.
     */
    private function calculatePhilHealth($monthlyBasicSalary, $date = null)
    {
        // Use monthly basic salary directly for PhilHealth premium computation
        $monthlyGross = $monthlyBasicSalary;

        // Try to get from settings dialog configuration
        $contribution = GovernmentRate::getContributionForSalary('philhealth', $monthlyGross, $date);

        if ($contribution && $contribution['employee'] > 0) {
            // Found in settings - use monthly amount, divide by 2 for semi-monthly
            return round($contribution['employee'] / 2, 2);
        }

        // FALLBACK: 2024-2026 PhilHealth hardcoded formula if settings not configured
        $contribution = $monthlyGross * 0.045;
        $employeeShare = $contribution / 2;
        $semiMonthlyShare = $employeeShare / 2;

        // Minimum: ₱225, Maximum: ₱1,800 per month (₱112.5 to ₱900 semi-monthly)
        return round(min(max($semiMonthlyShare, 112.50), 900), 2);
    }

    /**
     * Calculate Pag-IBIG contribution using GovernmentRate settings from /settings dialog
     * Falls back to 2024 hardcoded formula if settings not configured
     *
     * @param float $monthlyBasicSalary  Employee's monthly basic salary (NOT gross pay).
     *                                   HDMF contribution is based on monthly basic compensation.
     */
    private function calculatePagibig($monthlyBasicSalary, $date = null)
    {
        // Use monthly basic salary directly for Pag-IBIG contribution computation
        $monthlyGross = $monthlyBasicSalary;

        // Try to get from settings dialog configuration
        $contribution = GovernmentRate::getContributionForSalary('pagibig', $monthlyGross, $date);

        if ($contribution && $contribution['employee'] > 0) {
            // Found in settings - use monthly amount, divide by 2 for semi-monthly
            return round($contribution['employee'] / 2, 2);
        }

        // FALLBACK: 2024 Pag-IBIG hardcoded formula if settings not configured
        $contribution = $monthlyGross * 0.02;
        $semiMonthlyContribution = $contribution / 2;

        // Per HDMF 2024 rules:
        // Maximum monthly employee contribution = ₱200 → semi-monthly = ₱100
        // Minimum semi-monthly = ₱25 (₱50/month floor)
        return round(min(max($semiMonthlyContribution, 25), 100), 2);
    }

    /**
     * Calculate withholding tax using dynamic GovernmentRate tax brackets.
     *
     * The tax table remains fully managed from the Government Rates UI.
     * If no active tax bracket matches, tax is treated as zero.
     */
    private function calculateWithholdingTax(float $taxableIncome, $date = null, bool $isSemiMonthly = true): float
    {
        return GovernmentRate::calculateTaxForIncome($taxableIncome, $date, $isSemiMonthly);
    }
}
