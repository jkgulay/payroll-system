<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'amount_paid',
        'balance',
        'loan_term_months',
        'loan_date',
        'first_payment_date',
        'maturity_date',
        'status',
        'purpose',
        'reference_number',
        'approved_by',
        'approved_at',
        'released_by',
        'released_at',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'monthly_amortization' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'loan_date' => 'date',
        'first_payment_date' => 'date',
        'maturity_date' => 'date',
        'approved_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'employee_loan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('balance', '>', 0);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('loan_type', $type);
    }
}
