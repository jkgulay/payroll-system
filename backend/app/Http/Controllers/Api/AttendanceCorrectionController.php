<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceCorrectionController extends Controller
{
    /**
     * Get all correction requests
     */
    public function index(Request $request)
    {
        $query = AttendanceCorrection::with([
            'attendance',
            'employee.department',
            'requestedBy',
            'approvedBy'
        ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // For employee portal - only show their own requests
        if ($request->has('my_requests') && auth()->user()) {
            $employee = \App\Models\Employee::where('user_id', auth()->id())->first();
            if ($employee) {
                $query->where('employee_id', $employee->id);
            }
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * Request attendance correction
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'attendance_id' => 'required|exists:attendance,id',
            'requested_time_in' => 'nullable|date_format:H:i',
            'requested_time_out' => 'nullable|date_format:H:i',
            'requested_status' => 'nullable|string',
            'reason' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $attendance = Attendance::findOrFail($request->attendance_id);

        // Check if correction already exists for this attendance
        $existing = AttendanceCorrection::where('attendance_id', $request->attendance_id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'A pending correction request already exists for this attendance record'
            ], 400);
        }

        $correction = AttendanceCorrection::create([
            'attendance_id' => $request->attendance_id,
            'employee_id' => $attendance->employee_id,
            'requested_by' => auth()->id(),
            'original_time_in' => $attendance->time_in,
            'original_time_out' => $attendance->time_out,
            'original_status' => $attendance->status,
            'requested_time_in' => $request->requested_time_in,
            'requested_time_out' => $request->requested_time_out,
            'requested_status' => $request->requested_status,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Attendance correction request submitted successfully',
            'correction' => $correction->load(['attendance', 'employee', 'requestedBy']),
        ], 201);
    }

    /**
     * Approve correction request
     */
    public function approve(Request $request, $id)
    {
        $correction = AttendanceCorrection::findOrFail($id);

        if ($correction->status !== 'pending') {
            return response()->json(['message' => 'Correction request already processed'], 400);
        }

        $validator = Validator::make($request->all(), [
            'approval_remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Update attendance record
            $attendance = $correction->attendance;
            
            if ($correction->requested_time_in) {
                $attendance->time_in = $correction->requested_time_in;
            }
            
            if ($correction->requested_time_out) {
                $attendance->time_out = $correction->requested_time_out;
            }
            
            if ($correction->requested_status) {
                $attendance->status = $correction->requested_status;
            }

            // Recalculate hours if both time_in and time_out exist
            if ($attendance->time_in && $attendance->time_out) {
                $timeIn = \Carbon\Carbon::parse($attendance->time_in);
                $timeOut = \Carbon\Carbon::parse($attendance->time_out);
                $attendance->hours_worked = $timeOut->diffInHours($timeIn);
            }

            $attendance->save();

            // Update correction status
            $correction->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'approval_remarks' => $request->approval_remarks,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Attendance correction approved successfully',
                'correction' => $correction->load(['attendance', 'employee', 'approvedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to approve correction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject correction request
     */
    public function reject(Request $request, $id)
    {
        $correction = AttendanceCorrection::findOrFail($id);

        if ($correction->status !== 'pending') {
            return response()->json(['message' => 'Correction request already processed'], 400);
        }

        $validator = Validator::make($request->all(), [
            'approval_remarks' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $correction->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_remarks' => $request->approval_remarks,
        ]);

        return response()->json([
            'message' => 'Attendance correction rejected',
            'correction' => $correction,
        ]);
    }

    /**
     * Get specific correction request
     */
    public function show($id)
    {
        $correction = AttendanceCorrection::with([
            'attendance',
            'employee.department',
            'requestedBy',
            'approvedBy'
        ])->findOrFail($id);

        return response()->json($correction);
    }
}
