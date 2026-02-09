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
        'ot_time_in',
        'ot_time_out',
        'ot_time_in_2',
        'ot_time_out_2',
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

    protected $appends = ['hours_worked', 'actual_time_out'];

    // Accessors
    public function getHoursWorkedAttribute(): float
    {
        return round(($this->regular_hours ?? 0) + ($this->overtime_hours ?? 0), 2);
    }

    /**
     * Get the actual last punch time, considering OT sessions
     * This shows the real clock-out time, not the computed regular shift end
     */
    public function getActualTimeOutAttribute(): ?string
    {
        // If no time_out at all, return null
        if (!$this->time_out) {
            return null;
        }

        // Check for OT sessions and return the latest punch time
        if ($this->ot_time_out_2) {
            return $this->ot_time_out_2;
        }
        if ($this->ot_time_out) {
            return $this->ot_time_out;
        }

        // No OT, return regular time_out
        return $this->time_out;
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

        // REGULAR SHIFT: Calculate hours from time_in to time_out (excluding break)
        $regularMinutes = $timeIn->diffInMinutes($timeOut) - $breakMinutes;
        $regularHours = $regularMinutes / 60;

        // Schedule settings (project overrides if available)
        $schedule = $this->getScheduleConfig($timeIn);
        $standardHours = $schedule['standard_hours'];

        // Cap regular hours at standard hours (8 hours typically)
        $this->regular_hours = min($regularHours, $standardHours);

        // OVERTIME: Calculate from OT sessions (if employee returned after regular shift)
        $overtimeMinutes = 0;

        // OT Session 1
        if ($this->ot_time_in && $this->ot_time_out) {
            $otTimeIn = Carbon::parse($dateString . ' ' . $this->ot_time_in);
            $otTimeOut = Carbon::parse($dateString . ' ' . $this->ot_time_out);
            if ($otTimeOut->lt($otTimeIn)) {
                $otTimeOut->addDay();
            }
            $overtimeMinutes += $otTimeIn->diffInMinutes($otTimeOut);
        }

        // OT Session 2
        if ($this->ot_time_in_2 && $this->ot_time_out_2) {
            $otTimeIn2 = Carbon::parse($dateString . ' ' . $this->ot_time_in_2);
            $otTimeOut2 = Carbon::parse($dateString . ' ' . $this->ot_time_out_2);
            if ($otTimeOut2->lt($otTimeIn2)) {
                $otTimeOut2->addDay();
            }
            $overtimeMinutes += $otTimeIn2->diffInMinutes($otTimeOut2);
        }

        // Convert OT to hours (floor to count only complete hours: 59min = 0h, 60min = 1h, 119min = 1h)
        // Only use explicit OT sessions tracked via ot_time_in/out fields
        // Do NOT calculate fallback OT from extended regular shift (causes incorrect OT for late time_out)
        $this->overtime_hours = floor($overtimeMinutes / 60);

        // Calculate night differential hours (10 PM - 6 AM) - for both regular and OT
        $this->night_differential_hours = $this->calculateNightDifferentialHours($timeIn, $timeOut);

        // Add night differential from OT sessions
        if ($this->ot_time_in && $this->ot_time_out) {
            $otTimeIn = Carbon::parse($dateString . ' ' . $this->ot_time_in);
            $otTimeOut = Carbon::parse($dateString . ' ' . $this->ot_time_out);
            if ($otTimeOut->lt($otTimeIn)) {
                $otTimeOut->addDay();
            }
            $this->night_differential_hours += $this->calculateNightDifferentialHours($otTimeIn, $otTimeOut);
        }
        if ($this->ot_time_in_2 && $this->ot_time_out_2) {
            $otTimeIn2 = Carbon::parse($dateString . ' ' . $this->ot_time_in_2);
            $otTimeOut2 = Carbon::parse($dateString . ' ' . $this->ot_time_out_2);
            if ($otTimeOut2->lt($otTimeIn2)) {
                $otTimeOut2->addDay();
            }
            $this->night_differential_hours += $this->calculateNightDifferentialHours($otTimeIn2, $otTimeOut2);
        }

        // Calculate late hours
        $this->late_hours = $this->calculateLateHours(
            $timeIn,
            $schedule['standard_time_in'],
            $schedule['grace_period_minutes']
        );

        // Force half-day status if late exceeds threshold (e.g., 1 hour and 1 minute)
        $halfDayLateMinutes = (int) config('payroll.attendance.half_day_late_minutes', 61);
        $halfDayHoursThreshold = (float) config('payroll.attendance.half_day_hours_threshold', 5.0);

        // Detect half-day based on:
        // 1. Late arrival exceeds threshold OR
        // 2. Total regular hours worked is less than half-day threshold (e.g., less than 5 hours)
        $isHalfDayDueToLate = $this->late_hours > 0 && ($this->late_hours * 60) >= $halfDayLateMinutes;
        $isHalfDayDueToShortHours = $regularHours > 0 && $regularHours < $halfDayHoursThreshold;

        if ($isHalfDayDueToLate || $isHalfDayDueToShortHours) {
            $this->status = 'half_day';
        } elseif ($this->late_hours > 0 && in_array($this->status, ['present', 'late'])) {
            $this->status = 'late';
        }

        // If half-day, cap regular hours to half-day
        if ($this->status === 'half_day') {
            $halfDayHours = $standardHours / 2;
            $this->regular_hours = min($regularHours, $halfDayHours);
        }

        // Calculate undertime based on project/department schedule settings
        if ($this->status === 'half_day') {
            // Half-day: calculate undertime as shortage from standard hours
            // This replaces the separate late/early undertime to avoid double penalty
            $shortage = $standardHours - $this->regular_hours;
            $this->undertime_hours = max(0, round($shortage, 4));
        } else {
            // Always use schedule-based calculation (project settings from Attendance Settings UI)
            $this->undertime_hours = $this->calculateUndertimeHoursForSchedule(
                $timeIn,
                $schedule
            );
        }

        // NOTE: Undertime-overtime offset is now applied at the PAYROLL PERIOD level,
        // not per day. This allows total OT across the period to cancel total UT.
        // See PayrollService::calculatePayrollItem() for the offset logic.

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
        $undertime = (float) ($this->undertime_hours ?? 0);
        $overtime = (float) ($this->overtime_hours ?? 0);

        if ($undertime > 0 && $overtime > 0) {
            // Offset the lesser amount from both
            $offsetAmount = min($undertime, $overtime);
            
            $this->undertime_hours = round($undertime - $offsetAmount, 4);
            $this->overtime_hours = round($overtime - $offsetAmount, 4);
        }
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
     * Calculate undertime hours based on late arrivals AND early departures
     * Uses the grace period from project/department schedule (attendance settings)
     */
    private function calculateUndertimeHoursForSchedule(Carbon $timeIn, array $schedule): float
    {
        $dateString = $this->attendance_date instanceof Carbon
            ? $this->attendance_date->format('Y-m-d')
            : $this->attendance_date;

        $standardTimeIn = Carbon::parse($dateString . ' ' . $schedule['standard_time_in']);
        $standardTimeOut = Carbon::parse($dateString . ' ' . $schedule['standard_time_out']);

        // Handle overnight shifts
        if ($standardTimeOut->lte($standardTimeIn)) {
            $standardTimeOut->addDay();
        }

        // Use grace period from project/department schedule (from attendance settings)
        $gracePeriodMinutes = (int) $schedule['grace_period_minutes'];

        $undertimeMinutes = 0;

        // Calculate late arrival undertime (only if late MORE than grace period)
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
            // If employee left before scheduled time out, count as undertime
            if ($timeOut->lt($standardTimeOut)) {
                $earlyDepartureMinutes = $timeOut->diffInMinutes($standardTimeOut);
                $undertimeMinutes += $earlyDepartureMinutes;
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
