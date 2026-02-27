<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\MealAllowanceItem;
use App\Models\SalaryAdjustment;
use App\Models\Holiday;
use App\Models\GovernmentRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\CompanySettingService;
use Illuminate\Support\Facades\Cache;

class PayrollService
{
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
            $allAdjustmentIds = []; // Collect salary adjustment IDs to mark after successful insert
            $allBonusIds = []; // Collect bonus IDs to mark as paid after successful insert

            foreach ($employees as $employee) {
                $item = $this->calculatePayrollItem($payroll, $employee, $holidays);

                // Skip employees with ₱0 or negative gross pay
                // These employees have no complete attendance records, so they shouldn't be included in payroll
                if ($item['gross_pay'] <= 0) {
                    continue;
                }

                // Collect items instead of individual inserts
                $payrollItems[] = $item;

                // Collect adjustment IDs from this item
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
                // Remove internal tracking fields before creating
                unset($item['_adjustment_ids']);
                unset($item['_bonus_ids']);
                PayrollItem::create($item);
            }

            // Mark salary adjustments as applied AFTER successful inserts
            if (!empty($allAdjustmentIds)) {
                SalaryAdjustment::whereIn('id', $allAdjustmentIds)
                    ->update([
                        'status' => 'applied',
                        'applied_payroll_id' => $payroll->id,
                    ]);
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

        if (!empty($employeeIds)) {
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
                        ->where(function ($sub) {
                            // Include records with time_out OR half-day records
                            // (half-day entries may lack time_out but have valid regular_hours)
                            $sub->whereNotNull('time_out')
                                ->orWhere('status', 'half_day');
                        })
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
                // CRITERIA: is_active=true, effective_date <= period_end, 
                // (end_date IS NULL OR end_date >= period_start)
                'allowances' => function ($q) use ($payroll) {
                    $q->where('is_active', true)
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
                            'amount'
                        );
                },
                // Pending salary adjustments (only those effective within or before the pay period)
                'salaryAdjustments' => function ($q) use ($payroll) {
                    $q->where('status', 'pending')
                        ->where(function ($query) use ($payroll) {
                            $query->whereNull('effective_date')
                                ->orWhere('effective_date', '<=', $payroll->period_end);
                        })
                        ->select('id', 'employee_id', 'type', 'amount');
                },
                // Active loans with balance
                'loans' => function ($q) use ($payroll) {
                    $q->where('status', 'active')
                        ->where('balance', '>', 0)
                        ->where(function ($query) use ($payroll) {
                            $query->whereNull('maturity_date')
                                ->orWhere('maturity_date', '>=', $payroll->period_start);
                        })
                        ->select(
                            'id',
                            'employee_id',
                            'payment_frequency',
                            'semi_monthly_amortization',
                            'monthly_amortization',
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
                // Meal allowance items (via approved MealAllowance within period)
                'mealAllowanceItems' => function ($q) use ($payroll) {
                    $q->whereHas('mealAllowance', function ($mq) use ($payroll) {
                        $mq->where('status', 'approved')
                            ->where('period_start', '<=', $payroll->period_end)
                            ->where('period_end', '>=', $payroll->period_start);
                    })->select('id', 'employee_id', 'total_amount');
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
        $cacheKey = "holidays:{$startDate}:{$endDate}";

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
    public function calculatePayrollItem(Payroll $payroll, Employee $employee, array $holidays = [])
    {
        // OPTIMIZATION: Use preloaded attendances (already eager loaded)
        $attendances = $employee->attendances;

        // Calculate days worked and hours
        $regularDays = 0;
        $sundayDays = 0; // Track Sunday regular days separately (paid at 1.3x)
        $holidayDays = 0;
        $regularOtHours = 0;
        $sundayOtHours = 0;
        $regularHolidayOtHours = 0; // Regular holiday OT (weekday)
        $regularHolidaySundayOtHours = 0; // Regular holiday OT on Sunday
        $specialHolidayOtHours = 0; // Special holiday OT
        $holidayPay = 0;
        $totalUndertimeHours = 0;

        // Get employee's effective rate (uses custom_pay_rate if set, otherwise position rate or basic_salary)
        $rate = $employee->getBasicSalary();
        // Monthly basic salary for government contribution table lookups (SSS/PhilHealth/Pag-IBIG).
        // Using getMonthlyRate() is correct — contributions are based on BASIC salary, NOT gross pay.
        // Gross pay includes OT, allowances, and holiday premiums which must NOT inflate MSC/PMB.
        $monthlyBasicSalary = $employee->getMonthlyRate();
        $standardHours = config('payroll.standard_hours_per_day', 8);
        $hourlyRate = $rate / $standardHours;

        // Process each attendance record
        foreach ($attendances as $attendance) {
            $attendanceDate = Carbon::parse($attendance->attendance_date);

            // Accumulate undertime hours
            if ($attendance->undertime_hours > 0) {
                $totalUndertimeHours += $attendance->undertime_hours;
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
                if ($attendance->overtime_hours > 0) {
                    if ($holiday->type === 'regular') {
                        // Regular holiday overtime
                        if ($isSunday) {
                            // Regular holiday on Sunday: rate/8 × 1.3 × 1.3 × hours
                            $regularHolidaySundayOtHours += $attendance->overtime_hours;
                        } else {
                            // Regular holiday weekday: rate/8 × 2 × 1.3 × hours
                            $regularHolidayOtHours += $attendance->overtime_hours;
                        }
                    } else {
                        // Special holiday: rate/8 × 1.3 × 1.3 × hours
                        $specialHolidayOtHours += $attendance->overtime_hours;
                    }
                }
            } else {
                // Regular working day or Sunday
                if ($attendance->status === 'present' || $attendance->status === 'late') {
                    if ($isSunday) {
                        $sundayDays++; // Sunday regular hours paid at 1.3x
                    } else {
                        $regularDays++;
                    }
                } elseif ($attendance->status === 'half_day') {
                    if ($isSunday) {
                        $sundayDays += 0.5; // Half day on Sunday
                    } else {
                        $regularDays += 0.5; // Half day counts as 0.5
                    }
                }

                // Add overtime - separate Sunday from regular days
                if ($attendance->overtime_hours > 0) {
                    if ($isSunday) {
                        $sundayOtHours += $attendance->overtime_hours;
                    } else {
                        $regularOtHours += $attendance->overtime_hours;
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

        // Calculate basic pay (excluding holiday days)
        // Weekday regular pay at 1.0x, Sunday regular pay at 1.3x
        $basicPay = ($rate * $regularDays) + ($rate * $sundayDays * 1.3);

        // Calculate overtime pay with different rates
        // Regular days: rate/8 × 1.25 × hours
        $regularOtMultiplier = (float) $this->settings->get(
            'payroll.overtime.regularDay',
            config('payroll.overtime.regular_multiplier', 1.25)
        );
        $regularOtPay = $regularOtHours * $hourlyRate * $regularOtMultiplier;

        // Sunday (rest day): rate/8 × 1.3 (rest day premium) × 1.3 (OT on rest day) × hours
        // DOLE Labor Code Art. 93 & 87: rest day OT = 30% on rest day rate = 130% × 130% = 169% of regular hourly rate
        $sundayOtMultiplier = (float) $this->settings->get('payroll.overtime.sunday', 1.3);
        $sundayOtPay = $sundayOtHours * $hourlyRate * 1.3 * $sundayOtMultiplier;

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

        // Total overtime pay
        $specialOtHours = $sundayOtHours + $regularHolidayOtHours + $regularHolidaySundayOtHours + $specialHolidayOtHours;
        $specialOtPay = $sundayOtPay + $regularHolidayOtPay + $regularHolidaySundayOtPay + $specialHolidayOtPay;
        $totalOtPay = $regularOtPay + $specialOtPay;

        // OPTIMIZATION: Use preloaded relationships (no queries)
        $activeAllowances = $employee->allowances;
        $allowances = $activeAllowances->sum('amount') ?? 0;

        $allowancesBreakdown = $activeAllowances->map(function ($allowance) {
            return [
                'id' => $allowance->id,
                'type' => $allowance->allowance_type,
                'name' => $allowance->allowance_name ?: $allowance->allowance_type,
                'amount' => (float) $allowance->amount,
            ];
        })->values()->all();

        // OPTIMIZATION: Use preloaded meal allowance items (no N+1 query)
        $mealAllowanceTotal = $employee->relationLoaded('mealAllowanceItems')
            ? $employee->mealAllowanceItems->sum('total_amount')
            : MealAllowanceItem::where('employee_id', $employee->id)
            ->whereHas('mealAllowance', function ($query) use ($payroll) {
                $query->where('status', 'approved')
                    ->where('period_start', '<=', $payroll->period_end)
                    ->where('period_end', '>=', $payroll->period_start);
            })
            ->sum('total_amount');
        $mealAllowanceTotal = (float) ($mealAllowanceTotal ?? 0);

        if ($mealAllowanceTotal > 0) {
            $allowancesBreakdown[] = [
                'id' => null,
                'type' => 'meal_allowance',
                'name' => 'Meal Allowance',
                'amount' => $mealAllowanceTotal,
            ];
        }

        // OPTIMIZATION: Use preloaded bonuses (pending bonuses with payment_date in period)
        $pendingBonuses = $employee->relationLoaded('bonuses')
            ? $employee->bonuses
            : collect();
        $bonusTotal = (float) $pendingBonuses->sum('amount');
        $bonusIds = [];

        foreach ($pendingBonuses as $bonus) {
            $allowancesBreakdown[] = [
                'id' => $bonus->id,
                'type' => 'bonus',
                'name' => $bonus->bonus_name ?: $bonus->bonus_type,
                'amount' => (float) $bonus->amount,
            ];
            $bonusIds[] = $bonus->id;
        }

        $otherAllowances = $allowances + $mealAllowanceTotal + $bonusTotal;

        // Calculate salary adjustment from pending adjustments (OPTIMIZED - preloaded)
        $totalDaysWorked = $regularDays + $sundayDays + $holidayDays;

        // OPTIMIZATION: Use preloaded salary adjustments
        $pendingAdjustments = $employee->salaryAdjustments;

        $salaryAdjustment = 0;
        $adjustmentIds = [];
        foreach ($pendingAdjustments as $adjustment) {
            // Deductions are negative, additions are positive
            if ($adjustment->type === 'deduction') {
                $salaryAdjustment -= abs($adjustment->amount);
            } else {
                $salaryAdjustment += abs($adjustment->amount);
            }
            $adjustmentIds[] = $adjustment->id;
        }

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
        $sss = 0;
        $philhealth = 0;
        $pagibig = 0;

        if ($grossPay > 0) {
            if ($employee->has_sss) {
                // Use custom SSS if set (already semi-monthly amount), otherwise calculate
                $sss = $employee->custom_sss !== null
                    ? (float) $employee->custom_sss
                    : $this->calculateSSS($monthlyBasicSalary);
            }

            if ($employee->has_philhealth) {
                // Use custom PhilHealth if set (already semi-monthly amount), otherwise calculate
                $philhealth = $employee->custom_philhealth !== null
                    ? (float) $employee->custom_philhealth
                    : $this->calculatePhilHealth($monthlyBasicSalary);
            }

            if ($employee->has_pagibig) {
                // Use custom Pag-IBIG if set (already semi-monthly amount), otherwise calculate
                $pagibig = $employee->custom_pagibig !== null
                    ? (float) $employee->custom_pagibig
                    : $this->calculatePagibig($monthlyBasicSalary);
            }
        }

        // Determine if this is semi-monthly payroll (typically 15 days or less)
        $periodStart = Carbon::parse($payroll->period_start);
        $periodEnd = Carbon::parse($payroll->period_end);
        $periodDays = $periodStart->diffInDays($periodEnd) + 1;
        $isSemiMonthly = $periodDays <= 16;

        // For monthly loans on semi-monthly payrolls: only deduct on 2nd cutoff
        // 2nd cutoff = period ending on 16th or later of the month
        $isSecondCutoff = !$isSemiMonthly || $periodEnd->day >= 16;

        // OPTIMIZATION: Use preloaded loans
        $activeLoans = $employee->loans;

        $loanDeduction = 0;
        foreach ($activeLoans as $loan) {
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
        $employeeSavings = 0; // Can be configured

        // Cash advance
        $cashAdvance = 0; // Can be configured

        // OPTIMIZATION: Use preloaded deductions
        $activeDeductions = $employee->deductions;

        $employeeDeductions = 0;
        $deductionsBreakdown = [];

        foreach ($activeDeductions as $deduction) {
            $amountPerCutoff = $deduction->amount_per_cutoff;
            if (!$amountPerCutoff && $deduction->installments > 0) {
                $amountPerCutoff = $deduction->total_amount / $deduction->installments;
            }

            $deductionAmount = min($amountPerCutoff, $deduction->balance);
            $employeeDeductions += $deductionAmount;

            // Store breakdown by deduction type and name
            $deductionsBreakdown[] = [
                'id' => $deduction->id,
                'type' => $deduction->deduction_type,
                'name' => $deduction->deduction_name,
                'amount' => $deductionAmount,
            ];
        }

        // Other deductions
        $otherDeductions = 0;

        // Total deductions (including undertime deduction)
        $totalDeductions = $sss + $philhealth + $pagibig + $loanDeduction +
            $employeeSavings + $cashAdvance + $employeeDeductions + $otherDeductions + $undertimeDeduction;

        // Net pay = Gross pay - Total deductions
        $netPay = $grossPay - $totalDeductions;

        return [
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'rate' => $rate,
            'days_worked' => $totalDaysWorked,
            'regular_days' => $regularDays,
            'holiday_days' => $holidayDays,
            'holiday_pay' => $holidayPay,
            'basic_pay' => $basicPay,
            'regular_ot_hours' => $regularOtHours,
            'regular_ot_pay' => $regularOtPay,
            'special_ot_hours' => $specialOtHours,
            'special_ot_pay' => $specialOtPay,
            'other_allowances' => $otherAllowances,
            'salary_adjustment' => $salaryAdjustment,
            'allowances_breakdown' => $allowancesBreakdown,
            'gross_pay' => $grossPay,
            'undertime_hours' => $totalUndertimeHours,
            'undertime_deduction' => $undertimeDeduction,
            'sss' => $sss,
            'philhealth' => $philhealth,
            'pagibig' => $pagibig,
            'withholding_tax' => 0,
            'employee_savings' => $employeeSavings,
            'cash_advance' => $cashAdvance,
            'loans' => $loanDeduction,
            'employee_deductions' => $employeeDeductions,
            'deductions_breakdown' => $deductionsBreakdown,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            '_adjustment_ids' => $adjustmentIds, // Internal: collected by processPayroll
            '_bonus_ids' => $bonusIds, // Internal: collected by processPayroll to mark bonuses as paid
        ];
    }

    /**
     * Calculate SSS contribution using GovernmentRate settings from /settings dialog
     * Falls back to 2025 hardcoded table if settings not configured
     *
     * @param float $monthlyBasicSalary  Employee's monthly basic salary (NOT gross pay).
     *                                   SSS looks up MSC (Monthly Salary Credit) from basic salary only.
     */
    private function calculateSSS($monthlyBasicSalary)
    {
        // Use monthly basic salary directly for SSS MSC table lookup
        $monthlySalary = $monthlyBasicSalary;

        // Try to get from settings dialog configuration
        $contribution = GovernmentRate::getContributionForSalary('sss', $monthlySalary);

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
    private function calculatePhilHealth($monthlyBasicSalary)
    {
        // Use monthly basic salary directly for PhilHealth premium computation
        $monthlyGross = $monthlyBasicSalary;

        // Try to get from settings dialog configuration
        $contribution = GovernmentRate::getContributionForSalary('philhealth', $monthlyGross);

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
    private function calculatePagibig($monthlyBasicSalary)
    {
        // Use monthly basic salary directly for Pag-IBIG contribution computation
        $monthlyGross = $monthlyBasicSalary;

        // Try to get from settings dialog configuration
        $contribution = GovernmentRate::getContributionForSalary('pagibig', $monthlyGross);

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
}
