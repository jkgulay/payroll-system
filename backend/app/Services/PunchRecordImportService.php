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
     * Each date column may contain multiple time entries separated by newlines
     */
    public function importPunchRecords(array $punchData, ?int $year = null, ?int $month = null): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        
        // Default to current year if not provided
        if (!$year) {
            $year = now()->year;
        }

        DB::beginTransaction();
        
        try {
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
                        $errors[] = [
                            'row' => $index + 1,
                            'staff_code' => $staffCode,
                            'error' => 'Employee not found. Please import staff information first.',
                        ];
                        continue;
                    }

                    // Process each date column
                    foreach ($row as $key => $value) {
                        // Skip non-date columns
                        if ($key === 'Staff Code' || $key === 'Name') {
                            continue;
                        }
                        
                        // Parse date from column header (e.g., "12-01" means December 1st)
                        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $key, $matches)) {
                            $monthNum = (int)$matches[1];
                            $dayNum = (int)$matches[2];
                            
                            // Override month if provided
                            if ($month) {
                                $monthNum = $month;
                            }
                            
                            try {
                                $attendanceDate = Carbon::create($year, $monthNum, $dayNum);
                                
                                // Skip if no time entries
                                if (empty($value) || trim($value) === '') {
                                    continue;
                                }
                                
                                $result = $this->processPunchRecord($employee, $attendanceDate, $value);
                                
                                if ($result['action'] === 'created') {
                                    $imported++;
                                } elseif ($result['action'] === 'updated') {
                                    $updated++;
                                }
                                
                            } catch (\Exception $e) {
                                Log::warning("Invalid date: {$key} for year {$year}");
                            }
                        }
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

            DB::commit();
            
            return [
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => count($errors),
                'error_details' => $errors,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Punch record import failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a single punch record for a specific date
     * Time entries can be multiple lines like "06:10\n11:39\n11:51\n17:02"
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
                $times[] = Carbon::create($attendanceDate->year, $attendanceDate->month, $attendanceDate->day, $hour, $minute);
            }
        }
        
        // If no valid times found, skip
        if (empty($times)) {
            return ['action' => 'skipped'];
        }
        
        // Sort times chronologically
        usort($times, function($a, $b) {
            return $a->timestamp - $b->timestamp;
        });
        
        // Check if attendance record already exists
        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', $attendanceDate->format('Y-m-d'))
            ->first();
        
        // Determine time in, time out, breaks
        $timeIn = $times[0]->format('H:i:s');
        $timeOut = count($times) > 1 ? end($times)->format('H:i:s') : null;
        
        // If there are more than 2 entries, assume middle entries are break times
        $breakStart = null;
        $breakEnd = null;
        
        if (count($times) >= 3) {
            // Simple logic: 2nd time is break start, 3rd is break end
            $breakStart = $times[1]->format('H:i:s');
            $breakEnd = $times[2]->format('H:i:s');
        }
        
        // Calculate hours worked
        $regularHours = 0;
        if ($timeOut) {
            $workStart = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $timeIn);
            $workEnd = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $timeOut);
            $totalMinutes = $workStart->diffInMinutes($workEnd);
            
            // Subtract break time if any
            if ($breakStart && $breakEnd) {
                $breakStartTime = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $breakStart);
                $breakEndTime = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $breakEnd);
                $breakMinutes = $breakStartTime->diffInMinutes($breakEndTime);
                $totalMinutes -= $breakMinutes;
            }
            
            $regularHours = round($totalMinutes / 60, 2);
            
            // Cap at 8 hours for regular, rest is overtime
            if ($regularHours > 8) {
                $regularHours = 8;
            }
        }
        
        $attendanceData = [
            'employee_id' => $employee->id,
            'attendance_date' => $attendanceDate->format('Y-m-d'),
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
            'regular_hours' => $regularHours,
            'status' => 'present',
            'is_manual_entry' => false,
            'approval_status' => 'approved',
            'is_approved' => true,
        ];
        
        if ($attendance) {
            // Update existing record
            $attendance->update($attendanceData);
            return ['action' => 'updated', 'attendance' => $attendance];
        } else {
            // Create new record
            $attendanceData['created_by'] = auth()->id() ?? 1;
            $attendance = Attendance::create($attendanceData);
            return ['action' => 'created', 'attendance' => $attendance];
        }
    }

    /**
     * Parse date from column header (e.g., "12-01" to month and day)
     */
    public function parseDateColumn(string $columnHeader): ?array
    {
        if (preg_match('/^(\d{1,2})-(\d{1,2})$/', $columnHeader, $matches)) {
            return [
                'month' => (int)$matches[1],
                'day' => (int)$matches[2],
            ];
        }
        
        return null;
    }
}
