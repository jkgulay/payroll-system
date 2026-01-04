<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
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
     * Determines if it's time in, time out, or break
     */
    protected function updateAttendanceTime(Attendance $attendance, Carbon $timestamp): void
    {
        $timeString = $timestamp->format('H:i:s');

        // Logic to determine if this is time_in, time_out, break_start, or break_end
        if (!$attendance->time_in) {
            $attendance->time_in = $timeString;
        } elseif (!$attendance->time_out) {
            // Check if this could be break start - parse time_in with date for comparison
            $timeIn = Carbon::parse($attendance->attendance_date . ' ' . $attendance->time_in);
            if ($timeIn->diffInHours($timestamp) >= 3 && $timeIn->diffInHours($timestamp) <= 6) {
                if (!$attendance->break_start) {
                    $attendance->break_start = $timeString;
                } elseif (!$attendance->break_end) {
                    $attendance->break_end = $timeString;
                } else {
                    $attendance->time_out = $timeString;
                }
            } else {
                $attendance->time_out = $timeString;
            }
        } elseif (!$attendance->break_end && $attendance->break_start) {
            $attendance->break_end = $timeString;
        } else {
            // Update time out with latest timestamp
            $attendance->time_out = $timeString;
        }

        $attendance->save();
        $attendance->calculateHours();
    }

    /**
     * Create manual attendance entry
     */
    public function createManualEntry(array $data): Attendance
    {
        $requiresApproval = $data['requires_approval'] ?? true;

        $attendance = Attendance::create([
            'employee_id' => $data['employee_id'],
            'attendance_date' => $data['attendance_date'],
            'time_in' => $data['time_in'],
            'time_out' => $data['time_out'] ?? null,
            'break_start' => $data['break_start'] ?? null,
            'break_end' => $data['break_end'] ?? null,
            'status' => $data['status'] ?? 'present',
            'is_manual_entry' => true,
            'manual_reason' => $data['reason'] ?? null,
            'approval_status' => $requiresApproval ? 'pending' : 'approved',
            'is_approved' => !$requiresApproval,
            'approved_by' => $requiresApproval ? null : ($data['created_by'] ?? null),
            'approved_at' => $requiresApproval ? null : now(),
            'created_by' => $data['created_by'] ?? null,
        ]);

        $attendance->calculateHours();
        return $attendance;
    }

    /**
     * Update attendance record
     */
    public function updateAttendance(Attendance $attendance, array $data, int $userId): Attendance
    {
        $oldData = $attendance->toArray();

        $attendance->update($data);
        $attendance->is_edited = true;
        $attendance->edited_by = $userId;
        $attendance->edited_at = now();
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

    /**
     * Approve attendance corrections
     */
    public function approveAttendance(Attendance $attendance, int $userId): bool
    {
        $attendance->update([
            'approval_status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
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
