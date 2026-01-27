<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeLoan;
use App\Models\Holiday;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PayrollService
{
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
            // If no employee IDs provided, process all active employees
            if (empty($employeeIds)) {
                $employees = Employee::where('activity_status', 'active')->get();
            } else {
                $employees = Employee::whereIn('id', $employeeIds)
                    ->where('activity_status', 'active')
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

            // Check if this date is a holiday
            $holiday = Holiday::getHolidayForDate($attendanceDate);

            if ($holiday && $attendance->status === 'present') {
                // This is a holiday with attendance - calculate holiday pay
                $hoursWorked = $attendance->regular_hours ?? 8; // Default to 8 hours if not specified
                $payMultiplier = $holiday->getPayMultiplier($attendanceDate);
                $dayHolidayPay = $rate * $payMultiplier * ($hoursWorked / 8);

                // Calculate holiday pay for the hours worked
                $holidayPay += $dayHolidayPay;
                $holidayDays++;

                // Add overtime if any on holiday (should be calculated with higher rate)
                if ($attendance->overtime_hours > 0) {
                    $regularOtHours += $attendance->overtime_hours;
                }
            } else {
                // Regular working day
                if ($attendance->status === 'present') {
                    $regularDays++;
                }

                // Add regular overtime
                if ($attendance->overtime_hours > 0) {
                    $regularOtHours += $attendance->overtime_hours;
                }
            }
        }

        // Calculate basic pay (excluding holiday days)
        $basicPay = $rate * $regularDays;

        // Calculate overtime pay (1.25x for regular OT)
        $regularOtPay = $regularOtHours * $hourlyRate * 1.25;

        // Get allowances - sum allowances that are active and effective during the payroll period
        $allowances = EmployeeAllowance::where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('effective_date', '<=', $payroll->period_end)
            ->where(function ($query) use ($payroll) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $payroll->period_start);
            })
            ->sum('amount') ?? 0;

        // Calculate COLA (Cost of Living Allowance) - typically per day
        $totalDaysWorked = $regularDays + $holidayDays;
        $cola = $totalDaysWorked * 0; // Set to 0, can be configured

        // Calculate undertime deduction
        // Formula: (rate / 8) * undertime_hours
        $undertimeDeduction = $hourlyRate * $totalUndertimeHours;

        // Calculate gross pay (include holiday pay, subtract undertime deduction)
        $grossPay = $basicPay + $holidayPay + $regularOtPay + $cola + $allowances - $undertimeDeduction;

        // Calculate government deductions
        $sss = $this->calculateSSS($grossPay);
        $philhealth = $this->calculatePhilHealth($grossPay);
        $pagibig = $this->calculatePagibig($grossPay);

        // Get loans deduction for this period
        $loanDeduction = EmployeeLoan::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->sum('monthly_amortization') ?? 0;

        // Employee savings
        $employeeSavings = 0; // Can be configured

        // Cash advance
        $cashAdvance = 0; // Can be configured

        // Other deductions
        $otherDeductions = 0;

        // Total deductions
        $totalDeductions = $sss + $philhealth + $pagibig + $loanDeduction +
            $employeeSavings + $cashAdvance + $otherDeductions;

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
            'special_ot_hours' => 0,
            'special_ot_pay' => 0,
            'cola' => $cola,
            'other_allowances' => $allowances,
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
