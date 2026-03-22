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
        return $this->importPunchRecordsWithProgress($punchData);
    }

    /**
     * Import punch records with real-time progress callback.
     *
     * @param callable|null $onProgress fn($current, $total, $detail)
     */
    public function importPunchRecordsWithProgress(array $punchData, ?callable $onProgress = null): array
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
        $deviceCounts = []; // Track device punch counts per employee+date
        $punchDeviceMap = []; // staffCode → dateKey → [HH:MM → deviceName]
        $dateRange = ['min' => null, 'max' => null]; // Track date range for bulk queries

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

            // Track date range
            if ($dateRange['min'] === null || $dateKey < $dateRange['min']) {
                $dateRange['min'] = $dateKey;
            }
            if ($dateRange['max'] === null || $dateKey > $dateRange['max']) {
                $dateRange['max'] = $dateKey;
            }

            // Track device punch counts per employee+date for majority-device resolution
            $deviceName = trim($row['Device Name'] ?? '');
            if ($deviceName !== '') {
                $deviceCounts[$staffCode][$dateKey][$deviceName] =
                    ($deviceCounts[$staffCode][$dateKey][$deviceName] ?? 0) + 1;
                $punchDeviceMap[$staffCode][$dateKey][$timeKey] = $deviceName;
            }
        }

        // Resolve to majority device per employee+date (most punches wins)
        $deviceNames = [];
        foreach ($deviceCounts as $sc => $dates) {
            foreach ($dates as $dk => $counts) {
                arsort($counts);
                $deviceNames[$sc][$dk] = array_key_first($counts);
            }
        }

        Log::info('Punch import starting', [
            'total_rows'    => count($punchData),
            'unique_employees' => count($grouped),
        ]);

        // Count total employee+date combinations for progress tracking
        $totalTasks = 0;
        foreach ($grouped as $dates) {
            $totalTasks += count($dates);
        }
        $currentTask = 0;

        // ── OPTIMIZATION 1: Bulk load all needed employees at once ──────────
        $staffCodes = array_keys($grouped);
        $employees = Employee::whereIn('employee_number', $staffCodes)
            ->get()
            ->keyBy('employee_number');
        $employeeIds = $employees->pluck('id', 'employee_number')->toArray();

        // ── OPTIMIZATION 2: Bulk load all existing attendances for date range ──
        $existingAttendances = [];
        if ($dateRange['min'] && $dateRange['max']) {
            $attendances = Attendance::whereBetween('attendance_date', [$dateRange['min'], $dateRange['max']])
                ->whereIn('employee_id', $employeeIds)
                ->get();
            foreach ($attendances as $att) {
                $existingAttendances[$att->employee_id][$att->attendance_date] = $att;
            }
        }

        // ── OPTIMIZATION 3: Get schedules for all employees in one query ─────
        $employeeSchedules = [];
        $employeeObjects = Employee::whereIn('employee_number', $staffCodes)
            ->with('project')
            ->get()
            ->keyBy('employee_number');
        foreach ($employeeObjects as $empNum => $emp) {
            $employeeSchedules[$empNum] = $this->getScheduleForEmployee($emp, null);
        }

        // ── Process each employee's punches per date ──────────────────────────
        foreach ($grouped as $staffCode => $dates) {
            $employee = $employees[$staffCode] ?? null;

            if (!$employee) {
                $errors[] = [
                    'staff_code' => $staffCode,
                    'error' => 'Employee not found. Please import staff information first.',
                ];
                $currentTask += count($dates);
                if ($onProgress) {
                    $onProgress($currentTask, $totalTasks, "Skipped {$staffCode} (not found)");
                }
                continue;
            }

            $empName = $employee->first_name . ' ' . $employee->last_name;
            $schedule = $employeeSchedules[$staffCode] ?? [
                'standard_time_in' => config('payroll.attendance.standard_time_in', '07:30'),
                'standard_time_out' => config('payroll.attendance.standard_time_out', '17:00'),
            ];
            $empId = $employee->id;

            foreach ($dates as $dateStr => $times) {
                $currentTask++;
                // Sort times chronologically before passing to processPunchRecord
                sort($times);
                $attendanceDate = Carbon::parse($dateStr);
                // processPunchRecord expects newline-separated HH:MM strings
                $timeEntries = implode("\n", $times);
                $deviceName = $deviceNames[$staffCode][$dateStr] ?? null;
                $punchMap = $punchDeviceMap[$staffCode][$dateStr] ?? [];

                // ── OPTIMIZATION 4: Use pre-loaded attendance instead of query ──
                $existingAtt = $existingAttendances[$empId][$dateStr] ?? null;

                try {
                    $result = $this->processPunchRecordOptimized(
                        $employee,
                        $attendanceDate,
                        $timeEntries,
                        $deviceName,
                        $punchMap,
                        $existingAtt,
                        $schedule
                    );

                    // Update the cache so subsequent dates for same employee use updated data
                    if ($result['attendance']) {
                        $existingAttendances[$empId][$dateStr] = $result['attendance'];
                    }

                    if ($result['action'] === 'created')      $imported++;
                    elseif ($result['action'] === 'updated')  $updated++;
                    else                                       $skipped++;

                    if ($onProgress) {
                        $onProgress($currentTask, $totalTasks, ucfirst($result['action']) . ": {$empName} ({$dateStr})");
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'staff_code' => $staffCode,
                        'date'       => $dateStr,
                        'error'      => $e->getMessage(),
                    ];
                    Log::error("Punch import error [{$staffCode} {$dateStr}]: " . $e->getMessage());
                    if ($onProgress) {
                        $onProgress($currentTask, $totalTasks, "Error: {$empName} ({$dateStr})");
                    }
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
    protected function processPunchRecord(Employee $employee, Carbon $attendanceDate, string $timeEntries, ?string $deviceName = null, array $punchDeviceMap = []): array
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

        // Deduplicate punches that are too close together (e.g., 5-minute window)
        // Keeps the first punch in a cluster, skips subsequent rapid punches
        $times = $this->deduplicatePunches($times);

        // If no valid times remain after deduplication, skip
        if (empty($times)) {
            return ['action' => 'skipped'];
        }

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

        // Track whether OT was auto-created by setTimeOutWithOtSplit (not from explicit OT punches).
        // When auto-split, subsequent punches should extend ot_time_out rather than starting OT2,
        // because the employee never explicitly clocked out for their regular shift.
        $otAutoSplit = false;

        // Process each punch time individually to properly assign to regular shift or OT sessions
        foreach ($times as $timestamp) {
            $timeString = $timestamp->format('H:i:s');
            $dateStr = $attendanceDate->format('Y-m-d');

            // 1. First punch = time_in
            if (!$attendance->time_in) {
                $attendance->time_in = $timeString;
            }
            // 2. Second punch - could be break_start (if 3+ punches and configured hours after time_in) or time_out
            elseif (!$attendance->break_start && !$attendance->time_out) {
                $canBeBreak = false;

                // Only consider break detection when there are 3+ punches.
                // With exactly 2 punches the second is always time_out — there
                // won't be a subsequent punch to close the break, which would
                // leave an orphan break_start and no time_out (half_day fallback).
                if (count($times) >= 3) {
                    $timeIn = Carbon::parse($dateStr . ' ' . $attendance->time_in);
                    $hoursAfterTimeIn = $timeIn->diffInHours($timestamp);

                    // Also check against scheduled time_in as fallback
                    // When an employee arrives very late, the break detection window shifts too far
                    // Using the scheduled time_in ensures lunch breaks are still detected correctly
                    $scheduledTimeIn = Carbon::parse($dateStr . ' ' . $schedule['standard_time_in']);
                    $hoursAfterScheduledTimeIn = $scheduledTimeIn->diffInHours($timestamp);

                    $isBreakAfterActual = $hoursAfterTimeIn >= $breakMinHours && $hoursAfterTimeIn <= $breakMaxHours;
                    $isBreakAfterScheduled = $hoursAfterScheduledTimeIn >= $breakMinHours && $hoursAfterScheduledTimeIn <= $breakMaxHours;
                    $canBeBreak = $isBreakAfterActual || $isBreakAfterScheduled;
                }

                if ($canBeBreak) {
                    $attendance->break_start = $timeString;
                } else {
                    // It's time_out — but check if it's far past scheduled time_out (OT split needed)
                    if ($this->setTimeOutWithOtSplit($attendance, $timestamp, $scheduledTimeOut, $smartDetectionMinutes, $dateStr)) {
                        $otAutoSplit = true;
                    }
                }
            }
            // 3. Third punch - if we have break_start, check if this is break_end or time_out
            elseif ($attendance->break_start && !$attendance->break_end && !$attendance->time_out) {
                // Smart detection: if this punch is within configured window of scheduled time_out, treat as time_out
                // Otherwise treat as break_end (expecting a 4th punch)
                $minutesBeforeScheduledOut = $scheduledTimeOut->diffInMinutes($timestamp, false);

                // If punch is within configured window of scheduled time_out, assume it's time_out (not break_end)
                if ($minutesBeforeScheduledOut >= -$smartDetectionMinutes) {
                    // This is end of day — clear orphan break_start since we never got break_end
                    $attendance->break_start = null;
                    if ($this->setTimeOutWithOtSplit($attendance, $timestamp, $scheduledTimeOut, $smartDetectionMinutes, $dateStr)) {
                        $otAutoSplit = true;
                    }
                } else {
                    // Still early in the day, this is break_end
                    $attendance->break_end = $timeString;
                }
            }
            // 4. Fourth punch - if we had break, this is time_out (with OT split check)
            elseif ($attendance->break_start && $attendance->break_end && !$attendance->time_out) {
                if ($this->setTimeOutWithOtSplit($attendance, $timestamp, $scheduledTimeOut, $smartDetectionMinutes, $dateStr)) {
                    $otAutoSplit = true;
                }
            }
            // 5. Fifth+ punch - check if OT (> 2h past scheduled time_out)
            elseif ($attendance->time_out) {
                // Use the same 2-hour window as setTimeOutWithOtSplit for consistency.
                // Punch beyond scheduledTimeOut + 2h = OT. Within = adjust time_out.
                if ($timestamp->gt($scheduledTimeOut->copy()->addMinutes($smartDetectionMinutes))) {
                    $actualTimeOut = Carbon::parse($dateStr . ' ' . $attendance->time_out);

                    // If time_out was set past schedule (within the 2h window), snap it back
                    // now that a subsequent punch confirms the employee was working OT all along.
                    if ($actualTimeOut->gt($scheduledTimeOut) && !$attendance->ot_time_in) {
                        $attendance->time_out = $scheduledTimeOut->format('H:i:s');
                    }

                    $otStartTime = $scheduledTimeOut->format('H:i:s');

                    // Track OT sessions (up to 2 sessions)
                    if (!$attendance->ot_time_in) {
                        // First OT punch - set OT session starting from shift end
                        $attendance->ot_time_in = $otStartTime;
                        $attendance->ot_time_out = $timeString;
                    } elseif (!$attendance->ot_time_out) {
                        // Close first OT session
                        $attendance->ot_time_out = $timeString;
                    } elseif ($otAutoSplit) {
                        // OT1 was auto-created by setTimeOutWithOtSplit (employee never explicitly
                        // clocked out for their regular shift). All subsequent punches are part of
                        // the same continuous work session — extend OT1 end time.
                        $attendance->ot_time_out = $timeString;
                    } elseif (!$attendance->ot_time_in_2) {
                        // OT1 is complete — check if it was trivially short (< 60 min).
                        // A short OT1 typically means punch 5 was an OT clock-in (not end),
                        // and this punch is the actual OT end. Extend OT1 instead of
                        // starting a new OT2 session.
                        $ot1In = Carbon::parse($dateStr . ' ' . $attendance->ot_time_in);
                        $ot1Out = Carbon::parse($dateStr . ' ' . $attendance->ot_time_out);
                        if ($ot1Out->lt($ot1In)) {
                            $ot1Out->addDay();
                        }
                        $ot1Minutes = $ot1In->diffInMinutes($ot1Out);

                        if ($ot1Minutes < 60) {
                            // OT1 rounds to 0h via floor() — extend it with this punch
                            $attendance->ot_time_out = $timeString;
                        } else {
                            // Genuine first session — start second OT session
                            $attendance->ot_time_in_2 = $timeString;
                        }
                    } elseif (!$attendance->ot_time_out_2) {
                        // Close second OT session
                        $attendance->ot_time_out_2 = $timeString;
                    } else {
                        // More than 2 OT sessions, extend last OT out
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
            // calculateHours() handles status internally (present/late/half_day)
            // based on schedule, grace period, and half-day threshold rules.
            // Do NOT call adjustAttendanceStatus() after this — it would overwrite
            // the correct status (e.g., revert half_day back to present).
            $attendance->calculateHours();
        } else {
            // Incomplete attendance (no time_out) — apply fallback status logic
            $this->adjustAttendanceStatus($attendance);
        }

        // Calculate per-device hour distribution from punch-device mapping
        if (!empty($punchDeviceMap)) {
            $attendance->device_hours = $this->calculateDeviceHours($attendance, $punchDeviceMap, $attendanceDate->format('Y-m-d'));
            $attendance->save();
        }

        return ['action' => $action, 'attendance' => $attendance->fresh()];
    }

    /**
     * Calculate per-device hour distribution from punch-device mapping.
     *
     * Splits the attendance's working segments across devices based on which
     * biometric device recorded each punch. For multi-device days this produces
     * e.g. {"CSU Med": 5.98, "PIGLAWIGAN": 4.0}.
     */
    protected function calculateDeviceHours(Attendance $attendance, array $punchDeviceMap, string $dateStr): array
    {
        $deviceHours = [];
        $defaultDevice = $attendance->device_name ?? 'Unassigned';

        // Lookup device for a given HH:MM:SS time string via the punch map (keyed by HH:MM)
        $findDevice = function (string $timeStr) use ($punchDeviceMap, $defaultDevice) {
            $short = substr($timeStr, 0, 5); // HH:MM
            return $punchDeviceMap[$short] ?? $defaultDevice;
        };

        $addHours = function (string $device, float $hours) use (&$deviceHours) {
            if ($hours <= 0) return;
            $deviceHours[$device] = ($deviceHours[$device] ?? 0) + $hours;
        };

        // ── Regular work segments ────────────────────────────────────────────
        if ($attendance->time_in && $attendance->time_out) {
            if ($attendance->break_start && $attendance->break_end) {
                // Morning: time_in → break_start
                $mStart = Carbon::parse($dateStr . ' ' . $attendance->time_in);
                $mEnd   = Carbon::parse($dateStr . ' ' . $attendance->break_start);
                $addHours($findDevice($attendance->time_in), $mStart->diffInMinutes($mEnd) / 60);

                // Afternoon: break_end → time_out
                $aStart = Carbon::parse($dateStr . ' ' . $attendance->break_end);
                $aEnd   = Carbon::parse($dateStr . ' ' . $attendance->time_out);
                $addHours($findDevice($attendance->break_end), $aStart->diffInMinutes($aEnd) / 60);
            } else {
                // No break: time_in → time_out
                $start = Carbon::parse($dateStr . ' ' . $attendance->time_in);
                $end   = Carbon::parse($dateStr . ' ' . $attendance->time_out);
                $addHours($findDevice($attendance->time_in), $start->diffInMinutes($end) / 60);
            }
        } elseif ($attendance->time_in && !$attendance->time_out) {
            // Half-day fallback — attribute fixed hours to time_in device
            $addHours($findDevice($attendance->time_in), (float) ($attendance->regular_hours ?: 4));
        }

        // ── OT Session 1 ────────────────────────────────────────────────────
        if ($attendance->ot_time_in && $attendance->ot_time_out) {
            $otS = Carbon::parse($dateStr . ' ' . $attendance->ot_time_in);
            $otE = Carbon::parse($dateStr . ' ' . $attendance->ot_time_out);
            if ($otE->lt($otS)) $otE->addDay();
            // Use ot_time_out device (ot_time_in is often synthetic/scheduledTimeOut)
            $addHours($findDevice($attendance->ot_time_out), $otS->diffInMinutes($otE) / 60);
        }

        // ── OT Session 2 ────────────────────────────────────────────────────
        if ($attendance->ot_time_in_2 && $attendance->ot_time_out_2) {
            $otS2 = Carbon::parse($dateStr . ' ' . $attendance->ot_time_in_2);
            $otE2 = Carbon::parse($dateStr . ' ' . $attendance->ot_time_out_2);
            if ($otE2->lt($otS2)) $otE2->addDay();
            $addHours($findDevice($attendance->ot_time_out_2), $otS2->diffInMinutes($otE2) / 60);
        }

        // If nothing was computed, at least record the default device
        if (empty($deviceHours)) {
            $deviceHours[$defaultDevice] = (float) ($attendance->regular_hours ?: 0);
        }

        // Round to 2 decimals for cleanliness
        return array_map(fn($h) => round($h, 2), $deviceHours);
    }

    /**
     * Set time_out with automatic OT split when punch is far past scheduled time_out.
     *
     * If the punch is within the OT grace period of the scheduled end, it's just time_out.
     * If the punch is AFTER scheduled end + grace, set time_out to the scheduled end
     * and create an OT session from scheduled end to the punch time.
     *
     * @return bool True if an OT split was created, false if just time_out was set.
     */
    protected function setTimeOutWithOtSplit(Attendance $attendance, Carbon $timestamp, Carbon $scheduledTimeOut, int $smartDetectionMinutes, string $dateStr): bool
    {
        $timeString = $timestamp->format('H:i:s');

        // If punch is within 2 hours of scheduled time_out, just set as time_out (not OT)
        if ($timestamp->lte($scheduledTimeOut->copy()->addMinutes($smartDetectionMinutes))) {
            $attendance->time_out = $timeString;
            return false;
        }

        // Punch is well past scheduled time_out — split into regular + OT
        $attendance->time_out = $scheduledTimeOut->format('H:i:s');
        $attendance->ot_time_in = $scheduledTimeOut->format('H:i:s');
        $attendance->ot_time_out = $timeString;
        return true;
    }

    /**
     * Optimized version of punch record processing using pre-loaded data.
     *
     * Differences from processPunchRecord:
     * - Uses pre-loaded $existingAtt instead of querying the database
     * - Uses pre-loaded $schedule instead of calling getScheduleForEmployee each time
     * - Uses pre-loaded employee relationship (no DB query for project)
     * - Saves attendance directly without fresh() call
     *
     * @param Employee $employee Pre-loaded employee model
     * @param Carbon $attendanceDate The date for this attendance record
     * @param string $timeEntries Newline-separated time strings (already sorted, deduplicated)
     * @param string|null $deviceName Device name for this employee+date
     * @param array $punchDeviceMap Punch-to-device mapping
     * @param Attendance|null $existingAtt Pre-loaded existing attendance (or null)
     * @param array $schedule Pre-loaded schedule config
     * @return array ['action' => 'created'|'updated'|'skipped', 'attendance' => Attendance]
     */
    protected function processPunchRecordOptimized(
        Employee $employee,
        Carbon $attendanceDate,
        string $timeEntries,
        ?string $deviceName,
        array $punchDeviceMap,
        ?Attendance $existingAtt,
        array $schedule
    ): array {
        // Parse time entries
        $times = [];
        $lines = preg_split('/[\r\n]+/', trim($timeEntries));
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $line, $matches)) {
                $hour = (int)$matches[1];
                $minute = (int)$matches[2];
                if ($hour == 0 && $minute <= 5) {
                    continue;
                }
                $times[] = Carbon::create($attendanceDate->year, $attendanceDate->month, $attendanceDate->day, $hour, $minute);
            }
        }

        if (empty($times)) {
            return ['action' => 'skipped', 'attendance' => $existingAtt];
        }

        usort($times, fn($a, $b) => $a->timestamp - $b->timestamp);

        $action = $existingAtt ? 'updated' : 'created';
        $dateStr = $attendanceDate->format('Y-m-d');

        // Create or update attendance
        if (!$existingAtt) {
            $attendance = new Attendance();
            $attendance->employee_id = $employee->id;
            $attendance->attendance_date = $dateStr;
            $attendance->status = 'present';
            $attendance->is_manual_entry = false;
            $attendance->device_name = $deviceName;
            $attendance->approval_status = 'approved';
            $attendance->is_approved = true;
            $attendance->created_by = auth()->id() ?? 1;
        } else {
            $attendance = $existingAtt;
            if ($deviceName !== null) {
                $attendance->device_name = $deviceName;
            }
            // Clear all time fields for clean recalculation
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
            $attendance->status = 'present';
        }

        $scheduledTimeOut = Carbon::parse($dateStr . ' ' . $schedule['standard_time_out']);
        $breakMinHours = (int) config('payroll.attendance.break_detection_min_hours', 3);
        $breakMaxHours = (int) config('payroll.attendance.break_detection_max_hours', 6);
        $smartDetectionMinutes = (int) config('payroll.attendance.smart_detection_window_minutes', 120);
        $otAutoSplit = false;

        // Process each punch (same logic as processPunchRecord but with pre-loaded schedule)
        foreach ($times as $timestamp) {
            $timeString = $timestamp->format('H:i:s');

            if (!$attendance->time_in) {
                $attendance->time_in = $timeString;
            } elseif (!$attendance->break_start && !$attendance->time_out) {
                $canBeBreak = false;
                if (count($times) >= 3) {
                    $timeIn = Carbon::parse($dateStr . ' ' . $attendance->time_in);
                    $hoursAfterTimeIn = $timeIn->diffInHours($timestamp);
                    $scheduledTimeIn = Carbon::parse($dateStr . ' ' . $schedule['standard_time_in']);
                    $hoursAfterScheduledTimeIn = $scheduledTimeIn->diffInHours($timestamp);
                    $canBeBreak = ($hoursAfterTimeIn >= $breakMinHours && $hoursAfterTimeIn <= $breakMaxHours)
                        || ($hoursAfterScheduledTimeIn >= $breakMinHours && $hoursAfterScheduledTimeIn <= $breakMaxHours);
                }
                if ($canBeBreak) {
                    $attendance->break_start = $timeString;
                } else {
                    if ($this->setTimeOutWithOtSplit($attendance, $timestamp, $scheduledTimeOut, $smartDetectionMinutes, $dateStr)) {
                        $otAutoSplit = true;
                    }
                }
            } elseif ($attendance->break_start && !$attendance->break_end && !$attendance->time_out) {
                $minutesBeforeScheduledOut = $scheduledTimeOut->diffInMinutes($timestamp, false);
                if ($minutesBeforeScheduledOut >= -$smartDetectionMinutes) {
                    $attendance->break_start = null;
                    if ($this->setTimeOutWithOtSplit($attendance, $timestamp, $scheduledTimeOut, $smartDetectionMinutes, $dateStr)) {
                        $otAutoSplit = true;
                    }
                } else {
                    $attendance->break_end = $timeString;
                }
            } elseif ($attendance->break_start && $attendance->break_end && !$attendance->time_out) {
                if ($this->setTimeOutWithOtSplit($attendance, $timestamp, $scheduledTimeOut, $smartDetectionMinutes, $dateStr)) {
                    $otAutoSplit = true;
                }
            } elseif ($attendance->time_out) {
                if ($timestamp->gt($scheduledTimeOut->copy()->addMinutes($smartDetectionMinutes))) {
                    $actualTimeOut = Carbon::parse($dateStr . ' ' . $attendance->time_out);
                    if ($actualTimeOut->gt($scheduledTimeOut) && !$attendance->ot_time_in) {
                        $attendance->time_out = $scheduledTimeOut->format('H:i:s');
                    }
                    $otStartTime = $scheduledTimeOut->format('H:i:s');

                    if (!$attendance->ot_time_in) {
                        $attendance->ot_time_in = $otStartTime;
                        $attendance->ot_time_out = $timeString;
                    } elseif (!$attendance->ot_time_out) {
                        $attendance->ot_time_out = $timeString;
                    } elseif ($otAutoSplit) {
                        $attendance->ot_time_out = $timeString;
                    } elseif (!$attendance->ot_time_in_2) {
                        $ot1In = Carbon::parse($dateStr . ' ' . $attendance->ot_time_in);
                        $ot1Out = Carbon::parse($dateStr . ' ' . $attendance->ot_time_out);
                        if ($ot1Out->lt($ot1In)) $ot1Out->addDay();
                        $ot1Minutes = $ot1In->diffInMinutes($ot1Out);
                        if ($ot1Minutes < 60) {
                            $attendance->ot_time_out = $timeString;
                        } else {
                            $attendance->ot_time_in_2 = $timeString;
                        }
                    } elseif (!$attendance->ot_time_out_2) {
                        $attendance->ot_time_out_2 = $timeString;
                    } else {
                        $attendance->ot_time_out_2 = $timeString;
                    }
                } else {
                    $attendance->time_out = $timeString;
                }
            }
        }

        // Handle incomplete attendance
        if ($attendance->time_in && !$attendance->time_out) {
            $attendance->status = 'half_day';
            $attendance->regular_hours = 4;
        }

        // Save attendance (no transaction - already handled at batch level)
        $attendance->save();

        // Calculate hours
        if ($attendance->time_in && $attendance->time_out) {
            $attendance->calculateHours();
        } else {
            $this->adjustAttendanceStatus($attendance);
        }

        // Device hours
        if (!empty($punchDeviceMap)) {
            $attendance->device_hours = $this->calculateDeviceHours($attendance, $punchDeviceMap, $dateStr);
            $attendance->save();
        }

        return ['action' => $action, 'attendance' => $attendance];
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
        $halfDayThreshold = (float) config('payroll.attendance.half_day_hours_threshold', 5.0);
        if ($attendance->regular_hours > 0 && $attendance->regular_hours < $halfDayThreshold) {
            $attendance->status = 'half_day';
        } elseif ($attendance->regular_hours >= $halfDayThreshold) {
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
    protected function getScheduleForEmployee($employee, ?Carbon $date): array
    {
        $defaultTimeIn = config('payroll.attendance.standard_time_in', '07:30');
        $defaultTimeOut = config('payroll.attendance.standard_time_out', '17:00');
        $defaultGrace = (int) config('payroll.attendance.grace_period_minutes', 3);

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
     * Deduplicate punches that are too close together in time.
     *
     * When a user punches multiple times in quick succession (e.g., 7:01, 7:05, 7:07),
     * only the first punch in a cluster is kept. This prevents incorrect attendance
     * calculations where rapid re-punches would incorrectly set time_out too early.
     *
     * @param array $times Array of Carbon objects (sorted chronologically)
     * @param int $windowMinutes Punches within this many minutes are considered duplicates
     * @return array Filtered array of unique punches
     */
    protected function deduplicatePunches(array $times, int $windowMinutes = 5): array
    {
        if (count($times) <= 1) {
            return $times;
        }

        $deduped = [];
        $lastKept = null;

        foreach ($times as $punch) {
            if ($lastKept === null) {
                $deduped[] = $punch;
                $lastKept = $punch;
            } else {
                $diffMinutes = $punch->diffInMinutes($lastKept);
                if ($diffMinutes > $windowMinutes) {
                    $deduped[] = $punch;
                    $lastKept = $punch;
                }
                // Otherwise skip this punch as it's a duplicate of the last kept punch
            }
        }

        return $deduped;
    }
}
