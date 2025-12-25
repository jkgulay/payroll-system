<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with(['employee.department', 'employee.location']);

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest('date')->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:present,absent,late,half_day,on_leave',
        ]);

        // Calculate hours if both times provided
        $hoursWorked = null;
        if ($request->time_in && $request->time_out) {
            $timeIn = Carbon::parse($request->time_in);
            $timeOut = Carbon::parse($request->time_out);
            $hoursWorked = $timeOut->diffInHours($timeIn);
        }

        $attendance = Attendance::create([
            'employee_id' => $validated['employee_id'],
            'date' => $validated['date'],
            'time_in' => $validated['time_in'],
            'time_out' => $validated['time_out'],
            'status' => $validated['status'],
            'hours_worked' => $hoursWorked,
            'notes' => $request->notes,
        ]);

        return response()->json($attendance->load('employee'), 201);
    }

    public function show(Attendance $attendance)
    {
        return response()->json($attendance->load(['employee.department', 'employee.location']));
    }

    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'status' => 'in:present,absent,late,half_day,on_leave',
        ]);

        $data = $request->only(['time_in', 'time_out', 'status', 'notes']);

        // Recalculate hours if times changed
        if ($request->has('time_in') || $request->has('time_out')) {
            $timeIn = $request->time_in ?? $attendance->time_in;
            $timeOut = $request->time_out ?? $attendance->time_out;

            if ($timeIn && $timeOut) {
                $data['hours_worked'] = Carbon::parse($timeOut)->diffInHours(Carbon::parse($timeIn));
            }
        }

        $attendance->update($data);

        return response()->json($attendance);
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return response()->json(['message' => 'Attendance record deleted successfully']);
    }

    public function importBiometric(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx',
        ]);

        // TODO: Implement biometric file parsing logic
        return response()->json([
            'message' => 'Biometric import functionality will be implemented',
            'imported' => 0,
        ]);
    }

    public function approve(Attendance $attendance)
    {
        $attendance->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Attendance approved successfully',
            'attendance' => $attendance,
        ]);
    }

    public function reject(Attendance $attendance)
    {
        $attendance->update(['status' => 'rejected']);

        return response()->json([
            'message' => 'Attendance rejected',
            'attendance' => $attendance,
        ]);
    }

    public function employeeSummary($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $summary = [
            'employee' => $employee,
            'total_present' => Attendance::where('employee_id', $employeeId)->where('status', 'present')->count(),
            'total_absent' => Attendance::where('employee_id', $employeeId)->where('status', 'absent')->count(),
            'total_late' => Attendance::where('employee_id', $employeeId)->where('status', 'late')->count(),
            'total_hours' => Attendance::where('employee_id', $employeeId)->sum('hours_worked'),
        ];

        return response()->json($summary);
    }
}
