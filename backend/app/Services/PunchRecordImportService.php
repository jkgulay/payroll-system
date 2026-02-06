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
     * Import punch records from biometric device export
     * Format: Staff Code, Name, 12-01, 12-02, ... (dates as columns with time entries)
     * Also supports: Staff Code, Name, 01, 02, ... (day-only columns with month from parameter)
     * Each date column may contain multiple time entries separated by newlines
     */
    public function importPunchRecords(array $punchData, ?int $year = null, ?int $month = null): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $debugInfo = [];

        // Default to current year if not provided
        if (!$year) {
            $year = now()->year;
        }

        // Try to detect month from column headers if not provided
        $detectedMonth = $this->detectMonthFromColumns(array_keys($punchData[0] ?? []));

        // Debug: Log what we're working with
        Log::info('Import starting', [
            'total_rows' => count($punchData),
            'year' => $year,
            'month' => $month,
            'detected_month' => $detectedMonth,
            'sample_columns' => array_keys($punchData[0] ?? [])
        ]);

        // Process each row independently to avoid PostgreSQL transaction abort cascading
        foreach ($punchData as $index => $row) {
            try {
                // Skip if no staff code
                if (empty($row['Staff Code'])) {
                    $skipped++;
                    continue;
                }

                $staffCode = trim($row['Staff Code']);

                // Find employee by staff code
                $employee = Employee::where('employee_number', $staffCode)->first();

                if (!$employee) {
                    Log::warning("Employee not found: {$staffCode}");
                    $errors[] = [
                        'row' => $index + 1,
                        'staff_code' => $staffCode,
                        'error' => 'Employee not found. Please import staff information first.',
                    ];
                    continue;
                }

                Log::info("Processing employee: {$employee->first_name} {$employee->last_name} ({$staffCode})");

                // Process each date column for this employee in a single transaction
                DB::beginTransaction();

                try {
                    $rowImported = 0;
                    $rowUpdated = 0;

                    foreach ($row as $key => $value) {
                        // Skip non-date columns (case-insensitive check)
                        $keyLower = strtolower(trim($key ?? ''));
                        if (in_array($keyLower, ['staff code', 'name', 'staff_code', 'employee_number', 'employee number', ''])) {
                            continue;
                        }

                        // Skip null or empty column headers
                        if ($key === null || trim($key) === '') {
                            continue;
                        }

                        $dateInfo = $this->parseDateFromColumnHeader($key, $year, $month, $detectedMonth);

                        if ($dateInfo) {
                            try {
                                $attendanceDate = Carbon::create($dateInfo['year'], $dateInfo['month'], $dateInfo['day']);

                                // Skip if no time entries
                                if (empty($value) || (is_string($value) && trim($value) === '') || $value === null) {
                                    continue;
                                }

                                // Convert value to string if it's not already
                                $timeValue = is_string($value) ? $value : (string)$value;

                                Log::info("Processing date column", [
                                    'employee' => $staffCode,
                                    'column' => $key,
                                    'date' => $attendanceDate->format('Y-m-d'),
                                    'times' => substr($timeValue, 0, 100)
                                ]);

                                $result = $this->processPunchRecord($employee, $attendanceDate, $timeValue);

                                if ($result['action'] === 'created') {
                                    $rowImported++;
                                } elseif ($result['action'] === 'updated') {
                                    $rowUpdated++;
                                }
                            } catch (\Exception $e) {
                                Log::warning("Invalid date: {$key} for year {$year}, error: " . $e->getMessage());
                            }
                        } else {
                            // Date column not recognized
                            if (!in_array(strtolower(trim($key)), ['staff code', 'name', ''])) {
                                Log::debug("Column not recognized as date: {$key}");
                            }
                        }
                    }

                    DB::commit();
                    $imported += $rowImported;
                    $updated += $rowUpdated;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $errors[] = [
                        'row' => $index + 1,
                        'staff_code' => $staffCode,
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Punch record import error for row ' . ($index + 1) . ': ' . $e->getMessage());
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'staff_code' => $row['Staff Code'] ?? 'N/A',
                    'error' => $e->getMessage(),
                ];
                Log::error('Punch record import error: ' . $e->getMessage(), ['row' => $row]);
            }
        }

        Log::info('Import completed', [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => count($errors)
        ]);

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => count($errors),
            'error_details' => $errors,
        ];
    }

    /**
     * Parse date from column header - supports multiple formats:
     * - "MM-DD" format (e.g., "01-15" means January 15)
     * - "DD" format (e.g., "15" or "01" means day 15 or day 1, month from parameter)
     * - Numeric day only (e.g., 1, 2, 15)
     */
    protected function parseDateFromColumnHeader(string $key, int $year, ?int $providedMonth, ?int $detectedMonth): ?array
    {
        $key = trim($key);

        // Format 1: "MM-DD" (e.g., "01-15", "12-01")
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $key, $matches)) {
            $monthNum = (int)$matches[1];
            $dayNum = (int)$matches[2];

            // Validate day range
            if ($dayNum < 1 || $dayNum > 31) {
                return null;
            }

            // Override month if provided
            if ($providedMonth) {
                $monthNum = $providedMonth;
            }

            // Validate month range
            if ($monthNum < 1 || $monthNum > 12) {
                return null;
            }

            return [
                'year' => $year,
                'month' => $monthNum,
                'day' => $dayNum,
            ];
        }

        // Format 2: Day-only number (e.g., "01", "15", "1", "31")
        // This handles Yunnat biometric exports that use day numbers as column headers
        if (preg_match('/^(\d{1,2})$/', $key, $matches)) {
            $dayNum = (int)$matches[1];

            // Validate day range (1-31)
            if ($dayNum < 1 || $dayNum > 31) {
                return null;
            }

            // Month must be provided or detected for day-only format
            $monthNum = $providedMonth ?? $detectedMonth ?? now()->month;

            return [
                'year' => $year,
                'month' => $monthNum,
                'day' => $dayNum,
            ];
        }

        return null;
    }

    /**
     * Detect month from column headers if they are in MM-DD format
     */
    protected function detectMonthFromColumns(array $headers): ?int
    {
        foreach ($headers as $header) {
            if ($header === null) {
                continue;
            }
            $header = trim((string)$header);
            if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $header, $matches)) {
                return (int)$matches[1];
            }
        }
        return null;
    }

    /**
     * Process a single punch record for a specific date
     * Time entries can be multiple lines like "06:10\n11:39\n11:51\n17:02"
     * Now processes each punch individually to properly track OT sessions
     */
    protected function processPunchRecord(Employee $employee, Carbon $attendanceDate, string $timeEntries): array
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
                'approval_status' => 'approved',
                'is_approved' => true,
                'created_by' => auth()->id() ?? 1,
            ]);
        } else {
            // Clear ALL time and calculated fields for clean re-import
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
        $timeIn = Carbon::parse($attendance->attendance_date . ' ' . $attendance->time_in);
        $schedule = $this->getScheduleForEmployee($attendance->employee, Carbon::parse($attendance->attendance_date));
        $scheduledTimeIn = Carbon::parse($attendance->attendance_date . ' ' . $schedule['standard_time_in']);
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

    /**
     * Parse date from column header - supports multiple formats:
     * - "MM-DD" format (e.g., "01-15" means January 15)
     * - "DD" format (e.g., "15" or "01" means day only)
     */
    public function parseDateColumn(string $columnHeader): ?array
    {
        $columnHeader = trim($columnHeader);

        // Format 1: "MM-DD" (e.g., "01-15", "12-01")
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $columnHeader, $matches)) {
            return [
                'month' => (int)$matches[1],
                'day' => (int)$matches[2],
            ];
        }

        // Format 2: Day-only number (e.g., "01", "15", "1", "31")
        if (preg_match('/^(\d{1,2})$/', $columnHeader, $matches)) {
            $dayNum = (int)$matches[1];
            if ($dayNum >= 1 && $dayNum <= 31) {
                return [
                    'month' => null, // Month needs to be provided separately
                    'day' => $dayNum,
                ];
            }
        }

        return null;
    }
}
