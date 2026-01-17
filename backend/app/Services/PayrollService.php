<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeLoan;
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
     * Process payroll for specific employees
     */
    public function processPayroll(Payroll $payroll, array $employeeIds = null)
    {
        DB::beginTransaction();
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

            DB::commit();
            return $payroll;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing payroll: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate payroll item for a specific employee
     */
    private function calculatePayrollItem(Payroll $payroll, Employee $employee)
    {
        // Get attendance records for the period
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
            ->where('status', '!=', 'absent')
            ->get();

        // Calculate days worked and hours
        $daysWorked = $attendances->where('status', 'present')->count();
        $regularOtHours = $attendances->sum('overtime_hours') ?? 0;
        
        // Get employee's effective rate (uses custom_pay_rate if set, otherwise position rate or basic_salary)
        $rate = $employee->getBasicSalary();
        
        // Calculate basic pay
        $basicPay = $rate * $daysWorked;
        
        // Calculate overtime pay (1.25x for regular OT)
        $hourlyRate = $rate / 8; // Assuming 8-hour workday
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
        $cola = $daysWorked * 0; // Set to 0, can be configured
        
        // Calculate gross pay
        $grossPay = $basicPay + $regularOtPay + $cola + $allowances;
        
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
            'basic_rate' => $rate,
            'days_worked' => $daysWorked,
            'basic_pay' => $basicPay,
            'overtime_hours' => $regularOtHours,
            'overtime_pay' => $regularOtPay,
            'holiday_pay' => 0,
            'night_differential' => 0,
            'adjustments' => 0,
            'adjustment_notes' => null,
            'water_allowance' => 0,
            'cola' => $cola,
            'other_allowances' => $allowances,
            'total_allowances' => $allowances + $cola,
            'total_bonuses' => 0,
            'gross_pay' => $grossPay,
            'sss_contribution' => $sss,
            'philhealth_contribution' => $philhealth,
            'pagibig_contribution' => $pagibig,
            'withholding_tax' => 0,
            'total_other_deductions' => $employeeSavings + $cashAdvance + $otherDeductions,
            'total_loan_deductions' => $loanDeduction,
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
        ];
    }

    /**
     * Calculate SSS contribution
     */
    private function calculateSSS($grossPay)
    {
        // Simplified SSS calculation - 2024 rates
        if ($grossPay < 4250) return 180;
        if ($grossPay < 4750) return 202.50;
        if ($grossPay < 5250) return 225;
        if ($grossPay < 5750) return 247.50;
        if ($grossPay < 6250) return 270;
        if ($grossPay < 6750) return 292.50;
        if ($grossPay < 7250) return 315;
        if ($grossPay < 7750) return 337.50;
        if ($grossPay < 8250) return 360;
        if ($grossPay < 8750) return 382.50;
        if ($grossPay < 9250) return 405;
        if ($grossPay < 9750) return 427.50;
        if ($grossPay < 10250) return 450;
        if ($grossPay < 10750) return 472.50;
        if ($grossPay < 11250) return 495;
        if ($grossPay < 11750) return 517.50;
        if ($grossPay < 12250) return 540;
        if ($grossPay < 12750) return 562.50;
        if ($grossPay < 13250) return 585;
        if ($grossPay < 13750) return 607.50;
        if ($grossPay < 14250) return 630;
        if ($grossPay < 14750) return 652.50;
        if ($grossPay < 15250) return 675;
        if ($grossPay < 15750) return 697.50;
        if ($grossPay < 16250) return 720;
        if ($grossPay < 16750) return 742.50;
        if ($grossPay < 17250) return 765;
        if ($grossPay < 17750) return 787.50;
        if ($grossPay < 18250) return 810;
        if ($grossPay < 18750) return 832.50;
        if ($grossPay < 19250) return 855;
        if ($grossPay < 19750) return 877.50;
        return 900; // Maximum
    }

    /**
     * Calculate PhilHealth contribution
     */
    private function calculatePhilHealth($grossPay)
    {
        // PhilHealth 2024: 4% of basic salary (2% employee share)
        $contribution = $grossPay * 0.04;
        $employeeShare = $contribution / 2;
        
        // Minimum: PHP 450, Maximum: PHP 1,800 per month
        return min(max($employeeShare, 450), 1800);
    }

    /**
     * Calculate Pag-IBIG contribution
     */
    private function calculatePagibig($grossPay)
    {
        // Pag-IBIG: 2% of monthly salary
        $contribution = $grossPay * 0.02;
        
        // Maximum of PHP 100 per month
        return min($contribution, 100);
    }
}
