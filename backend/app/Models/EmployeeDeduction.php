<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDeduction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'deduction_type', // 'ppe', 'tools', 'uniform', 'absence', 'sss', 'philhealth', 'pagibig', 'tax', 'loan', 'other'
        'deduction_name',
        'total_amount',
        'amount_per_cutoff',
        'installments',
        'installments_paid',
        'balance',
        'start_date',
        'end_date',
        'status', // 'active', 'completed', 'cancelled'
        'description',
        'reference_number',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'amount_per_cutoff' => 'decimal:2',
        'balance' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('deduction_type', $type);
    }

    public function scopeGovernment($query)
    {
        return $query->whereIn('deduction_type', ['sss', 'philhealth', 'pagibig', 'tax']);
    }

    public function scopeCompany($query)
    {
        return $query->whereIn('deduction_type', ['ppe', 'tools', 'uniform', 'absence', 'loan', 'other']);
    }
}
