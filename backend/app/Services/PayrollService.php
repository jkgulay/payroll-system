<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollItemDetail;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeLoan;
use App\Models\EmployeeDeduction;
use App\Services\Government\SSSComputationService;
use App\Services\Government\PhilHealthComputationService;
use App\Services\Government\PagIbigComputationService;
use App\Services\Government\TaxComputationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    protected $sssService;
    protected $philHealthService;
    protected $pagIbigService;
    protected $taxService;

    public function __construct(
        SSSComputationService $sssService,
        PhilHealthComputationService $philHealthService,
        PagIbigComputationService $pagIbigService,
        TaxComputationService $taxService
    ) {
        $this->sssService = $sssService;
        $this->philHealthService = $philHealthService;
        $this->pagIbigService = $pagIbigService;
        $this->taxService = $taxService;
    }

    /**
     * Create a new payroll period
     */
    public function createPayroll(array $data): Payroll
    {
        $payrollNumber = $this->generatePayrollNumber($data['period_start_date']);

        return Payroll::create([
            'payroll_number' => $payrollNumber,
            'period_start_date' => $data['period_start_date'],
            'period_end_date' => $data['period_end_date'],
            'payment_date' => $data['payment_date'],
            'pay_period_number' => $data['pay_period_number'] ?? $this->determinePeriodNumber($data['period_start_date']),
            'status' => 'draft',
            'prepared_by' => auth()->id(),
            'prepared_at' => now(),
        ]);
    }

    /**
     * Process payroll for all active employees
     */
    public function processPayroll(Payroll $payroll, ?array $employeeIds = null): void
    {
        DB::beginTransaction();
        try {
            $query = Employee::active()->with([
                'governmentInfo',
                'allowances' => function($q) use ($payroll) {
                    $q->active()->where('effective_date', '<=', $payroll->period_end_date);
                },
                'loans' => function($q) {
                    $q->active();
                },
                'deductions' => function($q) {
                    $q->active();
                }
            ]);

            if ($employeeIds) {
                $query->whereIn('id', $employeeIds);
            }

            $employees = $query->get();

            foreach ($employees as $employee) {
                $this->processEmployeePayroll($payroll, $employee);
            }

            $payroll->update(['status' => 'processing']);
            $payroll->calculateTotals();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payroll processing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process individual employee payroll
     */
    protected function processEmployeePayroll(Payroll $payroll, Employee $employee): PayrollItem
    {
        // Get attendance records for the period
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$payroll->period_start_date, $payroll->period_end_date])
            ->where('status', 'present')
            ->get();

        // Calculate earnings
        $earnings = $this->calculateEarnings($employee, $attendance, $payroll);
        
        // Get allowances
        $allowances = $this->calculateAllowances($employee, $payroll);
        
        // Calculate gross pay
        $grossPay = $earnings['basic_pay'] 
                  + $earnings['overtime_pay'] 
                  + $earnings['holiday_pay'] 
                  + $earnings['night_differential']
                  + $allowances['water_allowance']
                  + $allowances['cola']
                  + $allowances['other_allowances'];

        // Calculate government contributions
        $monthlyBasicSalary = $employee->getMonthlyRate();
        $contributions = [
            'sss' => $this->sssService->computeContribution($monthlyBasicSalary),
            'philhealth' => $this->philHealthService->computeContribution($monthlyBasicSalary),
            'pagibig' => $this->pagIbigService->computeContribution($monthlyBasicSalary),
        ];

        // Semi-monthly government contributions (divide by 2)
        $sssContribution = $contributions['sss']['employee_share'] / 2;
        $philhealthContribution = $contributions['philhealth']['employee_share'] / 2;
        $pagibigContribution = $contributions['pagibig']['employee_share'] / 2;

        // Calculate taxable income for semi-monthly period
        $taxableIncome = $grossPay 
                       - $sssContribution 
                       - $philhealthContribution 
                       - $pagibigContribution
                       - $allowances['non_taxable_allowances'];

        $withholdingTax = $this->taxService->computeTax($taxableIncome, 'semi-monthly');

        // Get loan deductions
        $loanDeductions = $this->calculateLoanDeductions($employee, $payroll);
        
        // Get other deductions
        $otherDeductions = $this->calculateOtherDeductions($employee, $payroll);

        // Calculate totals
        $totalDeductions = $sssContribution 
                         + $philhealthContribution 
                         + $pagibigContribution 
                         + $withholdingTax
                         + $loanDeductions['sss_loan']
                         + $loanDeductions['pagibig_loan']
                         + $loanDeductions['other_loans']
                         + $otherDeductions['ppe_deduction']
                         + $otherDeductions['other_deductions'];

        $netPay = $grossPay - $totalDeductions;

        // Create payroll item
        $payrollItem = PayrollItem::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'basic_pay' => $earnings['basic_pay'],
            'overtime_pay' => $earnings['overtime_pay'],
            'holiday_pay' => $earnings['holiday_pay'],
            'night_differential' => $earnings['night_differential'],
            'water_allowance' => $allowances['water_allowance'],
            'cola' => $allowances['cola'],
            'other_allowances' => $allowances['other_allowances'],
            'other_earnings' => 0,
            'gross_pay' => $grossPay,
            'sss_contribution' => $sssContribution,
            'philhealth_contribution' => $philhealthContribution,
            'pagibig_contribution' => $pagibigContribution,
            'withholding_tax' => $withholdingTax,
            'sss_loan' => $loanDeductions['sss_loan'],
            'pagibig_loan' => $loanDeductions['pagibig_loan'],
            'other_loans' => $loanDeductions['other_loans'],
            'ppe_deduction' => $otherDeductions['ppe_deduction'],
            'other_deductions' => $otherDeductions['other_deductions'],
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
            'days_worked' => $attendance->where('status', 'present')->count(),
            'regular_hours' => $attendance->sum('regular_hours'),
            'overtime_hours' => $attendance->sum('overtime_hours'),
            'night_differential_hours' => $attendance->sum('night_differential_hours'),
        ]);

        // Create detailed breakdown
        $this->createPayrollDetails($payrollItem, $earnings, $allowances, $contributions, $loanDeductions, $otherDeductions);

        return $payrollItem;
    }

    /**
     * Calculate employee earnings
     */
    protected function calculateEarnings(Employee $employee, $attendance, Payroll $payroll): array
    {
        $basicPay = 0;
        $overtimePay = 0;
        $holidayPay = 0;
        $nightDifferential = 0;

        $hourlyRate = $employee->getHourlyRate();

        foreach ($attendance as $record) {
            // Basic pay
            if ($employee->salary_type === 'daily') {
                $basicPay += $employee->basic_salary * ($record->regular_hours / 8);
            } else {
                $basicPay += $hourlyRate * $record->regular_hours;
            }

            // Overtime pay
            if ($record->overtime_hours > 0) {
                $overtimeRate = $this->getOvertimeRate($record);
                $overtimePay += $hourlyRate * $record->overtime_hours * $overtimeRate;
            }

            // Holiday pay
            if ($record->is_holiday) {
                $holidayRate = $record->holiday_type === 'regular' ? 2.0 : 1.3;
                if ($record->overtime_hours > 0) {
                    $holidayRate = $record->holiday_type === 'regular' ? 2.6 : 1.69;
                }
                $holidayPay += $hourlyRate * $record->regular_hours * ($holidayRate - 1);
            }

            // Night differential (10% of hourly rate)
            if ($record->night_differential_hours > 0) {
                $nightDifferential += $hourlyRate * $record->night_differential_hours * 0.10;
            }
        }

        return [
            'basic_pay' => round($basicPay, 2),
            'overtime_pay' => round($overtimePay, 2),
            'holiday_pay' => round($holidayPay, 2),
            'night_differential' => round($nightDifferential, 2),
        ];
    }

    /**
     * Get overtime rate multiplier
     */
    protected function getOvertimeRate($attendance): float
    {
        if ($attendance->is_holiday) {
            return $attendance->holiday_type === 'regular' ? 2.6 : 1.69;
        }
        // Check if rest day (Sunday or designated rest day)
        $dayOfWeek = Carbon::parse($attendance->attendance_date)->dayOfWeek;
        if ($dayOfWeek == 0) { // Sunday
            return 1.69;
        }
        return 1.25; // Regular overtime
    }

    /**
     * Calculate allowances
     */
    protected function calculateAllowances(Employee $employee, Payroll $payroll): array
    {
        $waterAllowance = 0;
        $cola = 0;
        $otherAllowances = 0;
        $nonTaxableAllowances = 0;

        foreach ($employee->allowances as $allowance) {
            if (!$allowance->is_active) continue;

            $amount = $this->calculateAllowanceAmount($allowance, $payroll);

            switch ($allowance->allowance_type) {
                case 'water':
                    $waterAllowance += $amount;
                    if (!$allowance->is_taxable) $nonTaxableAllowances += $amount;
                    break;
                case 'cola':
                    $cola += $amount;
                    if (!$allowance->is_taxable) $nonTaxableAllowances += $amount;
                    break;
                default:
                    $otherAllowances += $amount;
                    if (!$allowance->is_taxable) $nonTaxableAllowances += $amount;
                    break;
            }
        }

        return [
            'water_allowance' => round($waterAllowance, 2),
            'cola' => round($cola, 2),
            'other_allowances' => round($otherAllowances, 2),
            'non_taxable_allowances' => round($nonTaxableAllowances, 2),
        ];
    }

    /**
     * Calculate allowance amount based on frequency
     */
    protected function calculateAllowanceAmount($allowance, Payroll $payroll): float
    {
        switch ($allowance->frequency) {
            case 'daily':
                // Calculate days in period
                $days = Carbon::parse($payroll->period_start_date)->diffInDays($payroll->period_end_date) + 1;
                return $allowance->amount * $days;
            case 'weekly':
                return $allowance->amount / 2; // Semi-monthly approximation
            case 'semi-monthly':
                return $allowance->amount;
            case 'monthly':
                return $allowance->amount / 2;
            default:
                return $allowance->amount;
        }
    }

    /**
     * Calculate loan deductions
     */
    protected function calculateLoanDeductions(Employee $employee, Payroll $payroll): array
    {
        $sssLoan = 0;
        $pagibigLoan = 0;
        $otherLoans = 0;

        foreach ($employee->loans as $loan) {
            if ($loan->status !== 'active' || $loan->balance <= 0) continue;

            $deduction = min($loan->monthly_amortization / 2, $loan->balance);

            switch ($loan->loan_type) {
                case 'sss':
                    $sssLoan += $deduction;
                    break;
                case 'pagibig':
                    $pagibigLoan += $deduction;
                    break;
                default:
                    $otherLoans += $deduction;
                    break;
            }

            // Record loan payment (will be created after payroll approval)
            // This is tracked in the payroll details
        }

        return [
            'sss_loan' => round($sssLoan, 2),
            'pagibig_loan' => round($pagibigLoan, 2),
            'other_loans' => round($otherLoans, 2),
        ];
    }

    /**
     * Calculate other deductions
     */
    protected function calculateOtherDeductions(Employee $employee, Payroll $payroll): array
    {
        $ppeDeduction = 0;
        $otherDeductions = 0;

        foreach ($employee->deductions as $deduction) {
            if ($deduction->status !== 'active') continue;

            if ($deduction->deduction_type === 'ppe') {
                $ppeDeduction += $deduction->amount_per_cutoff;
            } else {
                $otherDeductions += $deduction->amount_per_cutoff;
            }
        }

        return [
            'ppe_deduction' => round($ppeDeduction, 2),
            'other_deductions' => round($otherDeductions, 2),
        ];
    }

    /**
     * Create detailed payroll breakdown
     */
    protected function createPayrollDetails(PayrollItem $payrollItem, array $earnings, array $allowances, array $contributions, array $loanDeductions, array $otherDeductions): void
    {
        // Earnings
        $details = [
            ['type' => 'earning', 'category' => 'basic_pay', 'description' => 'Basic Pay', 'amount' => $earnings['basic_pay']],
            ['type' => 'earning', 'category' => 'overtime', 'description' => 'Overtime Pay', 'amount' => $earnings['overtime_pay']],
            ['type' => 'earning', 'category' => 'holiday', 'description' => 'Holiday Pay', 'amount' => $earnings['holiday_pay']],
            ['type' => 'earning', 'category' => 'night_differential', 'description' => 'Night Differential', 'amount' => $earnings['night_differential']],
            ['type' => 'earning', 'category' => 'allowance', 'description' => 'Water Allowance', 'amount' => $allowances['water_allowance']],
            ['type' => 'earning', 'category' => 'allowance', 'description' => 'COLA', 'amount' => $allowances['cola']],
            ['type' => 'earning', 'category' => 'allowance', 'description' => 'Other Allowances', 'amount' => $allowances['other_allowances']],
            
            // Deductions
            ['type' => 'deduction', 'category' => 'government', 'description' => 'SSS Contribution', 'amount' => $contributions['sss']['employee_share'] / 2],
            ['type' => 'deduction', 'category' => 'government', 'description' => 'PhilHealth Contribution', 'amount' => $contributions['philhealth']['employee_share'] / 2],
            ['type' => 'deduction', 'category' => 'government', 'description' => 'Pag-IBIG Contribution', 'amount' => $contributions['pagibig']['employee_share'] / 2],
            ['type' => 'deduction', 'category' => 'tax', 'description' => 'Withholding Tax', 'amount' => $payrollItem->withholding_tax],
            ['type' => 'deduction', 'category' => 'loan', 'description' => 'SSS Loan', 'amount' => $loanDeductions['sss_loan']],
            ['type' => 'deduction', 'category' => 'loan', 'description' => 'Pag-IBIG Loan', 'amount' => $loanDeductions['pagibig_loan']],
            ['type' => 'deduction', 'category' => 'loan', 'description' => 'Other Loans', 'amount' => $loanDeductions['other_loans']],
            ['type' => 'deduction', 'category' => 'other', 'description' => 'PPE Deduction', 'amount' => $otherDeductions['ppe_deduction']],
            ['type' => 'deduction', 'category' => 'other', 'description' => 'Other Deductions', 'amount' => $otherDeductions['other_deductions']],
        ];

        foreach ($details as $detail) {
            if ($detail['amount'] > 0) {
                PayrollItemDetail::create([
                    'payroll_item_id' => $payrollItem->id,
                    'type' => $detail['type'],
                    'category' => $detail['category'],
                    'description' => $detail['description'],
                    'amount' => $detail['amount'],
                ]);
            }
        }
    }

    /**
     * Workflow methods
     */
    public function checkPayroll(Payroll $payroll, int $userId): bool
    {
        if (!$payroll->canCheck()) {
            return false;
        }

        $payroll->update([
            'status' => 'checked',
            'checked_by' => $userId,
            'checked_at' => now(),
        ]);

        return true;
    }

    public function recommendPayroll(Payroll $payroll, int $userId): bool
    {
        if (!$payroll->canRecommend()) {
            return false;
        }

        $payroll->update([
            'status' => 'recommended',
            'recommended_by' => $userId,
            'recommended_at' => now(),
        ]);

        return true;
    }

    public function approvePayroll(Payroll $payroll, int $userId): bool
    {
        if (!$payroll->canApprove()) {
            return false;
        }

        $payroll->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return true;
    }

    public function markAsPaid(Payroll $payroll, int $userId): bool
    {
        if (!$payroll->canMarkAsPaid()) {
            return false;
        }

        DB::beginTransaction();
        try {
            $payroll->update([
                'status' => 'paid',
                'paid_by' => $userId,
                'paid_at' => now(),
            ]);

            // Update loan balances
            $this->updateLoanPayments($payroll);

            // Update deduction installments
            $this->updateDeductionInstallments($payroll);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark payroll as paid: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper methods
     */
    protected function generatePayrollNumber(string $periodStart): string
    {
        $date = Carbon::parse($periodStart);
        $prefix = 'PR';
        $yearMonth = $date->format('Ym');
        $sequence = str_pad(Payroll::whereYear('period_start_date', $date->year)
                                   ->whereMonth('period_start_date', $date->month)
                                   ->count() + 1, 2, '0', STR_PAD_LEFT);
        
        return "{$prefix}{$yearMonth}{$sequence}";
    }

    protected function determinePeriodNumber(string $periodStart): int
    {
        $day = Carbon::parse($periodStart)->day;
        return $day <= 15 ? 1 : 2;
    }

    protected function updateLoanPayments(Payroll $payroll): void
    {
        // Implementation to record loan payments and update balances
        // This creates LoanPayment records for each loan deduction
    }

    protected function updateDeductionInstallments(Payroll $payroll): void
    {
        // Implementation to update deduction installment counts
    }
}
