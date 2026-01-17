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
        'user_id',
        'employee_number',
        'biometric_id',
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
        'department',
        'staff_type',
        'worker_address',
        'position_id',
        'work_schedule',
        'contract_type',
        'activity_status',
        'date_hired',
        'date_regularized',
        'date_separated',
        'separation_reason',
        'salary_type',
        'basic_salary',
        'custom_pay_rate',
        // Government IDs
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'tin_number',
        'bank_name',
        'bank_account_number',
        'profile_photo',
        'is_active',
        'resignation_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_hired' => 'date',
        'date_regularized' => 'date',
        'date_separated' => 'date',
        'basic_salary' => 'decimal:2',
        'custom_pay_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'full_name',
        'position',
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

    public function getPositionAttribute(): ?string
    {
        // First check if position_id exists and try to load the relationship
        if ($this->position_id) {
            // If relationship is already loaded, use it
            if ($this->relationLoaded('positionRate') && $this->positionRate) {
                return $this->positionRate->position_name;
            }
            // If not loaded, load it now
            try {
                $positionRate = $this->positionRate;
                return $positionRate?->position_name ?? 'N/A';
            } catch (\Exception $e) {
                return 'N/A';
            }
        }
        // Fallback to the position column if it exists in attributes
        return $this->attributes['position'] ?? 'N/A';
    }

    public function getDailyRateAttribute(): float
    {
        return $this->getBasicSalary();
    }

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function positionRate(): BelongsTo
    {
        return $this->belongsTo(PositionRate::class, 'position_id');
    }

    // Alias for backward compatibility
    public function position(): BelongsTo
    {
        return $this->belongsTo(PositionRate::class, 'position_id');
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

    public function resignation(): HasOne
    {
        return $this->hasOne(Resignation::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByWorkSchedule($query, $schedule)
    {
        return $query->where('work_schedule', $schedule);
    }

    // Legacy alias
    public function scopeByEmploymentType($query, $type)
    {
        // Map old values to new
        $schedule = $type === 'part_time' ? 'part_time' : 'full_time';
        return $query->where('work_schedule', $schedule);
    }

    public function scopeByContractType($query, $type)
    {
        return $query->where('contract_type', $type);
    }

    public function scopeByActivityStatus($query, $status)
    {
        return $query->where('activity_status', $status);
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

    /**
     * Get the effective basic salary for this employee
     * Priority: custom_pay_rate > PositionRate daily_rate > manual basic_salary field
     */
    public function getBasicSalary(): float
    {
        // Priority 1: Custom pay rate set by admin (overrides everything)
        if ($this->custom_pay_rate !== null) {
            return (float) $this->custom_pay_rate;
        }

        // Priority 2: If employee has position_id, get rate from PositionRate table
        if ($this->position_id && $this->positionRate) {
            return (float) $this->positionRate->daily_rate;
        }

        // Priority 3: Fallback to manual basic_salary field (for backward compatibility)
        return (float) $this->basic_salary;
    }

    public function getSemiMonthlyRate(): float
    {
        $basicSalary = $this->getBasicSalary();

        if ($this->salary_type === 'daily') {
            // Use configured working days per semi-monthly period
            $workingDaysPerSemiMonth = config('payroll.working_days_per_semi_month', 11);
            return $basicSalary * $workingDaysPerSemiMonth;
        }
        return $basicSalary / 2;
    }

    public function getMonthlyRate(): float
    {
        $basicSalary = $this->getBasicSalary();

        if ($this->salary_type === 'daily') {
            $workingDaysPerMonth = config('payroll.working_days_per_month', 22);
            return (float) ($basicSalary * $workingDaysPerMonth);
        }

        if ($this->salary_type === 'hourly') {
            $workingDaysPerMonth = config('payroll.working_days_per_month', 22);
            $standardHours = config('payroll.standard_hours_per_day', 8);
            return (float) ($basicSalary * $standardHours * $workingDaysPerMonth);
        }

        return (float) $basicSalary;
    }

    public function getHourlyRate(): float
    {
        $basicSalary = $this->getBasicSalary();
        $standardHours = config('payroll.standard_hours_per_day', 8);

        if ($this->salary_type === 'daily') {
            // For daily workers, divide daily rate by hours per day
            return $basicSalary / $standardHours;
        }

        // For monthly workers, calculate from monthly rate
        $monthlyRate = $basicSalary;
        $workingDaysPerMonth = config('payroll.working_days_per_month', 22);
        return $monthlyRate / ($workingDaysPerMonth * $standardHours);
    }
}
