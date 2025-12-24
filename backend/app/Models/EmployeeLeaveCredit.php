<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeLeaveCredit extends Model
{
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'total_credits',
        'used_credits',
        'available_credits',
    ];

    protected $casts = [
        'total_credits' => 'decimal:2',
        'used_credits' => 'decimal:2',
        'available_credits' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }
}
