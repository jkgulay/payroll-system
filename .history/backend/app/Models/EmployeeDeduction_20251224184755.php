<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDeduction extends Model
{
    protected $fillable = [
        'employee_id',
        'deduction_type',
        'deduction_name',
        'total_amount',
        'amount_per_cutoff',
        'installments',
        'installments_paid',
        'start_date',
        'end_date',
        'status',
        'description',
        'reference_number',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'amount_per_cutoff' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
