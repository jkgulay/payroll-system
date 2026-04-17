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

    private function isLeaveManager($user): bool
    {
        return in_array($user->role, ['admin', 'hr'], true);
    }

    private function canUseMyLeavePortal($user): bool
    {
        return in_array($user->role, ['employee', 'hr', 'payrollist'], true);
    }

    private function resolveUserEmployeeId($user): ?int
    {
        if (!empty($user->employee_id)) {
            return (int) $user->employee_id;
        }

        return Employee::where('user_id', $user->id)->value('id');
    }

    private function ensureCanAccessLeave($user, EmployeeLeave $leave)
    {
        if ($this->isLeaveManager($user)) {
            return null;
        }

        $employeeId = $this->resolveUserEmployeeId($user);
        if (!$employeeId || (int) $leave->employee_id !== (int) $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return null;
    }

    private function applyListFilters($query, Request $request, bool $includeStatus = true)
    {
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($includeStatus && $request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('leave_type_id')) {
            $query->where('leave_type_id', $request->input('leave_type_id'));
        }

        if ($request->boolean('pending_only')) {
            $query->where('status', 'pending');
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('reason', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('leaveType', function ($leaveTypeQuery) use ($search) {
                        $leaveTypeQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$this->isLeaveManager($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $query = EmployeeLeave::with(['employee', 'leaveType', 'approvedBy']);

        $this->applyListFilters($query, $request, true);

        return response()->json($query->latest()->paginate($perPage));
    }

    /**
     * Manually set leave on behalf of an employee and auto-approve it.
     */
    public function setLeave(Request $request)
    {
        $user = Auth::user();

        if (!$this->isLeaveManager($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'leave_date_from' => 'required|date|after_or_equal:today',
            'leave_date_to' => 'required|date|after_or_equal:leave_date_from',
            'reason' => 'required|string|max:1000',
            'approval_remarks' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $startDate = Carbon::parse($request->leave_date_from);
        $endDate = Carbon::parse($request->leave_date_to);
        $numberOfDays = $startDate->diffInDays($endDate) + 1;

        DB::beginTransaction();

        try {
            $leave = EmployeeLeave::create([
                'employee_id' => $request->input('employee_id'),
                'leave_type_id' => $request->input('leave_type_id'),
                'leave_date_from' => $request->input('leave_date_from'),
                'leave_date_to' => $request->input('leave_date_to'),
                'number_of_days' => $numberOfDays,
                'reason' => $request->input('reason'),
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_remarks' => $request->input('approval_remarks'),
                'rejection_reason' => null,
            ]);

            // Immediately reflect today's approved leave in employee activity status.
            $this->syncEmployeeLeaveStatus((int) $request->input('employee_id'));

            AuditLog::create([
                'module' => 'leaves',
                'action' => 'set_leave',
                'description' => "Leave manually set and approved for employee ID {$request->input('employee_id')}",
                'user_id' => $user->id,
                'record_id' => $leave->id,
                'new_values' => json_encode($leave->toArray()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Leave was set and approved successfully',
                'data' => $leave->load(['employee', 'leaveType', 'approvedBy']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to set leave',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'hr', 'employee', 'payrollist'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $canCreateForAnotherEmployee = $this->isLeaveManager($user) && $request->filled('employee_id');
        $employeeId = $canCreateForAnotherEmployee
            ? (int) $request->input('employee_id')
            : $this->resolveUserEmployeeId($user);

        if (!$employeeId) {
            return response()->json([
                'message' => 'Employee record not found for your account'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => $canCreateForAnotherEmployee ? 'required|exists:employees,id' : 'nullable',
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
                'description' => $canCreateForAnotherEmployee
                    ? "Leave request created for employee ID {$employeeId}"
                    : (($user->role === 'employee' || $user->role === 'payrollist' || $user->role === 'hr')
                        ? "Employee filed leave request for {$numberOfDays} day(s)"
                        : "Leave request created"),
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

        $accessError = $this->ensureCanAccessLeave($user, $leave);
        if ($accessError) {
            return $accessError;
        }

        return response()->json($leave->load(['employee', 'leaveType', 'approvedBy']));
    }

    public function update(Request $request, EmployeeLeave $leave)
    {
        $user = Auth::user();

        $accessError = $this->ensureCanAccessLeave($user, $leave);
        if ($accessError) {
            return $accessError;
        }

        // Only pending leaves can be updated
        if ($leave->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending leave requests can be updated'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'sometimes|exists:leave_types,id',
            'leave_date_from' => 'sometimes|date|after_or_equal:today',
            'leave_date_to' => 'sometimes|date',
            'reason' => 'sometimes|string|max:1000',
        ]);

        $validator->after(function ($validator) use ($request, $leave) {
            $startDate = $request->input('leave_date_from', optional($leave->leave_date_from)->toDateString());
            $endDate = $request->input('leave_date_to', optional($leave->leave_date_to)->toDateString());

            if ($startDate && $endDate && Carbon::parse($endDate)->lt(Carbon::parse($startDate))) {
                $validator->errors()->add(
                    'leave_date_to',
                    'The leave date to must be a date after or equal to leave date from.'
                );
            }
        });

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

        $accessError = $this->ensureCanAccessLeave($user, $leave);
        if ($accessError) {
            return $accessError;
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
        if (!in_array($user->role, ['admin', 'hr'])) {
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
                'approval_remarks' => $request->remarks,
                'rejection_reason' => null,
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
        if (!in_array($user->role, ['admin', 'hr'])) {
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
                'approval_remarks' => null,
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

        // Non-manager roles can only view their own credits
        if (!$this->isLeaveManager($user)) {
            $userEmployeeId = $this->resolveUserEmployeeId($user);
            if (!$userEmployeeId || (int) $employee->id !== (int) $userEmployeeId) {
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
     * Get aggregated leave stats for approval dashboard
     */
    public function stats(Request $request)
    {
        $user = Auth::user();

        if (!$this->isLeaveManager($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = EmployeeLeave::query();
        $this->applyListFilters($query, $request, false);

        $countsByStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pending = (int) ($countsByStatus['pending'] ?? 0);
        $approved = (int) ($countsByStatus['approved'] ?? 0);
        $rejected = (int) ($countsByStatus['rejected'] ?? 0);
        $cancelled = (int) ($countsByStatus['cancelled'] ?? 0);

        return response()->json([
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'cancelled' => $cancelled,
            'total' => $pending + $approved + $rejected + $cancelled,
        ]);
    }

    /**
     * Get my leaves (for employee, HR, and payrollist portal)
     */
    public function myLeaves(Request $request)
    {
        $user = Auth::user();

        if (!$this->canUseMyLeavePortal($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $employeeId = $this->resolveUserEmployeeId($user);
        if (!$employeeId) {
            return response()->json(['message' => 'Employee record not found for this account'], 404);
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

        if (!in_array($user->role, ['admin', 'hr'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = EmployeeLeave::with(['employee', 'leaveType'])
            ->where('status', 'pending');

        return response()->json($query->latest()->get());
    }

    /**
     * Get my leave credits (for employee, HR, and payrollist portal)
     */
    public function myCredits(Request $request)
    {
        $user = Auth::user();

        if (!$this->canUseMyLeavePortal($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $employeeId = $this->resolveUserEmployeeId($user);
        if (!$employeeId) {
            return response()->json(['message' => 'Employee record not found for this account'], 404);
        }

        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json(['message' => 'Employee record not found'], 404);
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
