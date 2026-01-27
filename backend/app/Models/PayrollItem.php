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
        'rate',
        'days_worked',
        'regular_days',
        'holiday_days',
        'holiday_pay',
        'basic_pay',
        'regular_ot_hours',
        'regular_ot_pay',
        'special_ot_hours',
        'special_ot_pay',
        'cola',
        'other_allowances',
        'allowances_breakdown',
        'gross_pay',
        'undertime_hours',
        'undertime_deduction',
        'sss',
        'philhealth',
        'pagibig',
        'withholding_tax',
        'employee_savings',
        'cash_advance',
        'loans',
        'other_deductions',
        'total_deductions',
        'net_pay',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'days_worked' => 'decimal:2',
        'regular_days' => 'decimal:2',
        'holiday_days' => 'decimal:2',
        'holiday_pay' => 'decimal:2',
        'basic_pay' => 'decimal:2',
        'regular_ot_hours' => 'decimal:2',
        'regular_ot_pay' => 'decimal:2',
        'special_ot_hours' => 'decimal:2',
        'special_ot_pay' => 'decimal:2',
        'cola' => 'decimal:2',
        'other_allowances' => 'decimal:2',
        'allowances_breakdown' => 'array',
        'gross_pay' => 'decimal:2',
        'undertime_hours' => 'decimal:2',
        'undertime_deduction' => 'decimal:2',
        'sss' => 'decimal:2',
        'philhealth' => 'decimal:2',
        'pagibig' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'employee_savings' => 'decimal:2',
        'cash_advance' => 'decimal:2',
        'loans' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    protected $appends = ['effective_rate'];

    /**
     * Get the effective rate for display
     * If rate is 0 or null, get it from the employee's current rate
     */
    public function getEffectiveRateAttribute(): float
    {
        // If rate is valid and not 0, use it
        if ($this->rate && $this->rate > 0) {
            return (float) $this->rate;
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
