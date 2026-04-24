<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItemLeaveOut extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_leave_out_id',
        'payroll_item_id',
        'payroll_id',
        'deduction_amount',
        'applied_days',
        'applied_hours',
    ];

    protected $casts = [
        'deduction_amount' => 'decimal:2',
        'applied_days' => 'decimal:2',
        'applied_hours' => 'decimal:2',
    ];

    public function leaveOut(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeaveOut::class, 'employee_leave_out_id');
    }

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
