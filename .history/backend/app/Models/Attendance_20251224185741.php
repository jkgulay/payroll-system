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
        'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'time_in' => 'datetime',
        'time_out' => 'datetime',
        'break_start' => 'datetime',
        'break_end' => 'datetime',
        'regular_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'undertime_hours' => 'decimal:2',
        'late_hours' => 'decimal:2',
        'night_differential_hours' => 'decimal:2',
        'is_holiday' => 'boolean',
        'is_manual_entry' => 'boolean',
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

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

        $timeIn = Carbon::parse($this->time_in);
        $timeOut = Carbon::parse($this->time_out);

        // Calculate break duration
        $breakMinutes = 0;
        if ($this->break_start && $this->break_end) {
            $breakStart = Carbon::parse($this->break_start);
            $breakEnd = Carbon::parse($this->break_end);
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

        // Calculate undertime
        if ($totalHours < $standardHours && $this->status === 'present') {
            $this->undertime_hours = $standardHours - $totalHours;
        }

        $this->save();
    }

    private function calculateNightDifferentialHours(Carbon $timeIn, Carbon $timeOut): float
    {
        $nightStart = Carbon::parse($timeIn->toDateString() . ' 22:00:00');
        $nightEnd = Carbon::parse($timeIn->toDateString() . ' 06:00:00')->addDay();

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
        // Assuming standard time in is 8:00 AM (configurable)
        $standardTimeIn = Carbon::parse($timeIn->toDateString() . ' 08:00:00');

        if ($timeIn->gt($standardTimeIn)) {
            $lateMinutes = $standardTimeIn->diffInMinutes($timeIn);
            return round($lateMinutes / 60, 2);
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
}
