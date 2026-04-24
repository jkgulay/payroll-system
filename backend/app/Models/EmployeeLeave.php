<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeLeave extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'leave_date_from',
        'leave_date_to',
        'duration_type',
        'duration_hours',
        'number_of_days',
        'is_with_pay',
        'reason',
        'status',
        'is_locked',
        'locked_by_payroll_id',
        'locked_at',
        'approved_by',
        'approved_at',
        'approval_remarks',
        'rejection_reason',
    ];

    protected $casts = [
        'leave_date_from' => 'date',
        'leave_date_to' => 'date',
        'duration_hours' => 'decimal:2',
        'number_of_days' => 'decimal:2',
        'is_with_pay' => 'boolean',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function leaveOut(): HasOne
    {
        return $this->hasOne(EmployeeLeaveOut::class, 'employee_leave_id');
    }

    public function isWithPay(): bool
    {
        if ($this->is_with_pay !== null) {
            return (bool) $this->is_with_pay;
        }

        if ($this->relationLoaded('leaveType') && $this->leaveType) {
            return (bool) $this->leaveType->is_paid;
        }

        return (bool) optional($this->leaveType)->is_paid;
    }
}
