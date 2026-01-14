<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollItemDetail;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeAllowance;
use App\Models\GovernmentRate;
use App\Models\EmployeeLoan;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeLeave;
use App\Models\LoanPayment;
use App\Models\Holiday;
use App\Services\Government\TaxComputationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    protected $taxService;

    public function __construct(
        TaxComputationService $taxService
    ) {
        $this->taxService = $taxService;
    }

    /**
     * Create a new payroll period
     */
    public function createPayroll(array $data): Payroll
    {
        $payrollNumber = $this->generatePayrollNumber($data['period_start_date']);
        $periodStart = Carbon::parse($data['period_start_date']);

        return Payroll::create([
            'payroll_number' => $payrollNumber,
            'period_type' => $data['period_type'] ?? 'semi_monthly', // Use provided value or default
            'period_start' => $data['period_start_date'],
            'period_end' => $data['period_end_date'],
            'payment_date' => $data['payment_date'],
            'pay_period_number' => $data['pay_period_number'] ?? $this->determinePeriodNumber($data['period_start_date']),
            'month' => $periodStart->month,
            'year' => $periodStart->year,
            'status' => 'draft',
            'prepared_by' => auth()->id(),
            'prepared_at' => now(),
        ]);
    }

    /**
     * Process payroll for all active employees
     */
    public function processPayroll(Payroll $payroll, ?array $employeeIds = null, ?array $filters = null): void
    {
        DB::beginTransaction();
        try {
            $query = Employee::active()
                ->whereNotIn('activity_status', ['resigned', 'terminated'])
                ->with([
                    'positionRate', // CRITICAL: Load position rate for getBasicSalary()
                    'governmentInfo',
                    'allowances' => function ($q) use ($payroll) {
                        $q->active()->where('effective_date', '<=', $payroll->period_end);
                    },
                    'loans' => function ($q) {
                        // Include both active and approved loans
                        $q->whereIn('status', ['active', 'approved'])->where('balance', '>', 0);
                    },
                    'deductions' => function ($q) {
                        $q->active();
                    }
                ]);

            // Apply filters for targeted payroll generation
            if ($filters) {
                if (!empty($filters['project_id'])) {
                    $query->where('project_id', $filters['project_id']);
                }
                if (!empty($filters['contract_type'])) {
                    $query->where('contract_type', $filters['contract_type']);
                }
                if (!empty($filters['position_id'])) {
                    $query->where('position_id', $filters['position_id']);
                }
            }

            if ($employeeIds) {
                $query->whereIn('id', $employeeIds);
            }

            $employees = $query->get();

            if ($employees->isEmpty()) {
                Log::warning('No employees found for payroll processing', [
                    'payroll_id' => $payroll->id,
                    'filters' => $filters,
                    'employee_ids' => $employeeIds
                ]);
                throw new \Exception('No active employees found matching the criteria. Please check employee status and filters.');
            }

            Log::info('Processing payroll for employees', [
                'payroll_id' => $payroll->id,
                'employee_count' => $employees->count()
            ]);

            foreach ($employees as $employee) {
                try {
                    $this->processEmployeePayroll($payroll, $employee);
                } catch (\Exception $e) {
                    Log::error('Failed to process payroll for employee', [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->full_name,
                        'error' => $e->getMessage()
                    ]);
                    // Continue processing other employees
                }
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
            ->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
            ->where('status', 'present')
            ->get();

        // CRITICAL: Throw exception for daily workers with no attendance
        // Cannot calculate pay without attendance data for daily-paid employees
        if ($employee->salary_type === 'daily' && $attendance->isEmpty()) {
            Log::error("Cannot process payroll: No attendance records for daily employee", [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'payroll_id' => $payroll->id,
                'period' => $payroll->period_start . ' to ' . $payroll->period_end
            ]);
            throw new \Exception("Cannot process payroll for employee {$employee->employee_number} ({$employee->full_name}): No attendance records found for daily-paid employee during period {$payroll->period_start} to {$payroll->period_end}");
        }

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

        // Calculate government contributions based on MONTHLY salary
        // SSS/PhilHealth/Pag-IBIG require consistent monthly contributions
        // Use the employee's actual monthly rate, not period estimates
        $monthlySalary = $employee->getMonthlyRate();

        // Use GovernmentRate model to get current rates
        $sssContribution = GovernmentRate::getContributionForSalary('sss', $monthlySalary, $payroll->period_start);
        $philhealthContribution = GovernmentRate::getContributionForSalary('philhealth', $monthlySalary, $payroll->period_start);
        $pagibigContribution = GovernmentRate::getContributionForSalary('pagibig', $monthlySalary, $payroll->period_start);

        // For semi-monthly payroll: divide monthly contributions by 2
        // This ensures consistent contributions regardless of actual days worked
        $divisor = $payroll->period_type === 'semi_monthly' ? 2 : 1;
        $sssAmount = $sssContribution['employee'] / $divisor;
        $philhealthAmount = $philhealthContribution['employee'] / $divisor;
        $pagibigAmount = $pagibigContribution['employee'] / $divisor;

        // Calculate taxable income for semi-monthly period
        // Remove non-taxable allowances (they're already in gross_pay)
        $taxableIncome = $grossPay
            - $allowances['non_taxable_allowances']
            - $sssAmount
            - $philhealthAmount
            - $pagibigAmount;

        // Ensure taxable income is not negative
        $taxableIncome = max(0, $taxableIncome);

        $withholdingTax = $this->taxService->computeTax($taxableIncome, 'semi-monthly');

        // Get loan deductions
        $loanDeductions = $this->calculateLoanDeductions($employee, $payroll);

        // Get other deductions
        $otherDeductions = $this->calculateOtherDeductions($employee, $payroll);

        // Calculate totals
        $totalDeductions = $sssAmount
            + $philhealthAmount
            + $pagibigAmount
            + $withholdingTax
            + $loanDeductions['sss_loan']
            + $loanDeductions['pagibig_loan']
            + $loanDeductions['other_loans']
            + $otherDeductions['ppe_deduction']
            + $otherDeductions['tools_deduction']
            + $otherDeductions['uniform_deduction']
            + $otherDeductions['absence_deduction']
            + $otherDeductions['leave_deduction']
            + $otherDeductions['other_deductions'];

        $netPay = $grossPay - $totalDeductions;

        // Calculate total allowances
        $totalAllowances = $allowances['water_allowance']
            + $allowances['cola']
            + $allowances['other_allowances'];

        // Get employee's basic rate for display
        $basicRate = $employee->getBasicSalary();

        // Calculate total overtime hours from attendance
        $overtimeHours = $attendance->sum('overtime_hours');

        // Create payroll item
        $payrollItem = PayrollItem::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employee->id,
            'basic_rate' => $basicRate,
            'days_worked' => $attendance->where('status', 'present')->count(),
            'basic_pay' => $earnings['basic_pay'],
            'overtime_hours' => $overtimeHours,
            'overtime_pay' => $earnings['overtime_pay'],
            'holiday_pay' => $earnings['holiday_pay'],
            'night_differential' => $earnings['night_differential'],
            'water_allowance' => $allowances['water_allowance'],
            'cola' => $allowances['cola'],
            'other_allowances' => $allowances['other_allowances'],
            'total_allowances' => $totalAllowances,
            'gross_pay' => $grossPay,
            'sss_contribution' => $sssAmount,
            'philhealth_contribution' => $philhealthAmount,
            'pagibig_contribution' => $pagibigAmount,
            'withholding_tax' => $withholdingTax,
            'total_other_deductions' => $otherDeductions['ppe_deduction']
                + $otherDeductions['tools_deduction']
                + $otherDeductions['uniform_deduction']
                + $otherDeductions['absence_deduction']
                + $otherDeductions['other_deductions'],
            'total_loan_deductions' => $loanDeductions['sss_loan'] + $loanDeductions['pagibig_loan'] + $loanDeductions['other_loans'],
            'total_deductions' => $totalDeductions,
            'net_pay' => $netPay,
        ]);

        // Create detailed breakdown
        $this->createPayrollDetails($payrollItem, $earnings, $allowances, $sssAmount, $philhealthAmount, $pagibigAmount, $loanDeductions, $otherDeductions);

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

        // Use position rate instead of manual basic_salary
        $basicSalary = $employee->getBasicSalary();
        $hourlyRate = $employee->getHourlyRate();

        // Get holidays in the payroll period for automatic holiday pay calculation
        $holidays = Holiday::whereBetween('holiday_date', [$payroll->period_start, $payroll->period_end])
            ->pluck('holiday_type', 'holiday_date')
            ->toArray();

        foreach ($attendance as $record) {
            // Check if attendance date falls on a holiday
            $attendanceDate = Carbon::parse($record->attendance_date)->format('Y-m-d');
            $isHoliday = isset($holidays[$attendanceDate]);
            $holidayType = $isHoliday ? $holidays[$attendanceDate] : null;

            // Override attendance holiday flags with database holidays
            if ($isHoliday) {
                $record->is_holiday = true;
                $record->holiday_type = $holidayType;
            }

            // Basic pay
            if ($employee->salary_type === 'daily') {
                $basicPay += $basicSalary * ($record->regular_hours / 8);
            } elseif ($employee->salary_type === 'hourly') {
                $basicPay += $basicSalary * $record->regular_hours;
            } else {
                // Monthly salary - calculate based on hourly rate
                $basicPay += $hourlyRate * $record->regular_hours;
            }

            // Overtime pay
            if ($record->overtime_hours > 0) {
                $overtimeRate = $this->getOvertimeRate($record);
                $overtimePay += $hourlyRate * $record->overtime_hours * $overtimeRate;
            }

            // Holiday pay - automatically calculated based on holidays table
            if ($record->is_holiday) {
                // Regular holiday = 200% pay (2x), Special holiday = 130% pay (1.3x)
                // If worked, employee gets base pay PLUS premium
                $holidayRate = $record->holiday_type === 'regular' ? 2.0 : 1.3;

                // If overtime on holiday, higher premium applies
                if ($record->overtime_hours > 0) {
                    $holidayRate = $record->holiday_type === 'regular' ? 2.6 : 1.69;
                }

                // Holiday premium (subtract 1 because base pay already counted above)
                $holidayPay += $hourlyRate * $record->regular_hours * ($holidayRate - 1);
            }

            // Rest day premium (if employee worked on their rest day but it's not a holiday)
            if (!$isHoliday && $record->is_rest_day && $record->regular_hours > 0) {
                // Rest day work = 130% pay (30% premium)
                $holidayPay += $hourlyRate * $record->regular_hours * 0.30;
            }

            // Night differential (10% of hourly rate for work between 10pm-6am)
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
        $restDay = config('payroll.rest_day', 0); // 0 = Sunday in Carbon

        if ($dayOfWeek == $restDay) {
            return 1.69; // Rest day overtime
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

        // Add meal allowances from approved MealAllowance records
        $mealAllowanceAmount = $this->calculateMealAllowances($employee, $payroll);
        if ($mealAllowanceAmount > 0) {
            $otherAllowances += $mealAllowanceAmount;
            // Meal allowances are typically taxable
        }

        return [
            'water_allowance' => round($waterAllowance, 2),
            'cola' => round($cola, 2),
            'other_allowances' => round($otherAllowances, 2),
            'non_taxable_allowances' => round($nonTaxableAllowances, 2),
        ];
    }

    /**
     * Calculate meal allowances for employee during payroll period
     */
    protected function calculateMealAllowances(Employee $employee, Payroll $payroll): float
    {
        $mealAllowanceTotal = 0;

        // Get approved meal allowances that overlap with payroll period
        $mealAllowances = \App\Models\MealAllowance::where('status', 'approved')
            ->where(function ($query) use ($payroll) {
                $query->whereBetween('period_start', [$payroll->period_start, $payroll->period_end])
                    ->orWhereBetween('period_end', [$payroll->period_start, $payroll->period_end])
                    ->orWhere(function ($q) use ($payroll) {
                        $q->where('period_start', '<=', $payroll->period_start)
                            ->where('period_end', '>=', $payroll->period_end);
                    });
            })
            ->with(['items' => function ($query) use ($employee) {
                $query->where('employee_id', $employee->id);
            }])
            ->get();

        foreach ($mealAllowances as $mealAllowance) {
            foreach ($mealAllowance->items as $item) {
                $mealStart = Carbon::parse($mealAllowance->period_start);
                $mealEnd = Carbon::parse($mealAllowance->period_end);
                $payrollStart = Carbon::parse($payroll->period_start);
                $payrollEnd = Carbon::parse($payroll->period_end);

                // Get the overlap period between meal allowance and payroll
                $overlapStart = $mealStart->max($payrollStart);
                $overlapEnd = $mealEnd->min($payrollEnd);

                if ($overlapStart->lte($overlapEnd)) {
                    // Calculate the full meal allowance amount
                    $fullAmount = $item->no_of_days * $item->amount_per_day;

                    // Calculate total days in the meal allowance period
                    $totalMealDays = $mealStart->diffInDays($mealEnd) + 1;

                    // Calculate days in the overlap (payroll period)
                    $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;

                    // Pro-rate: If meal allowance period equals payroll period, use full amount
                    // Otherwise, calculate proportionally
                    if ($totalMealDays <= $overlapDays || $totalMealDays <= 16) {
                        // Meal allowance period is same as or smaller than payroll period
                        // Or it's a short-term meal allowance (≤16 days = semi-monthly)
                        $mealAllowanceTotal += $fullAmount;
                    } else {
                        // Pro-rate based on the overlap percentage
                        $proRatedAmount = ($fullAmount / $totalMealDays) * $overlapDays;
                        $mealAllowanceTotal += round($proRatedAmount, 2);
                    }
                }
            }
        }

        return $mealAllowanceTotal;
    }

    /**
     * Calculate allowance amount based on frequency
     */
    protected function calculateAllowanceAmount($allowance, Payroll $payroll): float
    {
        switch ($allowance->frequency) {
            case 'daily':
                // Calculate actual working days in period (not calendar days)
                $periodStart = Carbon::parse($payroll->period_start);
                $periodEnd = Carbon::parse($payroll->period_end);

                // Count working days (exclude Sundays)
                $workingDays = 0;
                for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
                    if ($date->dayOfWeek !== 0) { // 0 = Sunday
                        $workingDays++;
                    }
                }
                return $allowance->amount * $workingDays;

            case 'weekly':
                // Semi-monthly is approximately 2.17 weeks
                $weeksInSemiMonth = 2.17;
                return $allowance->amount * $weeksInSemiMonth;

            case 'semi_monthly':
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
            // Include both 'active' and 'approved' loans for deduction
            if (!in_array($loan->status, ['active', 'approved']) || $loan->balance <= 0) continue;

            // Check if first payment date has been reached
            if ($loan->first_payment_date && Carbon::parse($loan->first_payment_date)->gt($payroll->period_end)) {
                continue; // Skip if first payment date hasn't been reached
            }

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
     * Calculate other deductions (company deductions from EmployeeDeduction model)
     */
    protected function calculateOtherDeductions(Employee $employee, Payroll $payroll): array
    {
        $ppeDeduction = 0;
        $toolsDeduction = 0;
        $uniformDeduction = 0;
        $absenceDeduction = 0;
        $leaveDeduction = 0;
        $otherDeductions = 0;

        foreach ($employee->deductions as $deduction) {
            if ($deduction->status !== 'active') continue;

            $amount = $deduction->amount_per_cutoff;

            switch ($deduction->deduction_type) {
                case 'ppe':
                    $ppeDeduction += $amount;
                    break;
                case 'tools':
                    $toolsDeduction += $amount;
                    break;
                case 'uniform':
                    $uniformDeduction += $amount;
                    break;
                case 'absence':
                    $absenceDeduction += $amount;
                    break;
                default:
                    // For loan, other, or any company deductions
                    if (in_array($deduction->deduction_type, ['loan', 'other'])) {
                        $otherDeductions += $amount;
                    }
                    break;
            }
        }

        // Calculate leave deductions for approved leaves
        $leaveDeduction = $this->calculateLeaveDeductions($employee, $payroll);

        return [
            'ppe_deduction' => round($ppeDeduction, 2),
            'tools_deduction' => round($toolsDeduction, 2),
            'uniform_deduction' => round($uniformDeduction, 2),
            'absence_deduction' => round($absenceDeduction, 2),
            'leave_deduction' => round($leaveDeduction, 2),
            'other_deductions' => round($otherDeductions, 2),
        ];
    }

    /**
     * Calculate leave deductions for approved leaves
     * Deducts salary for approved leave days that are unpaid
     */
    protected function calculateLeaveDeductions(Employee $employee, Payroll $payroll): float
    {
        $totalLeaveDeduction = 0;

        // Get approved leaves that fall within the payroll period
        $approvedLeaves = $employee->leaves()
            ->where('status', 'approved')
            ->where(function ($query) use ($payroll) {
                $query->whereBetween('leave_date_from', [$payroll->period_start, $payroll->period_end])
                    ->orWhereBetween('leave_date_to', [$payroll->period_start, $payroll->period_end])
                    ->orWhere(function ($q) use ($payroll) {
                        $q->where('leave_date_from', '<=', $payroll->period_start)
                            ->where('leave_date_to', '>=', $payroll->period_end);
                    });
            })
            ->with('leaveType')
            ->get();

        if ($approvedLeaves->isEmpty()) {
            return 0;
        }

        $dailyRate = $employee->getBasicSalary(); // Gets daily rate for daily employees, or monthly/22 for monthly

        // If employee is on monthly salary, calculate daily rate
        if ($employee->salary_type === 'monthly') {
            $workingDaysPerMonth = config('payroll.working_days_per_month', 22);
            $dailyRate = $employee->getBasicSalary() / $workingDaysPerMonth;
        }

        foreach ($approvedLeaves as $leave) {
            // Only deduct if leave type is unpaid
            if ($leave->leaveType && !$leave->leaveType->is_paid) {
                // Calculate days within payroll period
                $leaveStart = max(Carbon::parse($leave->leave_date_from), Carbon::parse($payroll->period_start));
                $leaveEnd = min(Carbon::parse($leave->leave_date_to), Carbon::parse($payroll->period_end));

                $daysInPeriod = $leaveStart->diffInDays($leaveEnd) + 1;

                $totalLeaveDeduction += $dailyRate * $daysInPeriod;

                Log::info("Leave deduction calculated", [
                    'employee_id' => $employee->id,
                    'leave_id' => $leave->id,
                    'leave_type' => $leave->leaveType->name,
                    'days_in_period' => $daysInPeriod,
                    'daily_rate' => $dailyRate,
                    'deduction_amount' => $dailyRate * $daysInPeriod
                ]);
            }
        }

        return $totalLeaveDeduction;
    }

    /**
     * Create detailed payroll breakdown
     */
    protected function createPayrollDetails(PayrollItem $payrollItem, array $earnings, array $allowances, float $sssAmount, float $philhealthAmount, float $pagibigAmount, array $loanDeductions, array $otherDeductions): void
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
            ['type' => 'deduction', 'category' => 'government', 'description' => 'SSS Contribution', 'amount' => $sssAmount],
            ['type' => 'deduction', 'category' => 'government', 'description' => 'PhilHealth Contribution', 'amount' => $philhealthAmount],
            ['type' => 'deduction', 'category' => 'government', 'description' => 'Pag-IBIG Contribution', 'amount' => $pagibigAmount],
            ['type' => 'deduction', 'category' => 'tax', 'description' => 'Withholding Tax', 'amount' => $payrollItem->withholding_tax],
            ['type' => 'deduction', 'category' => 'loan', 'description' => 'SSS Loan', 'amount' => $loanDeductions['sss_loan']],
            ['type' => 'deduction', 'category' => 'loan', 'description' => 'Pag-IBIG Loan', 'amount' => $loanDeductions['pagibig_loan']],
            ['type' => 'deduction', 'category' => 'loan', 'description' => 'Other Loans', 'amount' => $loanDeductions['other_loans']],
            ['type' => 'deduction', 'category' => 'other', 'description' => 'PPE Deduction', 'amount' => $otherDeductions['ppe_deduction']],
            ['type' => 'deduction', 'category' => 'other', 'description' => 'Tools Deduction', 'amount' => $otherDeductions['tools_deduction']],
            ['type' => 'deduction', 'category' => 'other', 'description' => 'Uniform Deduction', 'amount' => $otherDeductions['uniform_deduction']],
            ['type' => 'deduction', 'category' => 'other', 'description' => 'Absence Deduction', 'amount' => $otherDeductions['absence_deduction']],
            ['type' => 'deduction', 'category' => 'leave', 'description' => 'Leave Without Pay', 'amount' => $otherDeductions['leave_deduction']],
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
        $sequence = str_pad(Payroll::where('year', $date->year)
            ->where('month', $date->month)
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
        // Get all payroll items with loan deductions
        $payrollItems = $payroll->payrollItems()->with(['employee.loans' => function ($q) {
            $q->active();
        }])->get();

        foreach ($payrollItems as $payrollItem) {
            foreach ($payrollItem->employee->loans as $loan) {
                if ($loan->balance <= 0) continue;

                // Calculate the deduction amount for this semi-monthly period
                $deductionAmount = min($loan->monthly_amortization / 2, $loan->balance);

                if ($deductionAmount > 0) {
                    // Create loan payment record
                    $payment = LoanPayment::create([
                        'employee_loan_id' => $loan->id,
                        'payroll_id' => $payroll->id,
                        'payroll_item_id' => $payrollItem->id,
                        'payment_date' => $payroll->payment_date,
                        'amount' => $deductionAmount,
                        'principal_payment' => $deductionAmount, // Simplified - could calculate interest
                        'interest_payment' => 0,
                        'balance_after_payment' => $loan->balance - $deductionAmount,
                        'payment_method' => 'payroll_deduction',
                        'remarks' => "Payroll deduction - {$payroll->payroll_number}",
                    ]);

                    // Update loan balance and amount paid
                    $loan->amount_paid += $deductionAmount;
                    $loan->balance -= $deductionAmount;

                    // If fully paid, update status
                    if ($loan->balance <= 0) {
                        $loan->status = 'paid';
                        $loan->balance = 0;
                    }

                    $loan->save();
                }
            }
        }
    }

    protected function updateDeductionInstallments(Payroll $payroll): void
    {
        // Get all payroll items with deductions
        $payrollItems = $payroll->payrollItems()->with(['employee.deductions' => function ($q) {
            $q->where('status', 'active');
        }])->get();

        foreach ($payrollItems as $payrollItem) {
            foreach ($payrollItem->employee->deductions as $deduction) {
                // If deduction has installments, track the payment
                if ($deduction->total_installments > 0) {
                    $deduction->paid_installments = ($deduction->paid_installments ?? 0) + 1;

                    // Mark as completed if all installments paid
                    if ($deduction->paid_installments >= $deduction->total_installments) {
                        $deduction->status = 'completed';
                    }

                    $deduction->save();
                }
            }
        }
    }

    /**
     * Recalculate payroll for specific employees (when attendance changes)
     * Only works for payrolls in 'draft' or 'processing' status
     */
    public function recalculatePayroll(Payroll $payroll, ?array $employeeIds = null): void
    {
        if (!in_array($payroll->status, ['draft', 'processing'])) {
            throw new \Exception("Cannot recalculate payroll in '{$payroll->status}' status. Only 'draft' and 'processing' can be recalculated.");
        }

        DB::beginTransaction();
        try {
            // Delete existing payroll items for employees to recalculate
            $query = $payroll->payrollItems();
            if ($employeeIds) {
                $query->whereIn('employee_id', $employeeIds);
            }
            $query->delete();

            // Reprocess the payroll
            $this->processPayroll($payroll, $employeeIds);

            Log::info("Payroll recalculated", [
                'payroll_id' => $payroll->id,
                'employee_ids' => $employeeIds,
                'user_id' => auth()->id(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payroll recalculation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reverse a payroll (undo processing)
     * Restores loan balances and deduction installments
     * Sets payroll to 'cancelled' status
     */
    public function reversePayroll(Payroll $payroll, ?string $reason = null): void
    {
        if ($payroll->status === 'cancelled') {
            throw new \Exception("Payroll is already cancelled.");
        }

        if ($payroll->status === 'paid') {
            throw new \Exception("Cannot reverse a paid payroll. Contact accounting department.");
        }

        DB::beginTransaction();
        try {
            // Get all payroll items before deletion
            $payrollItems = $payroll->payrollItems()->with(['employee.loans', 'employee.deductions'])->get();

            // Reverse loan payments
            foreach ($payrollItems as $item) {
                foreach ($item->employee->loans as $loan) {
                    // Find loan deductions in this payroll
                    $loanPayments = LoanPayment::where('payroll_id', $payroll->id)
                        ->where('loan_id', $loan->id)
                        ->get();

                    foreach ($loanPayments as $payment) {
                        // Restore loan balance
                        $loan->amount_paid -= $payment->amount;
                        $loan->balance += $payment->amount;
                        $loan->status = 'active'; // Set back to active
                        $loan->save();

                        // Delete loan payment record
                        $payment->delete();
                    }
                }

                // Reverse deduction installments
                foreach ($item->employee->deductions as $deduction) {
                    if ($deduction->total_installments > 0) {
                        $deduction->paid_installments = max(0, ($deduction->paid_installments ?? 0) - 1);
                        $deduction->status = 'active';
                        $deduction->save();
                    }
                }
            }

            // Delete payroll items (soft delete if enabled)
            $payroll->payrollItems()->delete();

            // Update payroll status
            $payroll->update([
                'status' => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            // Create audit log entry
            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'payroll',
                'action' => 'cancelled',
                'description' => "Payroll {$payroll->payroll_number} was cancelled. Reason: {$reason}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_values' => [
                    'status' => 'processing',
                    'payroll_number' => $payroll->payroll_number,
                    'period_start' => $payroll->period_start->format('Y-m-d'),
                    'period_end' => $payroll->period_end->format('Y-m-d'),
                ],
                'new_values' => [
                    'status' => 'cancelled',
                    'cancelled_by' => auth()->id(),
                    'cancelled_at' => now()->format('Y-m-d H:i:s'),
                    'cancellation_reason' => $reason,
                ],
            ]);

            Log::warning("Payroll reversed", [
                'payroll_id' => $payroll->id,
                'payroll_number' => $payroll->payroll_number,
                'reason' => $reason,
                'user_id' => auth()->id(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payroll reversal failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
