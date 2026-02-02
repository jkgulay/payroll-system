<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeAllowance;
use App\Models\MealAllowanceItem;
use App\Models\EmployeeLoan;
use App\Models\EmployeeDeduction;
use App\Models\Holiday;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\CompanySettingService;

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
     */
    public function reprocessPayroll(Payroll $payroll, ?array $employeeIds = null)
    {
        DB::beginTransaction();
        try {
            // Delete existing payroll items
            PayrollItem::where('payroll_id', $payroll->id)->delete();

            // Process payroll again
            $this->processPayroll($payroll, $employeeIds);

            DB::commit();
            return $payroll->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error reprocessing payroll: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process payroll for specific employees
     */
    public function processPayroll(Payroll $payroll, ?array $employeeIds = null)
    {
        try {
            // If no employee IDs provided, process employees who were working during the payroll period
            if (empty($employeeIds)) {
                $employees = Employee::where(function ($q) use ($payroll) {
                    $q->whereIn('activity_status', ['active', 'on_leave'])
                        ->orWhereHas('attendances', function ($subQ) use ($payroll) {
                            $subQ->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
                                ->where('status', '!=', 'absent');
                        });
                })->get();
            } else {
                $employees = Employee::whereIn('id', $employeeIds)
                    ->where(function ($q) use ($payroll) {
                        $q->whereIn('activity_status', ['active', 'on_leave'])
                            ->orWhereHas('attendances', function ($subQ) use ($payroll) {
                                $subQ->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
                                    ->where('status', '!=', 'absent');
                            });
                    })
                    ->get();
            }

            $totalGross = 0;
            $totalDeductions = 0;
            $totalNet = 0;

            foreach ($employees as $employee) {
                $item = $this->calculatePayrollItem($payroll, $employee);

                PayrollItem::create($item);

                $totalGross += $item['gross_pay'];
                $totalDeductions += $item['total_deductions'];
                $totalNet += $item['net_pay'];
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
     * Calculate payroll item for a specific employee
     */
    public function calculatePayrollItem(Payroll $payroll, Employee $employee)
    {
        // Get attendance records for the period
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
            ->where('status', '!=', 'absent')
            ->get();

        // Calculate days worked and hours
        $regularDays = 0;
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
        $hourlyRate = $rate / 8; // Assuming 8-hour workday

        // Process each attendance record
        foreach ($attendances as $attendance) {
            $attendanceDate = Carbon::parse($attendance->attendance_date);

            // Accumulate undertime hours
            if ($attendance->undertime_hours > 0) {
                $totalUndertimeHours += $attendance->undertime_hours;
            }

            // Check if this date is Sunday (0 = Sunday in Carbon)
            $isSunday = $attendanceDate->dayOfWeek === 0;

            // Check if this date is a holiday
            $holiday = Holiday::getHolidayForDate($attendanceDate);

            if ($holiday && ($attendance->status === 'present' || $attendance->status === 'half_day' || $attendance->status === 'late')) {
                // This is a holiday with attendance - calculate holiday pay
                $hoursWorked = $attendance->regular_hours ?? 8; // Default to 8 hours if not specified
                $payMultiplier = $holiday->getPayMultiplier($attendanceDate);
                $dayHolidayPay = $rate * $payMultiplier * ($hoursWorked / 8);

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
                    $regularDays++;
                } elseif ($attendance->status === 'half_day') {
                    $regularDays += 0.5; // Half day counts as 0.5
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

        // Calculate basic pay (excluding holiday days)
        $basicPay = $rate * $regularDays;

        // Calculate overtime pay with different rates
        // Regular days: rate/8 × 1.25 × hours
        $regularOtMultiplier = (float) $this->settings->get(
            'payroll.overtime.regularDay',
            config('payroll.overtime.regular_multiplier', 1.25)
        );
        $regularOtPay = $regularOtHours * $hourlyRate * $regularOtMultiplier;

        // Sunday: rate/8 × 1.3 × hours
        $sundayOtMultiplier = (float) $this->settings->get('payroll.overtime.sunday', 1.3);
        $sundayOtPay = $sundayOtHours * $hourlyRate * $sundayOtMultiplier;

        // Regular holiday (weekday): rate/8 × 2 × 1.3 × hours
        $regularHolidayMultiplier = (float) $this->settings->get(
            'payroll.holidays.regularHoliday',
            1.3
        );
        $regularHolidayOtPay = $regularHolidayOtHours * $hourlyRate * 2 * $regularHolidayMultiplier;

        // Regular holiday on Sunday: rate/8 × 1.3 × 1.3 × hours
        $regularHolidaySundayMultiplier = (float) $this->settings->get(
            'payroll.holidays.regularHolidaySunday',
            1.3
        );
        $regularHolidaySundayOtPay = $regularHolidaySundayOtHours * $hourlyRate * 1.3 * $regularHolidaySundayMultiplier;

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

        // Get allowances - sum allowances that are active and effective during the payroll period
        $activeAllowances = EmployeeAllowance::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('effective_date', '<=', $payroll->period_end)
            ->where(function ($query) use ($payroll) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $payroll->period_start);
            })
            ->get();

        $allowances = $activeAllowances->sum('amount') ?? 0;

        $allowancesBreakdown = $activeAllowances->map(function ($allowance) {
            return [
                'id' => $allowance->id,
                'type' => $allowance->allowance_type,
                'name' => $allowance->allowance_name ?: $allowance->allowance_type,
                'amount' => (float) $allowance->amount,
            ];
        })->values()->all();

        // Include approved meal allowances within the payroll period
        $mealAllowanceTotal = MealAllowanceItem::where('employee_id', $employee->id)
            ->whereHas('mealAllowance', function ($query) use ($payroll) {
                $query->where('status', 'approved')
                    ->where('period_start', '<=', $payroll->period_end)
                    ->where('period_end', '>=', $payroll->period_start);
            })
            ->sum('total_amount') ?? 0;

        if ($mealAllowanceTotal > 0) {
            $allowancesBreakdown[] = [
                'id' => null,
                'type' => 'meal_allowance',
                'name' => 'Allowance',
                'amount' => (float) $mealAllowanceTotal,
            ];
        }

        $otherAllowances = $allowances + $mealAllowanceTotal;

        // Calculate COLA (Cost of Living Allowance) - typically per day
        $totalDaysWorked = $regularDays + $holidayDays;
        $cola = $totalDaysWorked * 0; // Set to 0, can be configured

        // Calculate undertime deduction
        // Formula: (rate / 8) * undertime_hours
        $undertimeDeduction = $hourlyRate * $totalUndertimeHours;

        // Calculate gross pay (include holiday pay, overtime, subtract undertime deduction)
        $grossPay = $basicPay + $holidayPay + $totalOtPay + $cola + $otherAllowances - $undertimeDeduction;

        // Calculate government deductions (only if enabled for the employee)
        // Use custom contributions if set, otherwise calculate based on salary
        $sss = 0;
        $philhealth = 0;
        $pagibig = 0;

        if ($employee->has_sss) {
            // Use custom SSS if set (already semi-monthly amount), otherwise calculate
            $sss = $employee->custom_sss !== null
                ? (float) $employee->custom_sss
                : $this->calculateSSS($grossPay);
        }

        if ($employee->has_philhealth) {
            // Use custom PhilHealth if set (already semi-monthly amount), otherwise calculate
            $philhealth = $employee->custom_philhealth !== null
                ? (float) $employee->custom_philhealth
                : $this->calculatePhilHealth($grossPay);
        }

        if ($employee->has_pagibig) {
            // Use custom Pag-IBIG if set (already semi-monthly amount), otherwise calculate
            $pagibig = $employee->custom_pagibig !== null
                ? (float) $employee->custom_pagibig
                : $this->calculatePagibig($grossPay);
        }

        // Determine if this is semi-monthly payroll (typically 15 days or less)
        $periodStart = Carbon::parse($payroll->period_start);
        $periodEnd = Carbon::parse($payroll->period_end);
        $periodDays = $periodStart->diffInDays($periodEnd) + 1;
        $isSemiMonthly = $periodDays <= 16;

        // Get loans deduction for this period (all loan types combined)
        // Deduct from active loans that have balance remaining and haven't matured
        // Start deducting immediately once loan is active (ignore first_payment_date for deductions)
        $activeLoans = EmployeeLoan::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->where(function ($query) use ($payroll) {
                // Loan hasn't matured yet (maturity date is null or after payroll period starts)
                $query->whereNull('maturity_date')
                    ->orWhere('maturity_date', '>=', $payroll->period_start);
            })
            ->get();

        $loanDeduction = 0;
        foreach ($activeLoans as $loan) {
            // Determine payment amount based on payroll period and loan payment frequency
            if ($loan->payment_frequency === 'semi_monthly') {
                // Semi-monthly loans: use semi_monthly_amortization for semi-monthly payrolls
                // For monthly payrolls, use monthly_amortization (2 payments combined)
                $loanDeduction += $isSemiMonthly
                    ? ($loan->semi_monthly_amortization ?? 0)
                    : ($loan->monthly_amortization ?? 0);
            } else {
                // Monthly loans: only deduct on monthly payrolls
                if (!$isSemiMonthly) {
                    $loanDeduction += $loan->monthly_amortization ?? 0;
                }
            }
        }

        // Employee savings
        $employeeSavings = 0; // Can be configured

        // Cash advance
        $cashAdvance = 0; // Can be configured

        // Get active employee deductions for this period
        $activeDeductions = EmployeeDeduction::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->where('start_date', '<=', $payroll->period_end)
            ->where(function ($query) use ($payroll) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $payroll->period_start);
            })
            ->get();

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

        // Total deductions
        $totalDeductions = $sss + $philhealth + $pagibig + $loanDeduction +
            $employeeSavings + $cashAdvance + $employeeDeductions + $otherDeductions;

        // Net pay
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
            'cola' => $cola,
            'other_allowances' => $otherAllowances,
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
        ];
    }

    /**
     * Calculate SSS contribution
     */
    /**
     * Calculate SSS contribution based on 2025 SSS Contribution Table
     * Employee share: 4.5% of monthly salary credit
     */
    private function calculateSSS($grossPay)
    {
        // Convert semi-monthly to monthly salary credit for SSS calculation
        $monthlySalary = $grossPay * 2;

        // 2025 SSS Contribution Table - Employee Share (4.5%)
        // Using monthly salary credit brackets
        if ($monthlySalary < 4250) return 180.00;
        if ($monthlySalary < 4750) return 202.50;
        if ($monthlySalary < 5250) return 225.00;
        if ($monthlySalary < 5750) return 247.50;
        if ($monthlySalary < 6250) return 270.00;
        if ($monthlySalary < 6750) return 292.50;
        if ($monthlySalary < 7250) return 315.00;
        if ($monthlySalary < 7750) return 337.50;
        if ($monthlySalary < 8250) return 360.00;
        if ($monthlySalary < 8750) return 382.50;
        if ($monthlySalary < 9250) return 405.00;
        if ($monthlySalary < 9750) return 427.50;
        if ($monthlySalary < 10250) return 450.00;
        if ($monthlySalary < 10750) return 472.50;
        if ($monthlySalary < 11250) return 495.00;
        if ($monthlySalary < 11750) return 517.50;
        if ($monthlySalary < 12250) return 540.00;
        if ($monthlySalary < 12750) return 562.50;
        if ($monthlySalary < 13250) return 585.00;
        if ($monthlySalary < 13750) return 607.50;
        if ($monthlySalary < 14250) return 630.00;
        if ($monthlySalary < 14750) return 652.50;
        if ($monthlySalary < 15250) return 675.00;
        if ($monthlySalary < 15750) return 697.50;
        if ($monthlySalary < 16250) return 720.00;
        if ($monthlySalary < 16750) return 742.50;
        if ($monthlySalary < 17250) return 765.00;
        if ($monthlySalary < 17750) return 787.50;
        if ($monthlySalary < 18250) return 810.00;
        if ($monthlySalary < 18750) return 832.50;
        if ($monthlySalary < 19250) return 855.00;
        if ($monthlySalary < 19750) return 877.50;
        if ($monthlySalary < 20250) return 900.00;
        if ($monthlySalary < 20750) return 922.50;
        if ($monthlySalary < 21250) return 945.00;
        if ($monthlySalary < 21750) return 967.50;
        if ($monthlySalary < 22250) return 990.00;
        if ($monthlySalary < 22750) return 1012.50;
        if ($monthlySalary < 23250) return 1035.00;
        if ($monthlySalary < 23750) return 1057.50;
        if ($monthlySalary < 24250) return 1080.00;
        if ($monthlySalary < 24750) return 1102.50;
        if ($monthlySalary >= 25000) return 1125.00; // Maximum employee share

        return 1125.00; // Maximum
    }

    /**
     * Calculate PhilHealth contribution
     * 2024-2026 rates: 4.5% of basic salary (2.25% employee share)
     */
    private function calculatePhilHealth($grossPay)
    {
        // PhilHealth 2024-2026: 4.5% of monthly salary (2.25% employee share)
        // Semi-monthly computation: divide by 2 for twice-a-month payroll
        $monthlyGross = $grossPay * 2; // Assuming semi-monthly payroll
        $contribution = $monthlyGross * 0.045;
        $employeeShare = $contribution / 2;

        // For semi-monthly, divide by 2
        $semiMonthlyShare = $employeeShare / 2;

        // Minimum: ₱225, Maximum: ₱1,800 per month (₱900 semi-monthly)
        return round(min(max($semiMonthlyShare, 225 / 2), 900), 2);
    }

    /**
     * Calculate Pag-IBIG contribution
     * 2024 rates: 2% of monthly salary (1% employee, 1% employer)
     */
    private function calculatePagibig($grossPay)
    {
        // Pag-IBIG: Employee share is 2% of monthly salary
        // Semi-monthly computation
        $monthlyGross = $grossPay * 2; // Assuming semi-monthly payroll
        $contribution = $monthlyGross * 0.02;
        $semiMonthlyContribution = $contribution / 2;

        // Minimum: ₱50/month (₱25 semi-monthly), Maximum: ₱200/month (₱100 semi-monthly)
        return round(min(max($semiMonthlyContribution, 25), 100), 2);
    }
}
