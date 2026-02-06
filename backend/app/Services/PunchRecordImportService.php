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
        
        // Default to current year if not provided
        if (!$year) {
            $year = now()->year;
        }

        // Try to detect month from column headers if not provided
        $detectedMonth = $this->detectMonthFromColumns(array_keys($punchData[0] ?? []));
        
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
                    $errors[] = [
                        'row' => $index + 1,
                        'staff_code' => $staffCode,
                        'error' => 'Employee not found. Please import staff information first.',
                    ];
                    continue;
                }

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
                                
                                $result = $this->processPunchRecord($employee, $attendanceDate, $timeValue);
                                
                                if ($result['action'] === 'created') {
                                    $rowImported++;
                                } elseif ($result['action'] === 'updated') {
                                    $rowUpdated++;
                                }
                                
                            } catch (\Exception $e) {
                                Log::warning("Invalid date: {$key} for year {$year}, error: " . $e->getMessage());
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
        
        if (count($times) >= 4) {
            // With 4+ punches: 1st=in, 2nd=break start, 3rd=break end, last=out
            $breakStart = $times[1]->format('H:i:s');
            $breakEnd = $times[2]->format('H:i:s');
        }
        
        $attendanceData = [
            'employee_id' => $employee->id,
            'attendance_date' => $attendanceDate->format('Y-m-d'),
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'break_start' => $breakStart,
            'break_end' => $breakEnd,
            'status' => 'present',
            'is_manual_entry' => false,
            'approval_status' => 'approved',
            'is_approved' => true,
        ];
        
        if ($attendance) {
            // Update existing record
            $attendance->update($attendanceData);
            // Recalculate hours, undertime, overtime, and half-day status
            $attendance->calculateHours();
            return ['action' => 'updated', 'attendance' => $attendance->fresh()];
        } else {
            // Create new record
            $attendanceData['created_by'] = auth()->id() ?? 1;
            $attendance = Attendance::create($attendanceData);
            // Calculate hours, undertime, overtime, and half-day status
            $attendance->calculateHours();
            return ['action' => 'created', 'attendance' => $attendance->fresh()];
        }
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
