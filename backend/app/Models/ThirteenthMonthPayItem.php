<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThirteenthMonthPayItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'thirteenth_month_pay_id',
        'employee_id',
        'total_basic_salary',
        'taxable_thirteenth_month',
        'non_taxable_thirteenth_month',
        'withholding_tax',
        'net_pay',
    ];

    protected $casts = [
        'total_basic_salary' => 'decimal:2',
        'taxable_thirteenth_month' => 'decimal:2',
        'non_taxable_thirteenth_month' => 'decimal:2',
        'withholding_tax' => 'decimal:2',
        'net_pay' => 'decimal:2',
    ];

    public function thirteenthMonthPay()
    {
        return $this->belongsTo(ThirteenthMonthPay::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
