<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeBonus extends Model
{
    protected $fillable = [
        'employee_id',
        'bonus_type',
        'bonus_name',
        'amount',
        'grant_date',
        'payment_date',
        'payment_status',
        'is_taxable',
        'reason',
        'reference_number',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'grant_date' => 'date',
        'payment_date' => 'date',
        'is_taxable' => 'boolean',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
