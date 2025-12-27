<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'date_of_birth',
        'gender',
        'civil_status',
        'nationality',
        'email',
        'username',
        'mobile_number',
        'phone_number',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_number',
        'project_id',
        'worker_address',
        'position',
        'employment_type',
        'employment_status',
        'date_hired',
        'date_regularized',
        'date_separated',
        'separation_reason',
        'salary_type',
        'basic_salary',
        // Allowances
        'has_water_allowance',
        'water_allowance',
        'has_cola',
        'cola',
        'has_incentives',
        'incentives',
        'has_ppe',
        'ppe',
        // Government IDs
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'tin_number',
        'bank_name',
        'bank_account_number',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_hired' => 'date',
        'date_regularized' => 'date',
        'date_separated' => 'date',
        'basic_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'full_name',
    ];

    // Accessors
    public function getFullNameAttribute(): string
    {
        $name = "{$this->first_name} ";
        if ($this->middle_name) {
            $name .= substr($this->middle_name, 0, 1) . ". ";
        }
        $name .= $this->last_name;
        if ($this->suffix) {
            $name .= " {$this->suffix}";
        }
        return $name;
    }

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function allowances(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    public function bonuses(): HasMany
    {
        return $this->hasMany(EmployeeBonus::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function governmentInfo(): HasOne
    {
        return $this->hasOne(EmployeeGovernmentInfo::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    public function leaveCredits(): HasMany
    {
        return $this->hasMany(EmployeeLeaveCredit::class);
    }

    public function thirteenthMonthPay(): HasMany
    {
        return $this->hasMany(ThirteenthMonthPay::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByEmploymentType($query, $type)
    {
        return $query->where('employment_type', $type);
    }

    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeDailyPaid($query)
    {
        return $query->where('salary_type', 'daily');
    }

    public function scopeMonthlyPaid($query)
    {
        return $query->where('salary_type', 'monthly');
    }

    // Helper Methods
    public function getSemiMonthlyRate(): float
    {
        if ($this->salary_type === 'daily') {
            // Assume 11 working days per semi-monthly period
            return $this->basic_salary * 11;
        }
        return $this->basic_salary / 2;
    }

    public function getMonthlyRate(): float
    {
        if ($this->salary_type === 'daily') {
            // Assume 22 working days per month (or use config)
            $workingDaysPerMonth = config('payroll.working_days_per_month', 22);
            return $this->basic_salary * $workingDaysPerMonth;
        }
        return $this->basic_salary;
    }

    public function getHourlyRate(): float
    {
        $standardHours = config('payroll.standard_hours_per_day', 8);
        return $this->basic_salary / $standardHours;
    }
}
