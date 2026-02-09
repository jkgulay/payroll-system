<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use App\Models\Resignation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    /**
     * Import attendance from biometric device data
     * Accepts array of raw biometric records
     */
    public function importBiometric(array $records): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($records as $record) {
                try {
                    $result = $this->processBiometricRecord($record);
                    if ($result['action'] === 'created') {
                        $imported++;
                    } elseif ($result['action'] === 'updated') {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'record' => $record,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Biometric import error: ' . $e->getMessage(), ['record' => $record]);
                }
            }

            DB::commit();
            return [
                'imported' => $imported,
                'updated' => $updated,
                'errors' => count($errors),
                'error_details' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Biometric import failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process single biometric record
     */
    protected function processBiometricRecord(array $record): array
    {
        // Find employee by biometric ID or employee number
        $employee = Employee::where('employee_number', $record['employee_number'])
            ->orWhere('biometric_id', $record['biometric_id'] ?? null)
            ->first();

        if (!$employee) {
            throw new \Exception("Employee not found: {$record['employee_number']}");
        }

        $attendanceDate = Carbon::parse($record['date'])->format('Y-m-d');
        $timestamp = Carbon::parse($record['timestamp']);

        // Find existing attendance record
        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $attendanceDate)
            ->first();

        if (!$attendance) {
            // Create new attendance record
            $attendance = Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => $attendanceDate,
                'time_in' => $timestamp->format('H:i:s'),
                'status' => 'present',
                'is_manual_entry' => false,
                'approval_status' => 'approved',
            ]);
            return ['action' => 'created', 'attendance' => $attendance];
        }

        // Update existing record
        $this->updateAttendanceTime($attendance, $timestamp);
        return ['action' => 'updated', 'attendance' => $attendance];
    }

    /**
     * Update attendance with new timestamp
     * Determines if it's time in, time out, break, or OT
     * 
     * Logic:
     * 1. First punch = time_in
     * 2. Punches 3-6 hours after time_in = break_start/break_end
     * 3. Next punch = time_out (end of regular shift)
     * 4. Punches AFTER time_out = OT sessions (ot_time_in, ot_time_out, etc.)
     */
    protected function updateAttendanceTime(Attendance $attendance, Carbon $timestamp): void
    {
        $timeString = $timestamp->format('H:i:s');
        $dateString = $attendance->attendance_date instanceof Carbon
            ? $attendance->attendance_date->format('Y-m-d')
            : $attendance->attendance_date;

        // Get employee's schedule to determine if punch is during regular shift or OT
        $schedule = $this->getScheduleForEmployee($attendance->employee, $timestamp);
        $scheduledTimeOut = Carbon::parse($dateString . ' ' . $schedule['standard_time_out']);

        // 1. First punch = time_in
        if (!$attendance->time_in) {
            $attendance->time_in = $timeString;
        }
        // 2. Check if this is break time (3-6 hours after time_in, before scheduled time out)
        elseif (!$attendance->time_out) {
            $timeIn = Carbon::parse($dateString . ' ' . $attendance->time_in);
            $hoursAfterTimeIn = $timeIn->diffInHours($timestamp);

            // Break detection: 3-6 hours after time_in AND before scheduled time out
            if ($hoursAfterTimeIn >= 3 && $hoursAfterTimeIn <= 6 && $timestamp->lte($scheduledTimeOut)) {
                if (!$attendance->break_start) {
                    $attendance->break_start = $timeString;
                } elseif (!$attendance->break_end) {
                    $attendance->break_end = $timeString;
                } else {
                    // Multiple break sessions, update time_out
                    $attendance->time_out = $timeString;
                }
            } else {
                $attendance->time_out = $timeString;
            }
        }
        // 3. Break end punch
        elseif (!$attendance->break_end && $attendance->break_start) {
            $attendance->break_end = $timeString;
        }
        // 4. Check if this is OT (punch after regular time_out is set)
        elseif ($attendance->time_out) {
            // Any punch after time_out is already set is treated as OT session
            // Track OT sessions (ot_time_in, ot_time_out pairs)
            if (!$attendance->ot_time_in) {
                $attendance->ot_time_in = $timeString;
            } elseif (!$attendance->ot_time_out) {
                $attendance->ot_time_out = $timeString;
            } elseif (!$attendance->ot_time_in_2) {
                $attendance->ot_time_in_2 = $timeString;
            } elseif (!$attendance->ot_time_out_2) {
                $attendance->ot_time_out_2 = $timeString;
            } else {
                // More than 2 OT sessions, update last OT out
                $attendance->ot_time_out_2 = $timeString;
            }
        }

        $attendance->save();
        $attendance->calculateHours();
    }

    /**
     * Get schedule configuration for an employee
     */
    protected function getScheduleForEmployee($employee, Carbon $date): array
    {
        $defaultTimeIn = config('payroll.attendance.standard_time_in', '07:30');
        $defaultTimeOut = config('payroll.attendance.standard_time_out', '17:00');
        $defaultGrace = (int) config('payroll.attendance.grace_period_minutes', 0);

        if (!$employee || !$employee->project) {
            return [
                'standard_time_in' => $defaultTimeIn,
                'standard_time_out' => $defaultTimeOut,
                'grace_period_minutes' => $defaultGrace,
            ];
        }

        $project = $employee->project;
        return [
            'standard_time_in' => $project->time_in ?: $defaultTimeIn,
            'standard_time_out' => $project->time_out ?: $defaultTimeOut,
            'grace_period_minutes' => $project->grace_period_minutes !== null
                ? (int) $project->grace_period_minutes
                : $defaultGrace,
        ];
    }

    /**
     * Create manual attendance entry
     */
    public function createManualEntry(array $data): Attendance
    {
        $requiresApproval = $data['requires_approval'] ?? true;

        $employee = Employee::find($data['employee_id']);
        $computedStatus = $employee
            ? $this->determineStatus(
                $employee,
                $data['attendance_date'],
                $data['time_in'] ?? null,
                $data['time_out'] ?? null
            )
            : 'absent';

        $attendance = Attendance::create([
            'employee_id' => $data['employee_id'],
            'attendance_date' => $data['attendance_date'],
            'time_in' => $data['time_in'],
            'time_out' => $data['time_out'] ?? null,
            'break_start' => $data['break_start'] ?? null,
            'break_end' => $data['break_end'] ?? null,
            'status' => $computedStatus,
            'is_manual_entry' => true,
            'manual_reason' => $data['reason'] ?? null,
            'approval_status' => $requiresApproval ? 'pending' : 'approved',
            'is_approved' => !$requiresApproval,
            'approved_by' => $requiresApproval ? null : ($data['created_by'] ?? null),
            'approved_at' => $requiresApproval ? null : now(),
            'created_by' => $data['created_by'] ?? null,
        ]);

        $attendance->calculateHours();
        return $attendance->fresh();
    }

    /**
     * Update attendance record
     */
    public function updateAttendance(Attendance $attendance, array $data, int $userId): Attendance
    {
        $oldData = $attendance->toArray();

        $timeIn = $data['time_in'] ?? $attendance->time_in;
        $timeOut = $data['time_out'] ?? $attendance->time_out;
        $computedStatus = $attendance->employee
            ? $this->determineStatus(
                $attendance->employee,
                $attendance->attendance_date,
                $timeIn,
                $timeOut
            )
            : 'absent';

        $data['status'] = $computedStatus;

        $attendance->update($data);
        $attendance->is_edited = true;
        $attendance->edited_by = $userId;
        $attendance->edited_at = now();
        $attendance->approval_status = 'pending';
        $attendance->is_approved = false;
        $attendance->is_rejected = false;
        $attendance->approved_by = null;
        $attendance->approved_at = null;
        $attendance->rejection_reason = null;
        $attendance->rejected_by = null;
        $attendance->rejected_at = null;
        $attendance->approval_notes = null;
        $attendance->save();

        $attendance->calculateHours();

        // Log the change
        Log::info('Attendance updated', [
            'attendance_id' => $attendance->id,
            'old_data' => $oldData,
            'new_data' => $attendance->toArray(),
            'edited_by' => $userId,
        ]);

        return $attendance;
    }

    private function determineStatus(
        Employee $employee,
        string $attendanceDate,
        ?string $timeIn,
        ?string $timeOut
    ): string {
        $date = Carbon::parse($attendanceDate)->toDateString();

        $hasApprovedLeave = EmployeeLeave::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('leave_date_from', '<=', $date)
            ->whereDate('leave_date_to', '>=', $date)
            ->exists();

        if ($hasApprovedLeave) {
            return 'leave';
        }

        $hasResignation = Resignation::where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'completed'])
            ->where(function ($query) use ($date) {
                $query->whereDate('effective_date', '<=', $date)
                    ->orWhereDate('last_working_day', '<=', $date);
            })
            ->exists();

        if ($hasResignation) {
            return 'absent';
        }

        if ($timeIn || $timeOut) {
            return 'present';
        }

        return 'absent';
    }

    /**
     * Approve attendance corrections
     */
    public function approveAttendance(Attendance $attendance, int $userId): bool
    {
        $attendance->update([
            'approval_status' => 'approved',
            'is_approved' => true,
            'is_rejected' => false,
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        return true;
    }

    /**
     * Reject attendance corrections
     */
    public function rejectAttendance(Attendance $attendance, string $reason, int $userId): bool
    {
        $attendance->update([
            'approval_status' => 'rejected',
            'is_approved' => false,
            'is_rejected' => true,
            'rejection_reason' => $reason,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return true;
    }

    /**
     * Get attendance summary for employee and date range
     */
    public function getAttendanceSummary(int $employeeId, string $startDate, string $endDate): array
    {
        $attendance = Attendance::where('employee_id', $employeeId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        return [
            'total_days' => $attendance->count(),
            'present_days' => $attendance->where('status', 'present')->count(),
            'absent_days' => $attendance->where('status', 'absent')->count(),
            'leave_days' => $attendance->where('status', 'leave')->count(),
            'total_regular_hours' => $attendance->sum('regular_hours'),
            'total_overtime_hours' => $attendance->sum('overtime_hours'),
            'total_undertime_hours' => $attendance->sum('undertime_hours'),
            'total_late_hours' => $attendance->sum('late_hours'),
            'total_night_differential_hours' => $attendance->sum('night_differential_hours'),
        ];
    }

    /**
     * Mark attendance as absent for missing records
     */
    public function markAbsences(string $date, ?array $employeeIds = null): int
    {
        $query = Employee::active();

        if ($employeeIds) {
            $query->whereIn('id', $employeeIds);
        }

        $employees = $query->get();
        $markedAbsent = 0;

        foreach ($employees as $employee) {
            // Check if attendance record exists
            $exists = Attendance::where('employee_id', $employee->id)
                ->where('attendance_date', $date)
                ->exists();

            if (!$exists) {
                // Check if it's a rest day or holiday
                $dayOfWeek = Carbon::parse($date)->dayOfWeek;
                $restDay = config('payroll.rest_day', 0);
                if ($dayOfWeek == $restDay) {
                    continue;
                }

                // Create absent record
                Attendance::create([
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'status' => 'absent',
                    'is_manual_entry' => true,
                    'manual_reason' => 'Auto-marked absent',
                    'approval_status' => 'approved',
                ]);

                $markedAbsent++;
            }
        }

        return $markedAbsent;
    }
}
