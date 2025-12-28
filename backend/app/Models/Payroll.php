<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payroll';

    protected $fillable = [
        'payroll_number',
        'period_type',
        'period_start',
        'period_end',
        'payment_date',
        'pay_period_number',
        'month',
        'year',
        'status',
        'total_gross_pay',
        'total_deductions',
        'total_net_pay',
        'prepared_by',
        'prepared_at',
        'checked_by',
        'checked_at',
        'recommended_by',
        'recommended_at',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'total_gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
        'prepared_at' => 'datetime',
        'checked_at' => 'datetime',
        'recommended_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected $appends = [
        'period_label',
    ];

    // Accessors for backward compatibility
    public function getPeriodStartDateAttribute()
    {
        return $this->period_start;
    }

    public function getPeriodEndDateAttribute()
    {
        return $this->period_end;
    }

    public function setPeriodStartDateAttribute($value)
    {
        $this->attributes['period_start'] = $value;
    }

    public function setPeriodEndDateAttribute($value)
    {
        $this->attributes['period_end'] = $value;
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->period_start->format('M d') . ' - ' . $this->period_end->format('M d, Y');
    }

    // Relationships
    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function recommendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPeriod($query, $year, $month)
    {
        return $query->whereYear('period_start_date', $year)
            ->whereMonth('period_start_date', $month);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'processing']);
    }

    public function scopeProcessed($query)
    {
        return $query->whereIn('status', ['checked', 'recommended', 'approved', 'paid']);
    }

    // Helper Methods
    public function canEdit(): bool
    {
        return in_array($this->status, ['draft', 'processing']);
    }

    public function canCheck(): bool
    {
        return $this->status === 'processing' && $this->prepared_at !== null;
    }

    public function canRecommend(): bool
    {
        return $this->status === 'checked' && $this->checked_at !== null;
    }

    public function canApprove(): bool
    {
        return $this->status === 'recommended' && $this->recommended_at !== null;
    }

    public function canMarkAsPaid(): bool
    {
        return $this->status === 'approved' && $this->approved_at !== null;
    }

    public function calculateTotals(): void
    {
        $this->total_gross_pay = $this->payrollItems()->sum('gross_pay');
        $this->total_deductions = $this->payrollItems()->sum('total_deductions');
        $this->total_net_pay = $this->payrollItems()->sum('net_pay');
        $this->save();
    }

    public function getEmployeeCount(): int
    {
        return $this->payrollItems()->count();
    }
}
