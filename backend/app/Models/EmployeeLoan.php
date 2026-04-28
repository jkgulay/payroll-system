<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class EmployeeLoan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_number',
        'employee_id',
        'loan_type',
        'principal_amount',
        'interest_rate',
        'total_amount',
        'monthly_amortization',
        'semi_monthly_amortization',
        'payment_frequency', // 'monthly' or 'semi-monthly'
        'amount_paid',
        'balance',
        'loan_term_months',
        'loan_date',
        'first_payment_date',
        'maturity_date',
        'status', // 'pending', 'approved', 'active', 'paid', 'cancelled'
        'purpose',
        'reference_number',
        'notes',
        'requested_by', // Employee who requested
        'created_by', // Accountant who created
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'released_by',
        'released_at',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'monthly_amortization' => 'decimal:2',
        'semi_monthly_amortization' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'loan_date' => 'date',
        'first_payment_date' => 'date',
        'maturity_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected $appends = [
        'is_overdue_but_collecting',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'employee_loan_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('balance', '>', 0);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereIn('status', ['approved', 'active']);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('loan_type', $type);
    }

    public function getIsOverdueButCollectingAttribute(): bool
    {
        if ((string) $this->status !== 'active') {
            return false;
        }

        if ((float) ($this->balance ?? 0) <= 0) {
            return false;
        }

        if (empty($this->first_payment_date) || (int) ($this->loan_term_months ?? 0) <= 0) {
            return false;
        }

        $originalMaturity = Carbon::parse($this->first_payment_date)
            ->addMonths((int) $this->loan_term_months)
            ->endOfDay();

        return Carbon::now()->gt($originalMaturity);
    }

    /**
     * Generate unique loan number
     */
    public static function generateLoanNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "LOAN-{$year}{$month}-";

        // Include soft-deleted records to avoid duplicate numbers
        $lastLoan = self::withTrashed()
            ->where('loan_number', 'like', "{$prefix}%")
            ->orderBy('loan_number', 'desc')
            ->first();

        $sequence = $lastLoan ? (intval(substr($lastLoan->loan_number, -4)) + 1) : 1;

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
