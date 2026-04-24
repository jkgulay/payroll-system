<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveOut;
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

    private function canCreateLeaveForEmployees($user): bool
    {
        return in_array($user->role, ['admin', 'hr', 'payrollist'], true);
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

    private function ensureLeaveEditable(EmployeeLeave $leave)
    {
        if ((bool) ($leave->is_locked ?? false)) {
            return response()->json([
                'message' => 'This leave record is locked because it has been used in payroll and can no longer be modified.'
            ], 422);
        }

        return null;
    }

    private function ensureCanSetLeaveCompensation($user, Request $request)
    {
        if (!$request->has('is_with_pay')) {
            return null;
        }

        if (!$this->isLeaveManager($user)) {
            return response()->json([
                'message' => 'Unauthorized. Only HR/Admin can set leave compensation.'
            ], 403);
        }

        return null;
    }

    private function resolveLeaveCompensationFlag(Request $request, int $leaveTypeId): bool
    {
        if ($request->has('is_with_pay')) {
            return filter_var($request->input('is_with_pay'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        }

        return (bool) LeaveType::whereKey($leaveTypeId)->value('is_paid');
    }

    private function deriveLeaveUnits(string $leaveDateFrom, string $leaveDateTo, string $durationType, ?float $durationHours): array
    {
        $startDate = Carbon::parse($leaveDateFrom);
        $endDate = Carbon::parse($leaveDateTo);
        $standardHours = max((float) config('payroll.standard_hours_per_day', 8), 1);

        if ($durationType === 'half_day') {
            return [
                'number_of_days' => 0.5,
                'duration_hours' => round($standardHours / 2, 2),
            ];
        }

        if ($durationType === 'hours') {
            $hours = max((float) ($durationHours ?? 0), 0);

            return [
                'number_of_days' => round($hours / $standardHours, 2),
                'duration_hours' => round($hours, 2),
            ];
        }

        return [
            'number_of_days' => (float) ($startDate->diffInDays($endDate) + 1),
            'duration_hours' => null,
        ];
    }

    private function validateDurationPayload($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            $leaveDateFrom = $request->input('leave_date_from');
            $leaveDateTo = $request->input('leave_date_to');
            $durationType = (string) $request->input('duration_type', 'full_day');
            $durationHours = $request->input('duration_hours');
            $standardHours = max((float) config('payroll.standard_hours_per_day', 8), 1);

            if (!$leaveDateFrom || !$leaveDateTo) {
                return;
            }

            if (Carbon::parse($leaveDateTo)->lt(Carbon::parse($leaveDateFrom))) {
                $validator->errors()->add(
                    'leave_date_to',
                    'The leave date to must be a date after or equal to leave date from.'
                );
            }

            if (in_array($durationType, ['half_day', 'hours'], true) && $leaveDateFrom !== $leaveDateTo) {
                $validator->errors()->add(
                    'leave_date_to',
                    'Half-day and hourly leave requests must use the same start and end date.'
                );
            }

            if ($durationType === 'hours') {
                if ($durationHours === null || $durationHours === '') {
                    $validator->errors()->add('duration_hours', 'Duration hours is required for hourly leave requests.');
                    return;
                }

                $hours = (float) $durationHours;
                if ($hours <= 0) {
                    $validator->errors()->add('duration_hours', 'Duration hours must be greater than zero.');
                }

                if ($hours > $standardHours) {
                    $validator->errors()->add(
                        'duration_hours',
                        'Duration hours must not exceed the configured standard hours per day.'
                    );
                }
            }
        });
    }

    private function syncLeaveOutForApprovedLeave(EmployeeLeave $leave, ?int $createdBy = null): void
    {
        if ($leave->status !== 'approved') {
            return;
        }

        if ($leave->isWithPay()) {
            $this->deleteUnusedLeaveOutForLeave($leave);
            return;
        }

        $leaveOut = EmployeeLeaveOut::updateOrCreate(
            ['employee_leave_id' => $leave->id],
            [
                'employee_id' => $leave->employee_id,
                'leave_type_id' => $leave->leave_type_id,
                'leave_date_from' => optional($leave->leave_date_from)->toDateString(),
                'leave_date_to' => optional($leave->leave_date_to)->toDateString(),
                'duration_type' => $leave->duration_type ?? 'full_day',
                'quantity_days' => (float) ($leave->number_of_days ?? 0),
                'quantity_hours' => (float) ($leave->duration_hours ?? 0),
                'created_by' => $createdBy,
            ]
        );

        if (!$leaveOut->created_by && $createdBy) {
            $leaveOut->created_by = $createdBy;
            $leaveOut->save();
        }
    }

    private function deleteUnusedLeaveOutForLeave(EmployeeLeave $leave): void
    {
        $leaveOut = $leave->relationLoaded('leaveOut')
            ? $leave->leaveOut
            : $leave->leaveOut()->first();

        if (!$leaveOut) {
            return;
        }

        if ($leaveOut->payrollLinks()->exists()) {
            return;
        }

        $leaveOut->delete();
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

        if ($request->filled('is_with_pay')) {
            $query->where('is_with_pay', $request->boolean('is_with_pay'));
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

        $query = EmployeeLeave::with(['employee', 'leaveType', 'approvedBy', 'leaveOut']);

        $this->applyListFilters($query, $request, true);

        return response()->json($query->latest()->paginate($perPage));
    }

    /**
     * Manually set leave on behalf of an employee.
     * Admin-created records are auto-approved; HR/payrollist records stay pending.
     */
    public function setLeave(Request $request)
    {
        $user = Auth::user();

        if (!$this->canCreateLeaveForEmployees($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'leave_date_from' => 'required|date|after_or_equal:today',
            'leave_date_to' => 'required|date|after_or_equal:leave_date_from',
            'duration_type' => 'nullable|in:full_day,half_day,hours',
            'duration_hours' => 'nullable|numeric|min:0.01',
            'is_with_pay' => 'nullable|boolean',
            'reason' => 'required|string|max:1000',
            'approval_remarks' => 'nullable|string|max:500',
        ]);

        $this->validateDurationPayload($validator, $request);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $compensationError = $this->ensureCanSetLeaveCompensation($user, $request);
        if ($compensationError) {
            return $compensationError;
        }

        $durationType = (string) $request->input('duration_type', 'full_day');
        $units = $this->deriveLeaveUnits(
            $request->input('leave_date_from'),
            $request->input('leave_date_to'),
            $durationType,
            $request->filled('duration_hours') ? (float) $request->input('duration_hours') : null
        );
        $isAutoApproved = $user->role === 'admin';
        $isWithPay = $isAutoApproved
            ? $this->resolveLeaveCompensationFlag($request, (int) $request->input('leave_type_id'))
            : null;

        DB::beginTransaction();

        try {
            $leave = EmployeeLeave::create([
                'employee_id' => $request->input('employee_id'),
                'leave_type_id' => $request->input('leave_type_id'),
                'leave_date_from' => $request->input('leave_date_from'),
                'leave_date_to' => $request->input('leave_date_to'),
                'duration_type' => $durationType,
                'duration_hours' => $units['duration_hours'],
                'number_of_days' => $units['number_of_days'],
                'is_with_pay' => $isWithPay,
                'reason' => $request->input('reason'),
                'status' => $isAutoApproved ? 'approved' : 'pending',
                'approved_by' => $isAutoApproved ? $user->id : null,
                'approved_at' => $isAutoApproved ? now() : null,
                'approval_remarks' => $isAutoApproved ? $request->input('approval_remarks') : null,
                'rejection_reason' => null,
                'is_locked' => false,
                'locked_by_payroll_id' => null,
                'locked_at' => null,
            ]);

            if ($isAutoApproved) {
                $this->syncLeaveOutForApprovedLeave($leave, (int) $user->id);
            }

            if ($isAutoApproved) {
                // Immediately reflect today's approved leave in employee activity status.
                $this->syncEmployeeLeaveStatus((int) $request->input('employee_id'));
            }

            AuditLog::create([
                'module' => 'leaves',
                'action' => 'set_leave',
                'description' => $isAutoApproved
                    ? "Leave manually set and approved for employee ID {$request->input('employee_id')}"
                    : "Leave manually set and submitted for approval for employee ID {$request->input('employee_id')}",
                'user_id' => $user->id,
                'record_id' => $leave->id,
                'new_values' => json_encode($leave->toArray()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => $isAutoApproved
                    ? 'Leave was set and approved successfully'
                    : 'Leave was set and submitted for approval successfully',
                'data' => $leave->load(['employee', 'leaveType', 'approvedBy', 'leaveOut']),
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

        $canCreateForAnotherEmployee = $this->canCreateLeaveForEmployees($user) && $request->filled('employee_id');
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
            'duration_type' => 'nullable|in:full_day,half_day,hours',
            'duration_hours' => 'nullable|numeric|min:0.01',
            'is_with_pay' => 'nullable|boolean',
            'reason' => 'required|string|max:1000',
        ]);

        $this->validateDurationPayload($validator, $request);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $compensationError = $this->ensureCanSetLeaveCompensation($user, $request);
        if ($compensationError) {
            return $compensationError;
        }

        $durationType = (string) $request->input('duration_type', 'full_day');
        $units = $this->deriveLeaveUnits(
            $request->input('leave_date_from'),
            $request->input('leave_date_to'),
            $durationType,
            $request->filled('duration_hours') ? (float) $request->input('duration_hours') : null
        );
        $isAutoApproved = $user->role === 'admin';
        $isWithPay = $isAutoApproved
            ? $this->resolveLeaveCompensationFlag($request, (int) $request->input('leave_type_id'))
            : null;

        DB::beginTransaction();

        try {
            $leave = EmployeeLeave::create([
                'employee_id' => $employeeId,
                'leave_type_id' => $request->leave_type_id,
                'leave_date_from' => $request->leave_date_from,
                'leave_date_to' => $request->leave_date_to,
                'duration_type' => $durationType,
                'duration_hours' => $units['duration_hours'],
                'number_of_days' => $units['number_of_days'],
                'is_with_pay' => $isWithPay,
                'reason' => $request->reason,
                'status' => $isAutoApproved ? 'approved' : 'pending',
                'approved_by' => $isAutoApproved ? $user->id : null,
                'approved_at' => $isAutoApproved ? now() : null,
                'approval_remarks' => null,
                'rejection_reason' => null,
                'is_locked' => false,
                'locked_by_payroll_id' => null,
                'locked_at' => null,
            ]);

            if ($isAutoApproved) {
                $this->syncLeaveOutForApprovedLeave($leave, (int) $user->id);
                $this->syncEmployeeLeaveStatus($employeeId);
            }

            // Create audit log
            AuditLog::create([
                'module' => 'leaves',
                'action' => 'create',
                'description' => $canCreateForAnotherEmployee
                    ? ($isAutoApproved
                        ? "Leave request created and auto-approved for employee ID {$employeeId}"
                        : "Leave request created for employee ID {$employeeId}")
                    : (($user->role === 'employee' || $user->role === 'payrollist' || $user->role === 'hr')
                        ? "Employee filed leave request for {$units['number_of_days']} day(s)"
                        : "Leave request created"),
                'user_id' => $user->id,
                'record_id' => $leave->id,
                'new_values' => json_encode($leave->toArray()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => $isAutoApproved
                    ? 'Leave request created and approved successfully'
                    : 'Leave request submitted successfully',
                'data' => $leave->load(['employee', 'leaveType', 'approvedBy', 'leaveOut'])
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

        return response()->json($leave->load(['employee', 'leaveType', 'approvedBy', 'leaveOut']));
    }

    public function update(Request $request, EmployeeLeave $leave)
    {
        $user = Auth::user();

        $accessError = $this->ensureCanAccessLeave($user, $leave);
        if ($accessError) {
            return $accessError;
        }

        $lockError = $this->ensureLeaveEditable($leave);
        if ($lockError) {
            return $lockError;
        }

        // Only pending leaves can be updated
        if ($leave->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending leave requests can be updated'
            ], 422);
        }

        $payload = array_merge([
            'leave_type_id' => $leave->leave_type_id,
            'leave_date_from' => optional($leave->leave_date_from)->toDateString(),
            'leave_date_to' => optional($leave->leave_date_to)->toDateString(),
            'duration_type' => $leave->duration_type ?? 'full_day',
            'duration_hours' => $leave->duration_hours,
            'reason' => $leave->reason,
        ], $request->all());

        $validator = Validator::make($request->all(), [
            'leave_type_id' => 'sometimes|exists:leave_types,id',
            'leave_date_from' => 'sometimes|date|after_or_equal:today',
            'leave_date_to' => 'sometimes|date',
            'duration_type' => 'sometimes|in:full_day,half_day,hours',
            'duration_hours' => 'nullable|numeric|min:0.01',
            'reason' => 'sometimes|string|max:1000',
        ]);

        $this->validateDurationPayload($validator, new Request($payload));

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('is_with_pay')) {
            return response()->json([
                'message' => 'Compensation can only be set during leave approval.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $durationType = (string) ($payload['duration_type'] ?? 'full_day');
            $units = $this->deriveLeaveUnits(
                (string) $payload['leave_date_from'],
                (string) $payload['leave_date_to'],
                $durationType,
                isset($payload['duration_hours']) ? (float) $payload['duration_hours'] : null
            );

            $updatedLeaveTypeId = (int) ($payload['leave_type_id'] ?? $leave->leave_type_id);

            $data = [
                'leave_type_id' => $updatedLeaveTypeId,
                'leave_date_from' => $payload['leave_date_from'],
                'leave_date_to' => $payload['leave_date_to'],
                'duration_type' => $durationType,
                'duration_hours' => $units['duration_hours'],
                'number_of_days' => $units['number_of_days'],
                // Pending leave compensation remains undecided until approval.
                'is_with_pay' => null,
                'reason' => $payload['reason'],
            ];

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
                'data' => $leave->load(['employee', 'leaveType', 'leaveOut'])
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

        $lockError = $this->ensureLeaveEditable($leave);
        if ($lockError) {
            return $lockError;
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

        $lockError = $this->ensureLeaveEditable($leave);
        if ($lockError) {
            return $lockError;
        }

        $validator = Validator::make($request->all(), [
            'remarks' => 'nullable|string|max:500',
            'is_with_pay' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $isWithPay = $request->boolean('is_with_pay');

            $leave->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'approval_remarks' => $request->remarks,
                'rejection_reason' => null,
                'is_with_pay' => $isWithPay,
                'is_locked' => false,
                'locked_by_payroll_id' => null,
                'locked_at' => null,
            ]);

            $this->syncLeaveOutForApprovedLeave($leave->fresh(['leaveType', 'leaveOut']), (int) $user->id);

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
                'data' => $leave->load(['employee', 'leaveType', 'approvedBy', 'leaveOut']),
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

        $lockError = $this->ensureLeaveEditable($leave);
        if ($lockError) {
            return $lockError;
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
                'is_locked' => false,
                'locked_by_payroll_id' => null,
                'locked_at' => null,
            ]);

            $this->deleteUnusedLeaveOutForLeave($leave->fresh(['leaveOut']));

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
                'data' => $leave->load(['employee', 'leaveType', 'approvedBy', 'leaveOut']),
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
                ->where(function ($paidQuery) {
                    $paidQuery->where('is_with_pay', true)
                        ->orWhereNull('is_with_pay');
                })
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

        $query = EmployeeLeave::with(['leaveType', 'approvedBy', 'leaveOut'])
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

        $query = EmployeeLeave::with(['employee', 'leaveType', 'leaveOut'])
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
                ->where(function ($paidQuery) {
                    $paidQuery->where('is_with_pay', true)
                        ->orWhereNull('is_with_pay');
                })
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
