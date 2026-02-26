<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PunchRecordImportService
{
    /**
     * Import punch records from biometric device export.
     *
     * Expected XLS format (one row per punch event):
     *   Staff Code | Name | Punch Date          | Punch Type   | Punch Address | Device Name | Punch Photo | Remark
     *   ZS060594   | ...  | 2026-02-02 17:18    | Device Punch |               | C.P Garcia  |             |
     *
     * Rows for the same employee+date are grouped automatically and processed
     * using the standard punch-assignment logic (time_in → break → time_out → OT).
     */
    public function importPunchRecords(array $punchData): array
    {
        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        if (empty($punchData)) {
            return ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'error_details' => []];
        }

        // ── Group all rows by (staffCode → date → [HH:mm, ...]) ──────────────
        $grouped = [];
        $deviceNames = []; // Capture first non-empty device name per employee+date
        foreach ($punchData as $row) {
            $staffCode    = trim($row['Staff Code'] ?? '');
            $rawPunchDate = trim($row['Punch Date'] ?? '');

            if ($staffCode === '' || $rawPunchDate === '') {
                $skipped++;
                continue;
            }

            // Normalize Excel date artifacts:
            // PhpSpreadsheet may produce "2026-02-03 00:00:00 06:49:00" when a
            // cell stores date + time separately. Extract the YYYY-MM-DD part and
            // the LAST HH:MM[:SS] part so Carbon gets a clean datetime string.
            if (substr_count($rawPunchDate, ':') >= 2) {
                $datePart = '';
                $timePart = '';
                if (preg_match('/\d{4}-\d{2}-\d{2}/', $rawPunchDate, $dm)) {
                    $datePart = $dm[0];
                }
                if (preg_match_all('/\d{2}:\d{2}(?::\d{2})?/', $rawPunchDate, $tm)) {
                    // Use the LAST time match — midnight 00:00:00 always comes first
                    $timePart = end($tm[0]);
                }
                if ($datePart && $timePart) {
                    $rawPunchDate = $datePart . ' ' . $timePart;
                }
            }

            try {
                $punchDt = Carbon::parse($rawPunchDate);
            } catch (\Exception $e) {
                $skipped++;
                continue;
            }

            $dateKey = $punchDt->format('Y-m-d');
            $timeKey = $punchDt->format('H:i');

            $grouped[$staffCode][$dateKey][] = $timeKey;

            // Store first non-empty device name seen for this employee+date
            if (!isset($deviceNames[$staffCode][$dateKey])) {
                $deviceName = trim($row['Device Name'] ?? '');
                if ($deviceName !== '') {
                    $deviceNames[$staffCode][$dateKey] = $deviceName;
                }
            }
        }

        Log::info('Punch import starting', [
            'total_rows'    => count($punchData),
            'unique_employees' => count($grouped),
        ]);

        // ── Process each employee's punches per date ──────────────────────────
        foreach ($grouped as $staffCode => $dates) {
            $employee = Employee::where('employee_number', $staffCode)->first();

            if (!$employee) {
                $errors[] = [
                    'staff_code' => $staffCode,
                    'error' => 'Employee not found. Please import staff information first.',
                ];
                continue;
            }

            foreach ($dates as $dateStr => $times) {
                // Sort times chronologically before passing to processPunchRecord
                sort($times);
                $attendanceDate = Carbon::parse($dateStr);
                // processPunchRecord expects newline-separated HH:MM strings
                $timeEntries = implode("\n", $times);
                $deviceName = $deviceNames[$staffCode][$dateStr] ?? null;

                DB::beginTransaction();
                try {
                    $result = $this->processPunchRecord($employee, $attendanceDate, $timeEntries, $deviceName);
                    DB::commit();

                    if ($result['action'] === 'created')      $imported++;
                    elseif ($result['action'] === 'updated')  $updated++;
                    else                                       $skipped++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = [
                        'staff_code' => $staffCode,
                        'date'       => $dateStr,
                        'error'      => $e->getMessage(),
                    ];
                    Log::error("Punch import error [{$staffCode} {$dateStr}]: " . $e->getMessage());
                }
            }
        }

        Log::info('Punch import completed', [
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'failed'   => count($errors),
        ]);

        return [
            'imported'     => $imported,
            'updated'      => $updated,
            'skipped'      => $skipped,
            'failed'       => count($errors),
            'error_details' => $errors,
        ];
    }

    /**
     * Process a single punch record for a specific date
     * Time entries can be multiple lines like "06:10\n11:39\n11:51\n17:02"
     * Now processes each punch individually to properly track OT sessions
     */
    protected function processPunchRecord(Employee $employee, Carbon $attendanceDate, string $timeEntries, ?string $deviceName = null): array
    {
        // Parse time entries (multiple times may be on separate lines or separated by \n)
        $times = [];
        $lines = preg_split('/[\r\n]+/', trim($timeEntries));

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $line, $matches)) {
                $hour = (int)$matches[1];
                $minute = (int)$matches[2];

                // Skip obvious errors: 00:00-00:05 (midnight punches are usually biometric errors)
                if ($hour == 0 && $minute <= 5) {
                    continue;
                }

                $times[] = Carbon::create($attendanceDate->year, $attendanceDate->month, $attendanceDate->day, $hour, $minute);
            }
        }

        // If no valid times found, skip
        if (empty($times)) {
            return ['action' => 'skipped'];
        }

        // Sort times chronologically
        usort($times, function ($a, $b) {
            return $a->timestamp - $b->timestamp;
        });

        // Check if attendance record already exists
        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $attendanceDate->format('Y-m-d'))
            ->first();

        $action = $attendance ? 'updated' : 'created';

        // Create new attendance if doesn't exist
        if (!$attendance) {
            $attendance = Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => $attendanceDate->format('Y-m-d'),
                'status' => 'present',
                'is_manual_entry' => false,
                'device_name' => $deviceName,
                'approval_status' => 'approved',
                'is_approved' => true,
                'created_by' => auth()->id() ?? 1,
            ]);
        } else {
            // Clear ALL time and calculated fields for clean re-import
            if ($deviceName !== null) {
                $attendance->device_name = $deviceName;
            }
            $attendance->time_in = null;
            $attendance->time_out = null;
            $attendance->break_start = null;
            $attendance->break_end = null;
            $attendance->ot_time_in = null;
            $attendance->ot_time_out = null;
            $attendance->ot_time_in_2 = null;
            $attendance->ot_time_out_2 = null;
            $attendance->regular_hours = 0;
            $attendance->overtime_hours = 0;
            $attendance->undertime_hours = 0;
            $attendance->status = 'present'; // Reset status, will be recalculated
        }

        // Get employee's schedule to detect OT sessions
        $schedule = $this->getScheduleForEmployee($employee, $attendanceDate);
        $scheduledTimeOut = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $schedule['standard_time_out']);

        // Get configurable punch detection settings
        $breakMinHours = (int) config('payroll.attendance.break_detection_min_hours', 3);
        $breakMaxHours = (int) config('payroll.attendance.break_detection_max_hours', 6);
        $smartDetectionMinutes = (int) config('payroll.attendance.smart_detection_window_minutes', 120);
        $otGracePeriodMinutes = (int) config('payroll.attendance.ot_grace_period_minutes', 15);

        // Process each punch time individually to properly assign to regular shift or OT sessions
        foreach ($times as $timestamp) {
            $timeString = $timestamp->format('H:i:s');

            // 1. First punch = time_in
            if (!$attendance->time_in) {
                $attendance->time_in = $timeString;
            }
            // 2. Second punch - could be break_start (if configured hours after time_in) or early time_out
            elseif (!$attendance->break_start && !$attendance->time_out) {
                $timeIn = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $attendance->time_in);
                $hoursAfterTimeIn = $timeIn->diffInHours($timestamp);

                // If within configured hours after time_in, likely break start
                if ($hoursAfterTimeIn >= $breakMinHours && $hoursAfterTimeIn <= $breakMaxHours) {
                    $attendance->break_start = $timeString;
                } else {
                    // Otherwise it's time_out (no automatic OT splitting for 2-punch scenarios)
                    // Employee must punch in explicitly for OT to get OT credit
                    $attendance->time_out = $timeString;
                }
            }
            // 3. Third punch - if we have break_start, check if this is break_end or time_out
            elseif ($attendance->break_start && !$attendance->break_end && !$attendance->time_out) {
                // Smart detection: if this punch is within configured window of scheduled time_out, treat as time_out
                // Otherwise treat as break_end (expecting a 4th punch)
                $minutesBeforeScheduledOut = $scheduledTimeOut->diffInMinutes($timestamp, false);

                // If punch is within configured window of scheduled time_out, assume it's time_out (not break_end)
                if ($minutesBeforeScheduledOut >= -$smartDetectionMinutes) {
                    // This is the end of day punch
                    $attendance->time_out = $timeString;
                } else {
                    // Still early in the day, this is break_end
                    $attendance->break_end = $timeString;
                }
            }
            // 4. Fourth punch - if we had break, this is time_out
            elseif ($attendance->break_start && $attendance->break_end && !$attendance->time_out) {
                // Set time_out without automatic OT splitting
                // Employee must punch in explicitly for OT (5th+ punch) to get OT credit
                $attendance->time_out = $timeString;
            }
            // 5. Fifth+ punch - check if OT (>configured grace period after time_out)
            elseif ($attendance->time_out) {
                $actualTimeOut = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $attendance->time_out);

                // If current punch is AFTER time_out + configured grace period, it's an OT session
                if ($timestamp->gt($actualTimeOut->copy()->addMinutes($otGracePeriodMinutes))) {
                    // Track OT sessions (up to 2 sessions)
                    // OT starts from actual time_out (not scheduled), respects employee's actual punch times
                    if (!$attendance->ot_time_in) {
                        // First OT punch - set OT session starting from actual time_out
                        $attendance->ot_time_in = $attendance->time_out;
                        $attendance->ot_time_out = $timeString;
                    } elseif (!$attendance->ot_time_out) {
                        // Second OT punch - close first OT session
                        $attendance->ot_time_out = $timeString;
                    } elseif (!$attendance->ot_time_in_2) {
                        // Third OT punch - start second OT session
                        $attendance->ot_time_in_2 = $timeString;
                    } elseif (!$attendance->ot_time_out_2) {
                        // Fourth OT punch - close second OT session
                        $attendance->ot_time_out_2 = $timeString;
                    } else {
                        // More than 2 OT sessions, update last OT out
                        $attendance->ot_time_out_2 = $timeString;
                    }
                } else {
                    // Within grace period - adjust regular time_out
                    $attendance->time_out = $timeString;
                }
            }
        }

        // If employee has time_in but no time_out, mark as half_day (incomplete attendance)
        // Don't auto-complete - we don't know when they actually left
        if ($attendance->time_in && !$attendance->time_out) {
            $attendance->status = 'half_day';
            $attendance->regular_hours = 4; // Give half day credit (4 hours)
        }

        // Save and calculate hours
        $attendance->save();

        // Only calculate hours if we have complete attendance (both time_in and time_out)
        if ($attendance->time_in && $attendance->time_out) {
            $attendance->calculateHours();
        }

        // Auto-adjust status based on actual attendance
        $this->adjustAttendanceStatus($attendance);

        return ['action' => $action, 'attendance' => $attendance->fresh()];
    }

    /**
     * Adjust attendance status based on actual punch records and hours worked
     */
    protected function adjustAttendanceStatus(Attendance $attendance): void
    {
        // If no time_in at all, mark as absent
        if (!$attendance->time_in) {
            $attendance->status = 'absent';
            $attendance->save();
            return;
        }

        // If no time_out, status already set to half_day above - don't change it
        if (!$attendance->time_out) {
            return;
        }

        // If both time_in and time_out exist, check regular_hours to determine status
        // Less than 5 hours = half_day, >= 5 hours = present
        if ($attendance->regular_hours > 0 && $attendance->regular_hours < 5) {
            $attendance->status = 'half_day';
        } elseif ($attendance->regular_hours >= 5) {
            $attendance->status = 'present';
        }

        // Check if late (more than grace period)
        $dateStr = $attendance->attendance_date instanceof Carbon
            ? $attendance->attendance_date->format('Y-m-d')
            : $attendance->attendance_date;
        $timeIn = Carbon::parse($dateStr . ' ' . $attendance->time_in);
        $schedule = $this->getScheduleForEmployee($attendance->employee, Carbon::parse($dateStr));
        $scheduledTimeIn = Carbon::parse($dateStr . ' ' . $schedule['standard_time_in']);
        $gracePeriodMinutes = (int) $schedule['grace_period_minutes'];

        if ($attendance->status === 'present' && $timeIn->gt($scheduledTimeIn->copy()->addMinutes($gracePeriodMinutes))) {
            $attendance->status = 'late';
        }

        $attendance->save();
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
}
