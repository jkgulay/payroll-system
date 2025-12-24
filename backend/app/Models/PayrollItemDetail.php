<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItemDetail extends Model
{
    protected $fillable = [
        'payroll_item_id',
        'type',
        'category',
        'description',
        'amount',
        'reference_id',
        'reference_type',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
