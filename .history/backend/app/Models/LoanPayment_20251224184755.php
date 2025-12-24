<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanPayment extends Model
{
    protected $fillable = [
        'employee_loan_id',
        'payroll_id',
        'payroll_item_id',
        'payment_date',
        'amount',
        'principal_payment',
        'interest_payment',
        'balance_after_payment',
        'payment_number',
        'payment_method',
        'reference_number',
        'remarks',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'principal_payment' => 'decimal:2',
        'interest_payment' => 'decimal:2',
        'balance_after_payment' => 'decimal:2',
    ];

    public function loan()
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
