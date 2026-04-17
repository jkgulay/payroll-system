<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payroll_number',
        'period_name',
        'period_start',
        'period_end',
        'payment_date',
        'status',
        'total_gross',
        'total_deductions',
        'total_net',
        'notes',
        'payroll_scope',
        'individual_target',
        'included_position',
        'included_employee_id',
        'has_attendance',
        'excluded_positions',
        'excluded_employee_ids',
        'deduct_sss',
        'deduct_philhealth',
        'deduct_pagibig',
        'deduct_loans',
        'deduct_employee_deductions',
        'deduct_cash_advance',
        'deduct_cash_bond',
        'deduct_employee_savings',
        'overtime_employee_ids',
        'created_by',
        'finalized_by',
        'finalized_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'finalized_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_gross' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_net' => 'decimal:2',
        'included_employee_id' => 'integer',
        'has_attendance' => 'boolean',
        'excluded_positions' => 'array',
        'excluded_employee_ids' => 'array',
        'deduct_sss' => 'boolean',
        'deduct_philhealth' => 'boolean',
        'deduct_pagibig' => 'boolean',
        'deduct_loans' => 'boolean',
        'deduct_employee_deductions' => 'boolean',
        'deduct_cash_advance' => 'boolean',
        'deduct_cash_bond' => 'boolean',
        'deduct_employee_savings' => 'boolean',
        'overtime_employee_ids' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payroll) {
            if (empty($payroll->payroll_number)) {
                $payroll->payroll_number = self::generatePayrollNumber();
            }
        });
    }

    public static function generatePayrollNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "PR{$year}{$month}";

        $lastPayroll = self::withTrashed()
            ->where('payroll_number', 'like', "{$prefix}%")
            ->orderBy('payroll_number', 'desc')
            ->first();

        if ($lastPayroll) {
            $lastNumber = (int) substr($lastPayroll->payroll_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . $newNumber;
    }
}
