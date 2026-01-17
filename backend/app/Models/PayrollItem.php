<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'employee_id',
        'basic_rate',
        'days_worked',
        'basic_pay',
        'overtime_hours',
        'overtime_pay',
        'holiday_pay',
        'night_differential',
        'adjustments',
        'adjustment_notes',
        'water_allowance',
        'cola',
        'other_allowances',
        'total_allowances',
        'total_bonuses',
        'gross_pay',
        'sss_contribution',
        'philhealth_contribution',
        'pagibig_contribution',
        'withholding_tax',
        'total_other_deductions',
        'total_loan_deductions',
        'employee_deductions',
        'total_deductions',
        'net_pay',
        'payslip_generated',
        'payslip_file_path',
    ];

    protected $casts = [
        'basic_rate' => 'decimal:2',
        'days_worked' => 'decimal:2',
        'basic_pay' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'holiday_pay' => 'decimal:2',
        'night_differential' => 'decimal:2',
        'adjustments' => 'decimal:2',
        'water_allowance' => 'decimal:2',
        'cola' => 'decimal:2',
        'other_allowances' => 'decimal:2',
        'total_allowances' => 'decimal:2',
        'total_bonuses' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'sss_contribution' => 'decimal:2',
        'philhealth_contribution' => 'decimal:2',
        'pagibig_contribution' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'total_other_deductions' => 'decimal:2',
        'total_loan_deductions' => 'decimal:2',
        'employee_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'payslip_generated' => 'boolean',
    ];

    protected $appends = ['effective_rate'];

    /**
     * Get the effective rate for display
     * If basic_rate is 0 or null, get it from the employee's current rate
     */
    public function getEffectiveRateAttribute(): float
    {
        // If basic_rate is valid and not 0, use it
        if ($this->basic_rate && $this->basic_rate > 0) {
            return (float) $this->basic_rate;
        }

        // Otherwise, get the employee's current effective rate
        if ($this->employee) {
            return $this->employee->getBasicSalary();
        }

        return 0;
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
