<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeave;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class LeaveController extends Controller
{
    /**
     * Update employee activity status based on current leaves
     */
    private function syncEmployeeLeaveStatus($employeeId)
    {
        $employee = Employee::find($employeeId);
        if (!$employee) return;

        $today = Carbon::today();

        // Check if employee has any active approved leaves today
        $hasActiveLeave = EmployeeLeave::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('leave_date_from', '<=', $today)
            ->whereDate('leave_date_to', '>=', $today)
            ->exists();

        // Update status immediately
        if ($hasActiveLeave && $employee->activity_status !== 'on_leave') {
            $employee->update(['activity_status' => 'on_leave']);
        } elseif (!$hasActiveLeave && $employee->activity_status === 'on_leave') {
            $employee->update(['activity_status' => 'active']);
        }
    }
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = EmployeeLeave::with(['employee', 'leaveType', 'approvedBy']);

        // If employee role, only show their leaves
        if ($user->role === 'employee') {
            $query->where('employee_id', $user->employee_id);
        }

        // Filter by employee ID (for admin/hr)
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by leave type
        if ($request->has('leave_type_id')) {
            $query->where('leave_type_id', $request->leave_type_id);
        }

        // Filter for pending approvals (HR view)
        if ($request->has('pending_only') && $request->pending_only) {
            $query->where('status', 'pending');
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        // Determine employee_id based on role
        if ($user->role === 'employee') {
            // If employee_id is not set, try to find employee by user_id
            $employeeId = $user->employee_id;
            if (!$employeeId) {
                $employee = Employee::where('user_id', $user->id)->first();
                if (!$employee) {
                    return response()->json([
                        'message' => 'Employee record not found for your account'
                    ], 404);
                }
                $employeeId = $employee->id;
            }
        } else {
            $employeeId = $request->input('employee_id');
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => $user->role === 'employee' ? 'nullable' : 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'leave_date_from' => 'required|date|after_or_equal:today',
            'leave_date_to' => 'required|date|after_or_equal:leave_date_from',
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Calculate number of days (excluding weekends - optional)
        $startDate = Carbon::parse($request->leave_date_from);
        $endDate = Carbon::parse($request->leave_date_to);
        $numberOfDays = $startDate->diffInDays($endDate) + 1;

        DB::beginTransaction();

        try {
            $leave = EmployeeLeave::create([
                'employee_id' => $employeeId,
                'leave_type_id' => $request->leave_type_id,
                'leave_date_from' => $request->leave_date_from,
                'leave_date_to' => $request->leave_date_to,
                'number_of_days' => $numberOfDays,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            // Create audit log
            AuditLog::create([
                'module' => 'leaves',
                'action' => 'create',
                'description' => $user->role === 'employee'
                    ? "Employee filed leave request for {$numberOfDays} day(s)"
                    : "Leave request created for employee ID {$employeeId}",
                'user_id' => $user->id,
                'record_id' => $leave->id,
                'new_values' => json_encode($leave->toArray()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Leave request submitted successfully',
                'data' => $leave->load(['employee', 'leaveType'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to submit leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(EmployeeLeave $leave)
    {
        $user = Auth::user();

        // Employees can only view their own leaves
        if ($user->role === 'employee' && $leave->employee_id !== $user->employee_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($leave->load(['employee', 'leaveType', 'approvedBy']));
    }

    public function update(Request $request, EmployeeLeave $leave)
    {
        $user = Auth::user();

        // Employees can only update their own pending leaves
        if ($user->role === 'employee' && $leave->employee_id !== $user->employee_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only pending leaves can be updated
        if ($leave->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending leave requests can be updated'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'sometimes|exists:leave_types,id',
            'leave_date_from' => 'sometimes|date',
            'leave_date_to' => 'sometimes|date|after_or_equal:leave_date_from',
            'reason' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $data = $request->only(['leave_type_id', 'leave_date_from', 'leave_date_to', 'reason']);

            // Recalculate days if dates changed
            if ($request->has('leave_date_from') || $request->has('leave_date_to')) {
                $startDate = Carbon::parse($request->leave_date_from ?? $leave->leave_date_from);
                $endDate = Carbon::parse($request->leave_date_to ?? $leave->leave_date_to);
                $data['number_of_days'] = $startDate->diffInDays($endDate) + 1;
            }

            $oldValues = $leave->toArray();
            $leave->update($data);

            // Create audit log
            AuditLog::create([
                'module' => 'leaves',
                'action' => 'update',
                'description' => "Leave request updated",
                'user_id' => $user->id,
                'record_id' => $leave->id,
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode($leave->fresh()->toArray()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Leave request updated successfully',
                'data' => $leave->load(['employee', 'leaveType'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(EmployeeLeave $leave)
    {
        $user = Auth::user();

        // Employees can only delete their own pending leaves
        if ($user->role === 'employee' && $leave->employee_id !== $user->employee_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only pending leaves can be deleted
        if ($leave->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending leave requests can be deleted'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $employeeId = $leave->employee_id;

            // Create audit log before deletion
            AuditLog::create([
                'module' => 'leaves',
                'action' => 'delete',
                'description' => "Leave request deleted",
                'user_id' => $user->id,
                'record_id' => $leave->id,
                'old_values' => json_encode($leave->toArray()),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $leave->delete();

            // Instantly sync employee activity status (may return to active if no other leaves)
            $this->syncEmployeeLeaveStatus($employeeId);

            DB::commit();

            return response()->json(['message' => 'Leave request deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function approve(Request $request, EmployeeLeave $leave)
    {
        // Only admin/hr can approve
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'accountant'])) {
            return response()->json(['message' => 'Unauthorized. Only HR/Admin can approve leaves.'], 403);
        }

        if ($leave->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending leave requests can be approved'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $leave->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => $request->remarks,
            ]);

            // Instantly sync employee activity status
            $this->syncEmployeeLeaveStatus($leave->employee_id);

            // Create audit log
            AuditLog::create([
                'module' => 'leaves',
                'action' => 'approve',
                'description' => "Leave request approved for employee ID {$leave->employee_id}",
                'user_id' => $user->id,
                'record_id' => $leave->id,
                'old_values' => json_encode(['status' => 'pending']),
                'new_values' => json_encode(['status' => 'approved']),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Leave request approved successfully',
                'data' => $leave->load(['employee', 'leaveType', 'approvedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to approve leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, EmployeeLeave $leave)
    {
        // Only admin/hr can reject
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'accountant'])) {
            return response()->json(['message' => 'Unauthorized. Only HR/Admin can reject leaves.'], 403);
        }

        if ($leave->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending leave requests can be rejected'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $leave->update([
                'status' => 'rejected',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Instantly sync employee activity status (may return to active if no other leaves)
            $this->syncEmployeeLeaveStatus($leave->employee_id);

            // Create audit log
            AuditLog::create([
                'module' => 'leaves',
                'action' => 'reject',
                'description' => "Leave request rejected for employee ID {$leave->employee_id}",
                'user_id' => $user->id,
                'record_id' => $leave->id,
                'old_values' => json_encode(['status' => 'pending']),
                'new_values' => json_encode(['status' => 'rejected', 'rejection_reason' => $request->rejection_reason]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Leave request rejected',
                'data' => $leave->load(['employee', 'leaveType', 'approvedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to reject leave request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function employeeCredits($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $user = Auth::user();

        // Employees can only view their own credits
        if ($user->role === 'employee') {
            $userEmployeeId = $user->employee_id;
            if (!$userEmployeeId) {
                $userEmployee = Employee::where('user_id', $user->id)->first();
                $userEmployeeId = $userEmployee?->id;
            }

            if ($employee->id !== $userEmployeeId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $leaveTypes = LeaveType::active()->get();
        $currentYear = Carbon::now()->year;

        $credits = [];
        foreach ($leaveTypes as $leaveType) {
            $usedDays = EmployeeLeave::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->whereYear('leave_date_from', $currentYear)
                ->sum('number_of_days');

            $credits[] = [
                'leave_type' => $leaveType,
                'total_credits' => $leaveType->default_credits,
                'used_credits' => $usedDays,
                'available_credits' => max(0, $leaveType->default_credits - $usedDays),
            ];
        }

        return response()->json([
            'employee' => $employee,
            'year' => $currentYear,
            'leave_credits' => $credits,
        ]);
    }

    /**
     * Get my leaves (for employee portal)
     */
    public function myLeaves(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'employee') {
            return response()->json(['message' => 'This endpoint is for employees only'], 403);
        }

        // Get employee_id from user or lookup via user_id
        $employeeId = $user->employee_id;
        if (!$employeeId) {
            $employee = Employee::where('user_id', $user->id)->first();
            if (!$employee) {
                return response()->json(['message' => 'Employee record not found'], 404);
            }
            $employeeId = $employee->id;
        }

        $query = EmployeeLeave::with(['leaveType', 'approvedBy'])
            ->where('employee_id', $employeeId);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * Get pending leaves for approval (HR)
     */
    public function pendingLeaves(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'accountant'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = EmployeeLeave::with(['employee', 'leaveType'])
            ->where('status', 'pending');

        return response()->json($query->latest()->get());
    }

    /**
     * Get my leave credits (for employee portal)
     */
    public function myCredits(Request $request)
    {
        $user = Auth::user();

        if ($user->role !== 'employee') {
            return response()->json(['message' => 'This endpoint is for employees only'], 403);
        }

        // Get employee_id from user or lookup via user_id
        $employeeId = $user->employee_id;
        if (!$employeeId) {
            $employee = Employee::where('user_id', $user->id)->first();
            if (!$employee) {
                return response()->json(['message' => 'Employee record not found'], 404);
            }
            $employeeId = $employee->id;
        } else {
            $employee = Employee::find($employeeId);
        }

        $leaveTypes = LeaveType::active()->get();
        $currentYear = Carbon::now()->year;

        $credits = [];
        foreach ($leaveTypes as $leaveType) {
            $usedDays = EmployeeLeave::where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->whereYear('leave_date_from', $currentYear)
                ->sum('number_of_days');

            $credits[] = [
                'leave_type' => $leaveType,
                'total_credits' => $leaveType->default_credits,
                'used_credits' => $usedDays,
                'available_credits' => max(0, $leaveType->default_credits - $usedDays),
            ];
        }

        return response()->json([
            'employee' => $employee,
            'year' => $currentYear,
            'leave_credits' => $credits,
        ]);
    }
}
