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

        // Schedule settings (project overrides if available)
        $schedule = $this->getScheduleConfig($timeIn);
        $standardHours = $schedule['standard_hours'];

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
        $this->late_hours = $this->calculateLateHours(
            $timeIn,
            $schedule['standard_time_in'],
            $schedule['grace_period_minutes']
        );

        // Force half-day status if late exceeds threshold (e.g., 1 hour and 1 minute)
        $halfDayLateMinutes = (int) config('payroll.attendance.half_day_late_minutes', 61);
        if ($this->late_hours > 0 && ($this->late_hours * 60) >= $halfDayLateMinutes) {
            $this->status = 'half_day';
        } elseif ($this->late_hours > 0 && in_array($this->status, ['present', 'late'])) {
            $this->status = 'late';
        }

        // If half-day, cap regular hours to half-day and treat excess as overtime
        if ($this->status === 'half_day') {
            $halfDayHours = $standardHours / 2;
            $this->regular_hours = min($totalHours, $halfDayHours);
            $this->overtime_hours = max(0, $totalHours - $halfDayHours);
        }

        // Calculate undertime based on project/department schedule settings
        if ($this->status === 'half_day') {
            // Half-day already handled by status; avoid double deduction
            $this->undertime_hours = 0;
        } else {
            // Always use schedule-based calculation (project settings from Attendance Settings UI)
            $this->undertime_hours = $this->calculateUndertimeHoursForSchedule(
                $timeIn,
                $schedule
            );
        }

        $this->save();
    }

    /**
     * Apply undertime-overtime offset
     * If employee has both undertime and overtime, offset them
     * Example: 2hrs undertime + 2hrs overtime = 0 undertime, 0 overtime
     * Example: 4hrs undertime + 2hrs overtime = 2hrs undertime, 0 overtime
     */
    private function applyUndertimeOvertimeOffset(): void
    {
        // Offset disabled to keep late undertime and overtime independent.
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

    /**
     * Get schedule configuration for attendance calculation
     * Priority: Project/Department settings (from Attendance Settings UI) -> System defaults
     */
    private function getScheduleConfig(Carbon $timeIn): array
    {
        $defaultTimeIn = config('payroll.attendance.standard_time_in', '07:30');
        $defaultTimeOut = config('payroll.attendance.standard_time_out', '17:00');
        $defaultGrace = (int) config('payroll.attendance.grace_period_minutes', 0);
        $defaultHours = (float) config('payroll.standard_hours_per_day', 8);

        $project = $this->employee?->project;
        
        // If no project assigned, use system defaults
        if (!$project) {
            return [
                'standard_time_in' => $defaultTimeIn,
                'standard_time_out' => $defaultTimeOut,
                'grace_period_minutes' => $defaultGrace,
                'standard_hours' => $defaultHours,
            ];
        }

        // Use project settings with fallback to defaults for any missing values
        return [
            'standard_time_in' => $project->time_in ?: $defaultTimeIn,
            'standard_time_out' => $project->time_out ?: $defaultTimeOut,
            'grace_period_minutes' => $project->grace_period_minutes !== null 
                ? (int) $project->grace_period_minutes 
                : $defaultGrace,
            'standard_hours' => $defaultHours,
        ];
    }

    private function calculateLateHours(
        Carbon $timeIn,
        string $standardTimeInConfig,
        int $gracePeriod
    ): float {
        $standardTimeIn = Carbon::parse($timeIn->toDateString() . ' ' . $standardTimeInConfig)
            ->addMinutes($gracePeriod);

        if ($timeIn->gt($standardTimeIn)) {
            $lateMinutes = $standardTimeIn->diffInMinutes($timeIn);
            return round($lateMinutes / 60, 2);
        }

        return 0;
    }

    /**
     * Calculate undertime hours based on project schedule overrides
     * Includes both late arrivals AND early departures
     */
    private function calculateUndertimeHoursForSchedule(Carbon $timeIn, array $schedule): float
    {
        $dateString = $this->attendance_date instanceof Carbon 
            ? $this->attendance_date->format('Y-m-d') 
            : $this->attendance_date;
            
        $standardTimeIn = Carbon::parse($dateString . ' ' . $schedule['standard_time_in']);
        $standardTimeOut = Carbon::parse($dateString . ' ' . $schedule['standard_time_out']);
        $gracePeriodMinutes = (int) $schedule['grace_period_minutes'];

        // Handle overnight shifts
        if ($standardTimeOut->lt($standardTimeIn)) {
            $standardTimeOut->addDay();
        }

        $undertimeMinutes = 0;

        // Calculate late arrival undertime (after grace period)
        $allowedTimeIn = $standardTimeIn->copy()->addMinutes($gracePeriodMinutes);
        if ($timeIn->gt($allowedTimeIn)) {
            $undertimeMinutes += $allowedTimeIn->diffInMinutes($timeIn);
        }

        // Calculate early departure undertime
        if ($this->time_out) {
            $timeOut = Carbon::parse($dateString . ' ' . $this->time_out);
            // Handle overnight shifts
            if ($timeOut->lt($timeIn)) {
                $timeOut->addDay();
            }
            
            // If employee left before standard time out
            if ($timeOut->lt($standardTimeOut)) {
                $undertimeMinutes += $timeOut->diffInMinutes($standardTimeOut);
            }
        }

        return round($undertimeMinutes / 60, 4);
    }

    /**
     * @deprecated No longer used - schedules are now managed per-department via Attendance Settings UI
     * Kept for reference but can be safely removed in future cleanup
     */

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
