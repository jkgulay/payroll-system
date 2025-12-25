<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThirteenthMonthPay extends Model
{
    use HasFactory;

    protected $table = 'thirteenth_month_pay';

    protected $fillable = [
        'batch_number',
        'year',
        'period',
        'computation_date',
        'payment_date',
        'status',
        'computed_by',
        'approved_by',
        'approved_at',
        'total_amount',
    ];

    protected $casts = [
        'computation_date' => 'date',
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'year' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(ThirteenthMonthPayItem::class);
    }

    public function computedBy()
    {
        return $this->belongsTo(User::class, 'computed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
