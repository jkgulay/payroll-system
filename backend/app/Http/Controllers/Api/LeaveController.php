<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeave;
use App\Models\Employee;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeLeave::with(['employee', 'leaveType']);

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        // Calculate number of days
        $startDate = \Carbon\Carbon::parse($validated['start_date']);
        $endDate = \Carbon\Carbon::parse($validated['end_date']);
        $numberOfDays = $startDate->diffInDays($endDate) + 1;

        $leave = EmployeeLeave::create([
            ...$validated,
            'number_of_days' => $numberOfDays,
            'status' => 'pending',
        ]);

        return response()->json($leave->load(['employee', 'leaveType']), 201);
    }

    public function show(EmployeeLeave $leave)
    {
        return response()->json($leave->load(['employee', 'leaveType']));
    }

    public function update(Request $request, EmployeeLeave $leave)
    {
        $validated = $request->validate([
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
        ]);

        $data = $validated;

        // Recalculate days if dates changed
        if ($request->has('start_date') || $request->has('end_date')) {
            $startDate = \Carbon\Carbon::parse($request->start_date ?? $leave->start_date);
            $endDate = \Carbon\Carbon::parse($request->end_date ?? $leave->end_date);
            $data['number_of_days'] = $startDate->diffInDays($endDate) + 1;
        }

        $leave->update($data);

        return response()->json($leave);
    }

    public function destroy(EmployeeLeave $leave)
    {
        $leave->delete();

        return response()->json(['message' => 'Leave request deleted successfully']);
    }

    public function approve(EmployeeLeave $leave)
    {
        $leave->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Leave request approved',
            'leave' => $leave,
        ]);
    }

    public function reject(Request $request, EmployeeLeave $leave)
    {
        $leave->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'remarks' => $request->remarks,
        ]);

        return response()->json([
            'message' => 'Leave request rejected',
            'leave' => $leave,
        ]);
    }

    public function employeeCredits($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        // TODO: Implement leave credits calculation based on leave types and used days
        $credits = [
            'employee' => $employee,
            'leave_credits' => [],
        ];

        return response()->json($credits);
    }
}
