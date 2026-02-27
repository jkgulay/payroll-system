<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\AuditLog;
use App\Services\AttendanceService;
use App\Services\BiometricService;
use App\Services\PunchRecordImportService;
use App\Exports\AttendanceSummaryExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    protected $attendanceService;
    protected $biometricService;
    protected $punchRecordImportService;

    public function __construct(AttendanceService $attendanceService, BiometricService $biometricService, PunchRecordImportService $punchRecordImportService)
    {
        $this->attendanceService = $attendanceService;
        $this->biometricService = $biometricService;
        $this->punchRecordImportService = $punchRecordImportService;

        // Manual entry and editing: admin and hr only (store/destroy), payrollist can update
        $this->middleware('role:admin,hr')->only(['store', 'destroy', 'markAbsent']);
        $this->middleware('role:admin,hr,payrollist')->only(['update']);

        // Approval actions: admin, hr, and manager
        $this->middleware('role:admin,hr,manager')->only(['approve', 'reject']);

        // Biometric import and device management: admin and hr only
        $this->middleware('role:admin,hr')->only(['importBiometric', 'fetchFromDevice', 'syncEmployees', 'clearDeviceLogs']);
    }
    public function index(Request $request)
    {
        $query = Attendance::with(['employee']);

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('date_from')) {
            $query->where('attendance_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('attendance_date', '<=', $request->date_to);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Use per_page from request, default to 1000 to show all records
        $perPage = $request->input('per_page', 1000);
        return response()->json($query->latest('attendance_date')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'attendance_date' => 'required|date|before_or_equal:today',
            'time_in' => 'nullable|date_format:H:i:s',
            'time_out' => 'nullable|date_format:H:i:s|after:time_in',
            'break_start' => 'nullable|date_format:H:i:s',
            'break_end' => 'nullable|date_format:H:i:s|after:break_start',
            'ot_time_in' => 'nullable|date_format:H:i:s',
            'ot_time_out' => 'nullable|date_format:H:i:s|after:ot_time_in',
            'notes' => 'nullable|string|max:500',
            'requires_approval' => 'boolean',
        ]);

        // Check for duplicate attendance
        $existingAttendance = Attendance::where('employee_id', $validated['employee_id'])
            ->where('attendance_date', $validated['attendance_date'])
            ->first();

        if ($existingAttendance) {
            return response()->json([
                'message' => 'Attendance record already exists for this date',
                'attendance' => $existingAttendance->load('employee'),
                'action' => 'exists',
            ], 422);
        }

        $employee = Employee::find($validated['employee_id']);

        try {
            $attendance = $this->attendanceService->createManualEntry([
                'employee_id' => $validated['employee_id'],
                'attendance_date' => $validated['attendance_date'],
                'time_in' => $validated['time_in'] ?? null,
                'time_out' => $validated['time_out'] ?? null,
                'break_start' => $validated['break_start'] ?? null,
                'break_end' => $validated['break_end'] ?? null,
                'ot_time_in' => $validated['ot_time_in'] ?? null,
                'ot_time_out' => $validated['ot_time_out'] ?? null,
                'reason' => $validated['notes'] ?? 'Manual entry by ' . $request->user()->name,
                'created_by' => $request->user()->id,
                'requires_approval' => $validated['requires_approval'] ?? true,
            ]);

            // Log manual entry
            AuditLog::create([
                'user_id' => $request->user()->id,
                'module' => 'attendance',
                'action' => 'manual_attendance_entry',
                'description' => 'Manual attendance entry created',
                'old_values' => null,
                'new_values' => $attendance->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Manual attendance entry created successfully',
                'attendance' => $attendance->fresh()->load('employee'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create manual attendance: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create attendance record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Attendance $attendance)
    {
        return response()->json($attendance->load(['employee.project', 'employee.positionRate']));
    }

    public function update(Request $request, Attendance $attendance)
    {
        // Prevent editing approved records without proper permission
        if (
            $attendance->approval_status === 'approved' &&
            !in_array($request->user()->role, ['admin', 'hr', 'payrollist'])
        ) {
            return response()->json([
                'message' => 'Cannot edit approved attendance records',
            ], 403);
        }

        $validated = $request->validate([
            'time_in' => 'nullable|date_format:H:i:s',
            'time_out' => 'nullable|date_format:H:i:s|after:time_in',
            'break_start' => 'nullable|date_format:H:i:s',
            'break_end' => 'nullable|date_format:H:i:s|after:break_start',
            'ot_time_in' => 'nullable|date_format:H:i:s',
            'ot_time_out' => 'nullable|date_format:H:i:s|after:ot_time_in',
            'notes' => 'nullable|string|max:500',
        ]);

        $oldValues = $attendance->toArray();

        try {
            // Build update data - include all validated fields (even null) to allow clearing
            $updateData = [
                'edit_reason' => $validated['notes'] ?? null,
            ];

            // Only include time fields that were actually sent in the request
            foreach (['time_in', 'time_out', 'break_start', 'break_end', 'ot_time_in', 'ot_time_out'] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $validated[$field] ?? null;
                }
            }

            $this->attendanceService->updateAttendance(
                $attendance,
                $updateData,
                $request->user()->id,
                in_array($request->user()->role, ['admin', 'hr'])
            );

            // Log the update
            AuditLog::create([
                'user_id' => $request->user()->id,
                'module' => 'attendance',
                'action' => 'update_attendance',
                'description' => 'Attendance record updated',
                'old_values' => $oldValues,
                'new_values' => $attendance->fresh()->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Attendance updated successfully',
                'attendance' => $attendance->fresh()->load('employee'),
            ]);
        } catch (\Exception $e) {
            Log::error('[Attendance Update Error] ' . $e->getMessage(), [
                'exception' => $e,
                'attendance_id' => $attendance->id ?? null,
                'request_data' => $request->all(),
                'validated' => $validated ?? null,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to update attendance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return response()->json(['message' => 'Attendance record deleted successfully']);
    }

    public function importBiometric(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            $file     = $request->file('file');
            $filePath = $file->storeAs('biometric_imports', 'import_' . time() . '.' . $file->getClientOriginalExtension());
            $fullPath = Storage::path($filePath);

            // Parse with PhpSpreadsheet – read cells directly to handle Excel
            // date/time serial numbers properly (avoids "2026-02-03 00:00:00 06:49:00"
            // artefacts that toArray() with format masks can produce).
            $spreadsheet   = \PhpOffice\PhpSpreadsheet\IOFactory::load($fullPath);
            $worksheet     = $spreadsheet->getActiveSheet();
            $highestRow    = $worksheet->getHighestDataRow();
            $highestCol    = $worksheet->getHighestDataColumn();
            $highestColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

            if ($highestRow < 2) {
                Storage::delete($filePath);
                return response()->json(['message' => 'No valid data found in file'], 422);
            }

            $headers = [];
            for ($col = 1; $col <= $highestColIdx; $col++) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $headers[$col] = trim((string) $worksheet->getCell($coord . '1')->getValue());
            }

            $punchData = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColIdx; $col++) {
                    $header = $headers[$col] ?? '';
                    if ($header === '') continue;
                    $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $cell  = $worksheet->getCell($coord . $row);
                    $value = $cell->getValue();
                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell) && is_numeric($value)) {
                        $dt    = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                        $value = $dt->format('Y-m-d H:i:s');
                    }
                    $rowData[$header] = ($value !== null && $value !== '') ? trim((string) $value) : null;
                }
                $punchData[] = $rowData;
            }

            $result = $this->punchRecordImportService->importPunchRecords($punchData);

            AuditLog::create([
                'user_id'     => $request->user()->id,
                'module'      => 'attendance',
                'action'      => 'biometric_import',
                'description' => 'Biometric attendance imported from file',
                'old_values'  => null,
                'new_values'  => [
                    'file'     => $file->getClientOriginalName(),
                    'imported' => $result['imported'],
                    'updated'  => $result['updated'],
                    'failed'   => $result['failed'],
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Storage::delete($filePath);

            return response()->json([
                'message'  => 'Biometric data imported successfully',
                'imported' => $result['imported'],
                'updated'  => $result['updated'],
                'skipped'  => $result['skipped'],
                'failed'   => $result['failed'],
                'errors'   => $result['error_details'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error('Biometric import failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to import biometric data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, Attendance $attendance)
    {
        if ($attendance->approval_status === 'approved') {
            return response()->json([
                'message' => 'Attendance is already approved',
                'attendance' => $attendance->load('employee', 'approvedBy'),
            ], 422);
        }

        if ($attendance->approval_status === 'rejected') {
            return response()->json([
                'message' => 'Cannot approve rejected attendance. Please create a new record.',
            ], 422);
        }

        $oldValues = $attendance->toArray();

        $attendance->update([
            'approval_status' => 'approved',
            'is_approved' => true,
            'is_rejected' => false,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'approval_notes' => $request->input('notes'),
            'rejection_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        // Log approval
        AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'attendance',
            'action' => 'approve_attendance',
            'description' => 'Attendance record approved',
            'old_values' => $oldValues,
            'new_values' => $attendance->fresh()->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Attendance approved successfully',
            'attendance' => $attendance->fresh()->load('employee', 'approvedBy'),
        ]);
    }

    public function reject(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($attendance->approval_status === 'approved') {
            return response()->json([
                'message' => 'Cannot reject approved attendance',
            ], 422);
        }

        $oldValues = $attendance->toArray();

        $attendance->update([
            'approval_status' => 'rejected',
            'is_approved' => false,
            'is_rejected' => true,
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'rejection_reason' => $validated['reason'],
        ]);

        // Log rejection
        AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'attendance',
            'action' => 'reject_attendance',
            'description' => 'Attendance record rejected',
            'old_values' => $oldValues,
            'new_values' => $attendance->fresh()->toArray(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Attendance rejected',
            'attendance' => $attendance->fresh()->load('employee', 'rejectedBy'),
        ]);
    }

    public function employeeSummary($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $summary = [
            'employee' => $employee,
            'total_present' => Attendance::where('employee_id', $employeeId)->where('status', 'present')->count(),
            'total_absent' => Attendance::where('employee_id', $employeeId)->where('status', 'absent')->count(),
            'total_late' => Attendance::where('employee_id', $employeeId)->where('status', 'present')->where('late_hours', '>', 0)->count(),
            'total_hours' => Attendance::where('employee_id', $employeeId)->sum('regular_hours'),
        ];

        return response()->json($summary);
    }

    /**
     * Get attendance summary for a date range
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $query = Attendance::query()
            ->whereBetween('attendance_date', [$validated['date_from'], $validated['date_to']]);

        if ($validated['employee_id'] ?? false) {
            $query->where('employee_id', $validated['employee_id']);
        }

        $attendances = $query->with('employee')->get();

        $summary = [
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'total_records' => $attendances->count(),
            'total_present' => $attendances->where('status', 'present')->count(),
            'total_absent' => $attendances->where('status', 'absent')->count(),
            'total_late' => $attendances->where('status', 'present')->filter(fn($a) => $a->late_hours > 0)->count(),
            'total_on_leave' => $attendances->where('status', 'leave')->count(),
            'total_hours_worked' => $attendances->sum('regular_hours') + $attendances->sum('overtime_hours'),
            'total_regular_hours' => $attendances->sum('regular_hours'),
            'total_overtime_hours' => $attendances->sum('overtime_hours'),
            'total_night_differential_hours' => $attendances->sum('night_differential_hours'),
            'pending_approval' => $attendances->where('approval_status', 'pending')->count(),
        ];

        if (!($validated['employee_id'] ?? false)) {
            // Group by employee
            $byEmployee = $attendances->groupBy('employee_id')->map(function ($records) {
                return [
                    'employee' => $records->first()->employee->only(['id', 'employee_number', 'full_name']),
                    'total_present' => $records->where('status', 'present')->count(),
                    'total_absent' => $records->where('status', 'absent')->count(),
                    'total_hours' => $records->sum('regular_hours') + $records->sum('overtime_hours'),
                ];
            })->values();

            $summary['by_employee'] = $byEmployee;
        }

        return response()->json($summary);
    }

    /**
     * Export attendance summary to Excel
     */
    public function exportSummary(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $query = Attendance::query()
            ->whereBetween('attendance_date', [$validated['date_from'], $validated['date_to']])
            ->with(['employee']);

        if ($validated['employee_id'] ?? false) {
            $query->where('employee_id', $validated['employee_id']);
        }

        $attendances = $query->orderBy('attendance_date')->orderBy('employee_id')->get();

        $filename = 'attendance_summary_' . $validated['date_from'] . '_to_' . $validated['date_to'] . '.xlsx';

        return Excel::download(
            new AttendanceSummaryExport($attendances, $validated['date_from'], $validated['date_to']),
            $filename
        );
    }

    /**
     * Mark employees as absent for a specific date
     */
    public function markAbsent(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id',
            'exclude_on_leave' => 'boolean',
        ]);

        $date = $validated['date'];
        $excludeOnLeave = $validated['exclude_on_leave'] ?? true;

        // Get employees who don't have attendance for this date
        $query = Employee::active();

        if ($validated['employee_ids'] ?? false) {
            $query->whereIn('id', $validated['employee_ids']);
        }

        $employees = $query->get();

        $marked = 0;
        foreach ($employees as $employee) {
            // Check if attendance already exists
            $exists = Attendance::where('employee_id', $employee->id)
                ->where('attendance_date', $date)
                ->exists();

            if ($exists) {
                continue;
            }

            // Check if employee is on leave
            if ($excludeOnLeave) {
                $onLeave = $employee->leaves()
                    ->where('leave_date_from', '<=', $date)
                    ->where('leave_date_to', '>=', $date)
                    ->where('status', 'approved')
                    ->exists();

                if ($onLeave) {
                    continue;
                }
            }

            // Create absent record
            Attendance::create([
                'employee_id' => $employee->id,
                'attendance_date' => $date,
                'status' => 'absent',
                'approval_status' => 'approved',
                'is_approved' => true,
                'is_manual_entry' => true,
                'manual_reason' => 'Marked absent automatically',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $marked++;
        }

        // Log the action
        AuditLog::create([
            'user_id' => $request->user()->id,
            'module' => 'attendance',
            'action' => 'mark_absent',
            'description' => 'Marked employees as absent',
            'old_values' => null,
            'new_values' => [
                'date' => $date,
                'marked_count' => $marked,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => "Marked {$marked} employees as absent",
            'date' => $date,
            'marked' => $marked,
        ]);
    }

    /**
     * Get pending approvals
     */
    public function pendingApprovals(Request $request)
    {
        $query = Attendance::where('approval_status', 'pending')
            ->with(['employee', 'createdBy']);

        if ($request->has('date_from')) {
            $query->where('attendance_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('attendance_date', '<=', $request->date_to);
        }

        // Use per_page from request, default to 1000 to show all records
        $perPage = $request->input('per_page', 1000);
        return response()->json($query->latest('attendance_date')->paginate($perPage));
    }

    /**
     * Fetch attendance directly from biometric device
     */
    public function fetchFromDevice(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        try {
            $records = $this->biometricService->fetchFromDevice(
                $validated['date_from'] ?? null,
                $validated['date_to'] ?? null
            );

            $result = $this->attendanceService->importBiometric($records);

            AuditLog::create([
                'user_id' => $request->user()->id,
                'module' => 'attendance',
                'action' => 'fetch_from_device',
                'description' => 'Fetched attendance from biometric device',
                'old_values' => null,
                'new_values' => [
                    'records_imported' => $result['imported'],
                    'records_updated' => $result['updated'] ?? 0,
                    'records_failed' => $result['errors'],
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Attendance fetched from device successfully',
                'imported' => $result['imported'],
                'updated' => $result['updated'] ?? 0,
                'failed' => $result['errors'],
                'errors' => $result['error_details'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch from device: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch from biometric device',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync employees to biometric device
     */
    public function syncEmployees(Request $request)
    {
        try {
            $result = $this->biometricService->syncEmployeesToDevice();

            AuditLog::create([
                'user_id' => $request->user()->id,
                'module' => 'attendance',
                'action' => 'sync_employees_to_device',
                'description' => 'Synced employees to biometric device',
                'old_values' => null,
                'new_values' => $result,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Employees synced to device successfully',
                'synced' => $result['synced'],
                'total' => $result['total'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync employees: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to sync employees to device',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get biometric device information
     */
    public function deviceInfo()
    {
        $info = $this->biometricService->getDeviceInfo();
        return response()->json($info);
    }

    /**
     * Clear device attendance logs
     */
    public function clearDeviceLogs(Request $request)
    {
        try {
            $result = $this->biometricService->clearDeviceLogs();

            AuditLog::create([
                'user_id' => $request->user()->id,
                'module' => 'attendance',
                'action' => 'clear_device_logs',
                'description' => 'Cleared biometric device logs',
                'old_values' => null,
                'new_values' => ['cleared' => $result],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Device logs cleared successfully',
                'success' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clear device logs: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to clear device logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employees with missing attendance data for a specific date
     */
    public function getMissingAttendance(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'type' => 'nullable|in:missing_timeout,missing_breakout,missing_ot_timeout,no_record,all',
        ]);

        $date = $validated['date'];
        $type = $validated['type'] ?? 'all';

        // Get ALL active employees
        $allActiveEmployees = \App\Models\Employee::with(['project', 'positionRate'])
            ->where('is_active', true)
            ->where('activity_status', 'active')
            ->get();

        // Get attendance records for the specified date
        $attendances = Attendance::whereDate('attendance_date', $date)
            ->whereIn('employee_id', $allActiveEmployees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        $missingRecords = [];
        $totalWithAttendance = $attendances->count();

        foreach ($allActiveEmployees as $employee) {
            $attendance = $attendances->get($employee->id);
            $issues = [];

            if (!$attendance) {
                // Employee has NO attendance record for this date
                if ($type === 'all' || $type === 'no_record') {
                    $issues[] = 'No attendance record';
                }
            } else {
                // Check for missing time out (has time_in but no time_out)
                if (($type === 'all' || $type === 'missing_timeout')
                    && $attendance->time_in
                    && !$attendance->time_out
                    && $attendance->status !== 'absent'
                ) {
                    $issues[] = 'Missing time out';
                }

                // Check for missing break end (has break_start but no break_end)
                if (($type === 'all' || $type === 'missing_breakout')
                    && $attendance->break_start
                    && !$attendance->break_end
                ) {
                    $issues[] = 'Missing break out';
                }

                // Check for missing OT time out (has ot_time_in but no ot_time_out)
                if (($type === 'all' || $type === 'missing_ot_timeout')
                    && $attendance->ot_time_in
                    && !$attendance->ot_time_out
                ) {
                    $issues[] = 'Missing OT time out';
                }
            }

            // If there are any issues, add to missing records
            if (!empty($issues)) {
                $missingRecords[] = [
                    'employee_id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                    'position' => $employee->positionRate?->position_name ?? $employee->position ?? 'N/A',
                    'department' => $employee->project?->name ?? $employee->department ?? 'N/A',
                    'issues' => $issues,
                    'attendance' => $attendance ? [
                        'id' => $attendance->id,
                        'employee_id' => $employee->id,
                        'attendance_date' => $attendance->attendance_date,
                        'time_in' => $attendance->time_in,
                        'time_out' => $attendance->time_out,
                        'break_start' => $attendance->break_start,
                        'break_end' => $attendance->break_end,
                        'ot_time_in' => $attendance->ot_time_in,
                        'ot_time_out' => $attendance->ot_time_out,
                        'status' => $attendance->status,
                    ] : null,
                ];
            }
        }

        return response()->json([
            'date' => $date,
            'total_employees' => $allActiveEmployees->count(),
            'total_attendance_records' => $totalWithAttendance,
            'employees_with_issues' => count($missingRecords),
            'missing_records' => $missingRecords,
        ]);
    }
}
