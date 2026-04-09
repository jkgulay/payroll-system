<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeductionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_deduction_id',
        'payroll_id',
        'payroll_item_id',
        'payment_date',
        'amount',
        'balance_after_payment',
        'installment_number',
        'remarks',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after_payment' => 'decimal:2',
        'installment_number' => 'integer',
    ];

    public function deduction(): BelongsTo
    {
        return $this->belongsTo(EmployeeDeduction::class, 'employee_deduction_id');
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }
}
