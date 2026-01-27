<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'attendance';

    protected $fillable = [
        'employee_id',
        'attendance_date',
        'time_in',
        'time_out',
        'break_start',
        'break_end',
        'regular_hours',
        'overtime_hours',
        'undertime_hours',
        'late_hours',
        'night_differential_hours',
        'status',
        'is_holiday',
        'holiday_type',
        'is_manual_entry',
        'manual_reason',
        'is_edited',
        'edit_reason',
        'edited_by',
        'edited_at',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'created_by',
        'updated_by',
        'is_approved',
        'is_rejected',
        'rejected_by',
        'rejected_at',
        'approval_notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'undertime_hours' => 'decimal:2',
        'late_hours' => 'decimal:2',
        'night_differential_hours' => 'decimal:2',
        'is_holiday' => 'boolean',
        'is_manual_entry' => 'boolean',
        'is_edited' => 'boolean',
        'is_approved' => 'boolean',
        'is_rejected' => 'boolean',
        'edited_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $appends = ['hours_worked'];

    // Accessors
    public function getHoursWorkedAttribute(): float
    {
        return round(($this->regular_hours ?? 0) + ($this->overtime_hours ?? 0), 2);
    }

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('attendance_date', [$startDate, $endDate]);
    }

    public function scopePresent($query)
    {
        return $query->where('status', 'present');
    }

    public function scopeWithOvertime($query)
    {
        return $query->where('overtime_hours', '>', 0);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', 'pending');
    }

    // Helper Methods
    public function calculateHours(): void
    {
        if (!$this->time_in || !$this->time_out) {
            return;
        }

        // Parse time fields - combine with attendance date to get full datetime
        $dateString = $this->attendance_date instanceof Carbon ? $this->attendance_date->format('Y-m-d') : $this->attendance_date;
        $timeIn = Carbon::parse($dateString . ' ' . $this->time_in);
        $timeOut = Carbon::parse($dateString . ' ' . $this->time_out);

        // Handle overnight shifts (time_out is next day)
        if ($timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        // Calculate break duration
        $breakMinutes = 0;
        if ($this->break_start && $this->break_end) {
            $breakStart = Carbon::parse($dateString . ' ' . $this->break_start);
            $breakEnd = Carbon::parse($dateString . ' ' . $this->break_end);

            // Handle overnight break
            if ($breakEnd->lt($breakStart)) {
                $breakEnd->addDay();
            }

            $breakMinutes = $breakStart->diffInMinutes($breakEnd);
        }

        // Total worked minutes (excluding break)
        $totalMinutes = $timeIn->diffInMinutes($timeOut) - $breakMinutes;
        $totalHours = $totalMinutes / 60;

        // Standard hours from config
        $standardHours = config('payroll.standard_hours_per_day', 8);

        // Calculate regular and overtime hours
        if ($totalHours <= $standardHours) {
            $this->regular_hours = $totalHours;
            $this->overtime_hours = 0;
        } else {
            $this->regular_hours = $standardHours;
            $this->overtime_hours = $totalHours - $standardHours;
        }

        // Calculate night differential hours (10 PM - 6 AM)
        $this->night_differential_hours = $this->calculateNightDifferentialHours($timeIn, $timeOut);

        // Calculate late hours
        $this->late_hours = $this->calculateLateHours($timeIn);

        // Calculate undertime - use department-specific logic if applicable
        $undertimeGroup = $this->getUndertimeGroup();
        if ($undertimeGroup) {
            // Use strict undertime calculation for tracked departments
            $this->undertime_hours = $this->calculateUndertimeHours($timeIn, $totalHours);
        } else {
            // Default undertime calculation for non-tracked departments
            if ($totalHours < $standardHours && $this->status === 'present') {
                $this->undertime_hours = $standardHours - $totalHours;
            } else {
                $this->undertime_hours = 0;
            }
        }

        $this->save();
    }

    private function calculateNightDifferentialHours(Carbon $timeIn, Carbon $timeOut): float
    {
        $nightStartTime = config('payroll.attendance.night_shift_start', '22:00');
        $nightEndTime = config('payroll.attendance.night_shift_end', '06:00');
        $nightStart = Carbon::parse($timeIn->toDateString() . ' ' . $nightStartTime);
        $nightEnd = Carbon::parse($timeIn->toDateString() . ' ' . $nightEndTime)->addDay();

        $ndHours = 0;

        // Check if work time overlaps with night differential period
        if ($timeOut->gt($nightStart)) {
            $ndStart = $timeIn->gt($nightStart) ? $timeIn : $nightStart;
            $ndEnd = $timeOut->lt($nightEnd) ? $timeOut : $nightEnd;

            if ($ndEnd->gt($ndStart)) {
                $ndHours = $ndStart->diffInMinutes($ndEnd) / 60;
            }
        }

        return round($ndHours, 2);
    }

    private function calculateLateHours(Carbon $timeIn): float
    {
        $standardTimeInConfig = config('payroll.attendance.standard_time_in', '08:00');
        $gracePeriod = config('payroll.attendance.grace_period_minutes', 15);
        $standardTimeIn = Carbon::parse($timeIn->toDateString() . ' ' . $standardTimeInConfig)
            ->addMinutes($gracePeriod);

        if ($timeIn->gt($standardTimeIn)) {
            $lateMinutes = $standardTimeIn->diffInMinutes($timeIn);
            return round($lateMinutes / 60, 2);
        }

        return 0;
    }

    /**
     * Check if employee's department requires strict undertime tracking
     * Returns the group ('8am', '730am', or null)
     */
    private function getUndertimeGroup(): ?string
    {
        if (!$this->employee) {
            return null;
        }

        $department = $this->employee->department;

        // Check if in 8:00 AM group
        $group8am = config('payroll.undertime.group_8am.departments', []);
        if (in_array($department, $group8am)) {
            return '8am';
        }

        // Check if in 7:30 AM group (all other departments)
        // Excluding null/empty departments
        if (!empty($department)) {
            return '730am';
        }

        return null;
    }

    /**
     * Calculate undertime hours for departments with strict time-in requirements
     * Supports multiple department groups with different time-in schedules
     */
    private function calculateUndertimeHours(Carbon $timeIn, float $totalHours): float
    {
        // Get the undertime group this employee belongs to
        $group = $this->getUndertimeGroup();
        
        if (!$group) {
            return 0; // No undertime tracking for this department
        }

        // Get configuration for the specific group
        $groupConfig = config("payroll.undertime.group_{$group}");
        if (!$groupConfig) {
            return 0;
        }

        $standardTimeInConfig = $groupConfig['standard_time_in'];
        $gracePeriodMinutes = $groupConfig['grace_period_minutes'];
        $halfDayThresholdConfig = $groupConfig['half_day_threshold'];
        $standardHours = config('payroll.standard_hours_per_day', 8);

        $standardTimeIn = Carbon::parse($timeIn->toDateString() . ' ' . $standardTimeInConfig);
        $halfDayThreshold = Carbon::parse($timeIn->toDateString() . ' ' . $halfDayThresholdConfig);

        // Check if time-in is at or after half-day threshold - mark as half day
        if ($timeIn->gte($halfDayThreshold)) {
            $this->status = 'half_day';
            // Undertime is 4 hours (half of 8 hours)
            return $standardHours / 2;
        }

        // Calculate undertime based on late time-in beyond grace period
        $allowedTimeIn = $standardTimeIn->copy()->addMinutes($gracePeriodMinutes);
        
        if ($timeIn->gt($allowedTimeIn)) {
            // Calculate minutes late
            $minutesLate = $allowedTimeIn->diffInMinutes($timeIn);
            return round($minutesLate / 60, 4); // Return in hours with precision
        }

        return 0;
    }

    public function isPresent(): bool
    {
        return $this->status === 'present';
    }

    public function hasOvertime(): bool
    {
        return $this->overtime_hours > 0;
    }

    public function isHoliday(): bool
    {
        return $this->is_holiday;
    }

    /**
     * Automatically check and mark if attendance date is a holiday
     */
    public function checkAndMarkHoliday(): void
    {
        $holiday = Holiday::getHolidayForDate($this->attendance_date);
        
        if ($holiday) {
            $this->is_holiday = true;
            $this->holiday_type = $holiday->type;
            
            // If no status is set yet, mark as holiday
            if (!$this->status || $this->status === 'present') {
                // Keep present status if already marked, otherwise set as holiday
                $this->status = $this->time_in ? 'present' : 'holiday';
            }
        } else {
            $this->is_holiday = false;
            $this->holiday_type = null;
        }
    }

    /**
     * Boot method to automatically check holidays when creating/updating attendance
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($attendance) {
            if ($attendance->attendance_date) {
                $attendance->checkAndMarkHoliday();
            }
        });
    }
}
