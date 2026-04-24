<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLeaveOut extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_leave_id',
        'employee_id',
        'leave_type_id',
        'leave_date_from',
        'leave_date_to',
        'duration_type',
        'quantity_days',
        'quantity_hours',
        'created_by',
    ];

    protected $casts = [
        'leave_date_from' => 'date',
        'leave_date_to' => 'date',
        'quantity_days' => 'decimal:2',
        'quantity_hours' => 'decimal:2',
    ];

    public function leave(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeave::class, 'employee_leave_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payrollLinks(): HasMany
    {
        return $this->hasMany(PayrollItemLeaveOut::class, 'employee_leave_out_id');
    }
}
