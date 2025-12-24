<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThirteenthMonthPay extends Model
{
    protected $table = 'thirteenth_month_pay';

    protected $fillable = [
        'employee_id',
        'year',
        'total_basic_salary',
        'thirteenth_month_amount',
        'taxable_amount',
        'tax_withheld',
        'net_amount',
        'payment_date',
        'status',
        'computed_by',
        'computed_at',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
    ];

    protected $casts = [
        'total_basic_salary' => 'decimal:2',
        'thirteenth_month_amount' => 'decimal:2',
        'taxable_amount' => 'decimal:2',
        'tax_withheld' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'payment_date' => 'date',
        'computed_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
