<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'amount',
        'type',
        'reason',
        'reference_period',
        'effective_date',
        'status',
        'applied_payroll_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_date' => 'date',
    ];

    /**
     * Get the employee that owns the adjustment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the payroll where this adjustment was applied.
     */
    public function appliedPayroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, 'applied_payroll_id');
    }

    /**
     * Get the user who created this adjustment.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this adjustment.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope for pending adjustments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for applied adjustments.
     */
    public function scopeApplied($query)
    {
        return $query->where('status', 'applied');
    }

    /**
     * Get the effective amount (negative for deductions, positive for additions)
     */
    public function getEffectiveAmountAttribute(): float
    {
        return $this->type === 'deduction' ? -abs($this->amount) : abs($this->amount);
    }

    /**
     * Accessor for description attribute (alias for reference_period for backwards compatibility)
     */
    public function getDescriptionAttribute(): ?string
    {
        return $this->reference_period;
    }
}
