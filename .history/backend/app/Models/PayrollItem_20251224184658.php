<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'employee_id',
        'basic_pay',
        'overtime_pay',
        'holiday_pay',
        'night_differential',
        'water_allowance',
        'cola',
        'other_allowances',
        'other_earnings',
        'gross_pay',
        'sss_contribution',
        'philhealth_contribution',
        'pagibig_contribution',
        'withholding_tax',
        'sss_loan',
        'pagibig_loan',
        'other_loans',
        'ppe_deduction',
        'other_deductions',
        'total_deductions',
        'net_pay',
        'days_worked',
        'regular_hours',
        'overtime_hours',
        'night_differential_hours',
        'remarks',
    ];

    protected $casts = [
        'basic_pay' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'holiday_pay' => 'decimal:2',
        'night_differential' => 'decimal:2',
        'water_allowance' => 'decimal:2',
        'cola' => 'decimal:2',
        'other_allowances' => 'decimal:2',
        'other_earnings' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'sss_contribution' => 'decimal:2',
        'philhealth_contribution' => 'decimal:2',
        'pagibig_contribution' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'sss_loan' => 'decimal:2',
        'pagibig_loan' => 'decimal:2',
        'other_loans' => 'decimal:2',
        'ppe_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'days_worked' => 'decimal:2',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'night_differential_hours' => 'decimal:2',
    ];

    // Relationships
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(PayrollItemDetail::class);
    }

    public function earningDetails(): HasMany
    {
        return $this->details()->where('type', 'earning');
    }

    public function deductionDetails(): HasMany
    {
        return $this->details()->where('type', 'deduction');
    }

    // Helper Methods
    public function calculateGrossPay(): float
    {
        return $this->basic_pay
            + $this->overtime_pay
            + $this->holiday_pay
            + $this->night_differential
            + $this->water_allowance
            + $this->cola
            + $this->other_allowances
            + $this->other_earnings;
    }

    public function calculateTotalDeductions(): float
    {
        return $this->sss_contribution
            + $this->philhealth_contribution
            + $this->pagibig_contribution
            + $this->withholding_tax
            + $this->sss_loan
            + $this->pagibig_loan
            + $this->other_loans
            + $this->ppe_deduction
            + $this->other_deductions;
    }

    public function calculateNetPay(): float
    {
        return $this->calculateGrossPay() - $this->calculateTotalDeductions();
    }

    public function updateTotals(): void
    {
        $this->gross_pay = $this->calculateGrossPay();
        $this->total_deductions = $this->calculateTotalDeductions();
        $this->net_pay = $this->calculateNetPay();
        $this->save();
    }
}
