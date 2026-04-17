<?php

namespace App\Http\Controllers;

use App\Models\SalaryAdjustment;
use App\Models\Employee;
use App\Models\AuditLog;
use App\Models\PositionRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SalaryAdjustmentController extends Controller
{
    private const RATE_REQUEST_META_PREFIX = '[RATE_REQUEST_META]';

    /**
     * Display a listing of all salary exception records.
     */
    public function index(Request $request)
    {
        $query = SalaryAdjustment::with(['employee', 'createdBy', 'appliedPayroll']);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        $adjustments = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        return response()->json($adjustments);
    }

    /**
     * Get all employees for the adjustment form.
     */
    public function getEmployees()
    {
        $employees = Employee::whereIn('activity_status', ['active', 'on_leave'])
            ->with(['project', 'positionRate'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                    'department' => $employee->project?->name ?? $employee->department ?? 'N/A',
                    'position' => $employee->positionRate?->position_name ?? $employee->position ?? 'N/A',
                    'basic_salary' => $employee->getBasicSalary(),
                    'pending_adjustments' => $employee->salaryAdjustments()
                        ->where('status', 'pending')
                        ->sum('amount'),
                ];
            });

        return response()->json($employees);
    }

    /**
     * Store a new salary adjustment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:deduction,addition',
            'reason' => 'required|string|max:255',
            'reference_period' => 'required|string|max:255',
            'effective_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $validated['reason'] = trim((string) $validated['reason']);
        $validated['reference_period'] = trim((string) $validated['reference_period']);

        if ($validated['reason'] === '' || $validated['reference_period'] === '') {
            return response()->json([
                'message' => 'Reason and reference period are required for one-time salary exception records.',
            ], 422);
        }

        if ($this->hasDuplicateAdjustment($validated)) {
            return response()->json([
                'message' => 'A similar pending/approved salary exception record already exists for this employee and period.',
                'code' => 'duplicate_adjustment',
            ], 422);
        }

        $employee = Employee::with('positionRate')->findOrFail((int) $validated['employee_id']);
        $overlapWarnings = $this->buildOverlapWarnings($employee, $validated['effective_date'] ?? null);
        $autoApproved = $this->shouldAutoApproveOnCreate();

        $notes = trim((string) ($validated['notes'] ?? ''));
        if (!empty($overlapWarnings)) {
            $warningNote = 'Overlap warning: ' . implode(' | ', $overlapWarnings);
            $notes = $notes !== '' ? ($notes . PHP_EOL . $warningNote) : $warningNote;
        }

        if ($autoApproved) {
            $autoApprovalNote = 'Auto-approved on create by admin user #' . (string) Auth::id() .
                ' on ' . now()->toDateTimeString();
            $notes = $notes !== '' ? ($notes . PHP_EOL . $autoApprovalNote) : $autoApprovalNote;
        }

        $adjustment = SalaryAdjustment::create([
            'employee_id' => $validated['employee_id'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'reference_period' => $validated['reference_period'],
            'effective_date' => $validated['effective_date'] ?? null,
            'status' => $autoApproved ? 'applied' : 'pending',
            'created_by' => Auth::id(),
            'notes' => $notes !== '' ? $notes : null,
        ]);

        Log::info('Salary adjustment created', [
            'adjustment_id' => $adjustment->id,
            'employee_id' => $adjustment->employee_id,
            'amount' => $adjustment->amount,
            'type' => $adjustment->type,
            'created_by' => Auth::id(),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'module' => 'salary_adjustments',
            'action' => 'create_adjustment',
            'description' => "Salary exception record created for employee #{$adjustment->employee_id}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'new_values' => array_merge($adjustment->toArray(), [
                'approval_required' => !$autoApproved,
                'auto_approved' => $autoApproved,
                'overlap_warnings' => $overlapWarnings,
            ]),
        ]);

        return response()->json([
            'message' => $autoApproved
                ? 'Salary exception record created and auto-approved successfully'
                : 'Salary exception record submitted for approval successfully',
            'adjustment' => $adjustment->load(['employee', 'createdBy']),
            'approval_required' => !$autoApproved,
            'auto_approved' => $autoApproved,
            'has_overlap_warning' => !empty($overlapWarnings),
            'overlap_warnings' => $overlapWarnings,
        ], 201);
    }

    /**
     * Display the specified salary adjustment.
     */
    public function show(SalaryAdjustment $salaryAdjustment)
    {
        return response()->json(
            $salaryAdjustment->load(['employee', 'createdBy', 'appliedPayroll'])
        );
    }

    /**
     * Update the specified salary adjustment.
     */
    public function update(Request $request, SalaryAdjustment $salaryAdjustment)
    {
        // Only allow updates if still pending approval
        if ($salaryAdjustment->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending salary exception records can be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'type' => 'sometimes|in:deduction,addition',
            'reason' => 'sometimes|nullable|string|max:255',
            'reference_period' => 'sometimes|nullable|string|max:255',
            'effective_date' => 'nullable|date',
            'status' => 'sometimes|in:pending,cancelled',
            'notes' => 'nullable|string',
        ]);

        $updateData = [];
        if (isset($validated['amount'])) {
            $updateData['amount'] = $validated['amount'];
        }
        if (isset($validated['type'])) {
            $updateData['type'] = $validated['type'];
        }
        if (array_key_exists('reason', $validated)) {
            $updateData['reason'] = $validated['reason'];
        }
        if (array_key_exists('reference_period', $validated)) {
            $updateData['reference_period'] = $validated['reference_period'];
        }
        if (array_key_exists('effective_date', $validated)) {
            $updateData['effective_date'] = $validated['effective_date'];
        }
        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }
        if (array_key_exists('notes', $validated)) {
            $updateData['notes'] = $validated['notes'];
        }

        $checkData = array_merge([
            'employee_id' => $salaryAdjustment->employee_id,
            'amount' => $salaryAdjustment->amount,
            'type' => $salaryAdjustment->type,
            'reference_period' => $salaryAdjustment->reference_period,
            'reason' => $salaryAdjustment->reason,
            'effective_date' => $salaryAdjustment->effective_date,
        ], $updateData);

        if (!$this->isRateRequestRecord($salaryAdjustment->notes)) {
            $normalizedReason = trim((string) ($checkData['reason'] ?? ''));
            $normalizedReference = trim((string) ($checkData['reference_period'] ?? ''));

            if ($normalizedReason === '' || $normalizedReference === '') {
                return response()->json([
                    'message' => 'Reason and reference period are required for one-time salary exception records.',
                ], 422);
            }

            $updateData['reason'] = $normalizedReason;
            $updateData['reference_period'] = $normalizedReference;
        }

        $targetStatus = $checkData['status'] ?? $salaryAdjustment->status;
        if (
            in_array($targetStatus, ['pending', 'applied'], true)
            && $this->hasDuplicateAdjustment($checkData, $salaryAdjustment->id)
        ) {
            return response()->json([
                'message' => 'A similar pending/approved salary exception record already exists for this employee and period.',
                'code' => 'duplicate_adjustment',
            ], 422);
        }

        $oldValues = $salaryAdjustment->toArray();
        $salaryAdjustment->update($updateData);

        AuditLog::create([
            'user_id' => Auth::id(),
            'module' => 'salary_adjustments',
            'action' => 'update_adjustment',
            'description' => "Salary adjustment #{$salaryAdjustment->id} updated",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $salaryAdjustment->fresh()->toArray(),
        ]);

        Log::info('Salary adjustment updated', [
            'adjustment_id' => $salaryAdjustment->id,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Salary exception record updated successfully',
            'adjustment' => $salaryAdjustment->load(['employee', 'createdBy']),
        ]);
    }

    /**
     * Remove the specified salary adjustment.
     */
    public function destroy(SalaryAdjustment $salaryAdjustment)
    {
        // Only allow deletion if still pending
        if ($salaryAdjustment->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending salary exception records can be deleted.',
            ], 422);
        }

        $adjustmentData = $salaryAdjustment->toArray();
        $salaryAdjustment->delete();

        AuditLog::create([
            'user_id' => Auth::id(),
            'module' => 'salary_adjustments',
            'action' => 'delete_adjustment',
            'description' => "Salary adjustment #{$adjustmentData['id']} deleted",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $adjustmentData,
        ]);

        Log::info('Salary adjustment deleted', [
            'adjustment_id' => $adjustmentData['id'],
            'deleted_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Salary exception record deleted successfully',
        ]);
    }

    /**
     * Get pending adjustments for a specific employee.
     */
    public function getEmployeeAdjustments(Employee $employee)
    {
        $adjustments = SalaryAdjustment::where('employee_id', $employee->id)
            ->with('createdBy')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($adjustments);
    }

    /**
     * Bulk create adjustments for multiple employees.
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'adjustments' => 'required|array|min:1',
            'adjustments.*.employee_id' => 'required|exists:employees,id',
            'adjustments.*.amount' => 'required|numeric|min:0.01',
            'adjustments.*.type' => 'required|in:deduction,addition',
            'adjustments.*.reason' => 'required|string|max:255',
            'adjustments.*.reference_period' => 'required|string|max:255',
            'adjustments.*.effective_date' => 'nullable|date',
            'adjustments.*.notes' => 'nullable|string',
        ]);

        $created = [];
        $duplicates = [];
        $warningCount = 0;
        $autoApproved = $this->shouldAutoApproveOnCreate();
        foreach ($validated['adjustments'] as $adjustmentData) {
            $adjustmentData['reason'] = trim((string) ($adjustmentData['reason'] ?? ''));
            $adjustmentData['reference_period'] = trim((string) ($adjustmentData['reference_period'] ?? ''));

            if ($adjustmentData['reason'] === '' || $adjustmentData['reference_period'] === '') {
                return response()->json([
                    'message' => 'Reason and reference period are required for one-time salary exception records.',
                ], 422);
            }

            if ($this->hasDuplicateAdjustment($adjustmentData)) {
                $duplicates[] = [
                    'employee_id' => $adjustmentData['employee_id'],
                    'amount' => $adjustmentData['amount'],
                    'type' => $adjustmentData['type'],
                    'reference_period' => $adjustmentData['reference_period'] ?? null,
                ];
                continue;
            }

            $employee = Employee::with('positionRate')->find($adjustmentData['employee_id']);
            $overlapWarnings = $employee
                ? $this->buildOverlapWarnings($employee, $adjustmentData['effective_date'] ?? null)
                : [];

            if (!empty($overlapWarnings)) {
                $warningCount++;
                $warningNote = 'Overlap warning: ' . implode(' | ', $overlapWarnings);
                $notes = trim((string) ($adjustmentData['notes'] ?? ''));
                $adjustmentData['notes'] = $notes !== '' ? ($notes . PHP_EOL . $warningNote) : $warningNote;
            }

            if ($autoApproved) {
                $notes = trim((string) ($adjustmentData['notes'] ?? ''));
                $autoApprovalNote = 'Auto-approved on create by admin user #' . (string) Auth::id() .
                    ' on ' . now()->toDateTimeString();
                $adjustmentData['notes'] = $notes !== ''
                    ? ($notes . PHP_EOL . $autoApprovalNote)
                    : $autoApprovalNote;
            }

            $adjustmentData['created_by'] = Auth::id();
            $adjustmentData['status'] = $autoApproved ? 'applied' : 'pending';
            $created[] = SalaryAdjustment::create($adjustmentData);
        }

        Log::info('Bulk salary exception records created', [
            'count' => count($created),
            'created_by' => Auth::id(),
            'duplicates_skipped' => count($duplicates),
        ]);

        return response()->json([
            'message' => $autoApproved
                ? count($created) . ' salary exception records created and auto-approved successfully'
                : count($created) . ' salary exception records submitted for approval successfully',
            'adjustments' => $created,
            'duplicates_skipped' => $duplicates,
            'warning_count' => $warningCount,
            'approval_required' => !$autoApproved,
            'auto_approved' => $autoApproved,
        ], 201);
    }

    private function shouldAutoApproveOnCreate(): bool
    {
        return strtolower((string) (Auth::user()?->role ?? '')) === 'admin';
    }

    public function overlapCheck(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'effective_date' => 'nullable|date',
        ]);

        $employee = Employee::with('positionRate')->findOrFail((int) $validated['employee_id']);
        $warnings = $this->buildOverlapWarnings($employee, $validated['effective_date'] ?? null);

        return response()->json([
            'has_overlap_warning' => !empty($warnings),
            'warnings' => $warnings,
        ]);
    }

    public function approve(Request $request, SalaryAdjustment $salaryAdjustment)
    {
        $this->ensureCanApproveOrReject($salaryAdjustment);

        if ($salaryAdjustment->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending salary exception records can be approved.',
            ], 422);
        }

        $requestMeta = $this->extractRateRequestMeta($salaryAdjustment->notes);

        if (!is_array($requestMeta)) {
            $reason = trim((string) ($salaryAdjustment->reason ?? ''));
            $referencePeriod = trim((string) ($salaryAdjustment->reference_period ?? ''));

            if ($reason === '' || $referencePeriod === '') {
                return response()->json([
                    'message' => 'Cannot approve this record without a reason and reference period.',
                ], 422);
            }
        }

        $oldValues = $salaryAdjustment->toArray();

        try {
            DB::transaction(function () use ($request, $salaryAdjustment, $oldValues, $requestMeta) {
                if (is_array($requestMeta) && !empty($requestMeta['request_type'])) {
                    $this->applyRateChangeRequestFromMeta($salaryAdjustment, $requestMeta, $request);
                }

                $approvalNote = 'Approved by user #' . Auth::id() . ' on ' . now()->toDateTimeString();
                if (is_array($requestMeta) && !empty($requestMeta['request_type'])) {
                    $approvalNote .= ' | Applied request type: ' . $requestMeta['request_type'];
                }

                $notes = trim((string) ($salaryAdjustment->notes ?? ''));
                $salaryAdjustment->update([
                    'status' => 'applied',
                    'notes' => $notes !== '' ? ($notes . PHP_EOL . $approvalNote) : $approvalNote,
                ]);

                AuditLog::create([
                    'user_id' => Auth::id(),
                    'module' => 'salary_adjustments',
                    'action' => 'approve_adjustment',
                    'description' => "Salary exception record #{$salaryAdjustment->id} approved",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'old_values' => $oldValues,
                    'new_values' => array_merge($salaryAdjustment->fresh()->toArray(), [
                        'approved_request_type' => $requestMeta['request_type'] ?? null,
                    ]),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to approve salary exception record request', [
                'adjustment_id' => $salaryAdjustment->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to apply approved request: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Salary exception record approved successfully',
            'adjustment' => $salaryAdjustment->fresh()->load(['employee', 'createdBy']),
        ]);
    }

    public function reject(Request $request, SalaryAdjustment $salaryAdjustment)
    {
        $this->ensureCanApproveOrReject($salaryAdjustment);

        if ($salaryAdjustment->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending salary exception records can be rejected.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $oldValues = $salaryAdjustment->toArray();
        $rejectionNote = 'Rejected by user #' . Auth::id() . ' on ' . now()->toDateTimeString() .
            ' | Reason: ' . $validated['reason'];
        $notes = trim((string) ($salaryAdjustment->notes ?? ''));
        $salaryAdjustment->update([
            'status' => 'cancelled',
            'notes' => $notes !== '' ? ($notes . PHP_EOL . $rejectionNote) : $rejectionNote,
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'module' => 'salary_adjustments',
            'action' => 'reject_adjustment',
            'description' => "Salary exception record #{$salaryAdjustment->id} rejected",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $salaryAdjustment->fresh()->toArray(),
        ]);

        return response()->json([
            'message' => 'Salary exception record rejected successfully',
            'adjustment' => $salaryAdjustment->fresh()->load(['employee', 'createdBy']),
        ]);
    }

    private function ensureCanApproveOrReject(SalaryAdjustment $salaryAdjustment): void
    {
        $role = strtolower((string) (Auth::user()?->role ?? ''));
        if ($role === 'admin') {
            return;
        }

        $requestMeta = $this->extractRateRequestMeta($salaryAdjustment->notes);
        if (is_array($requestMeta) && !empty($requestMeta['request_type'])) {
            abort(403, 'Only admin can approve/reject pay-rate or position-rate update requests.');
        }

        if ($role !== 'hr') {
            abort(403, 'Only admin or HR can approve/reject salary exception records.');
        }
    }

    private function extractRateRequestMeta(?string $notes): ?array
    {
        if (!is_string($notes) || trim($notes) === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $notes) ?: [];
        foreach ($lines as $line) {
            if (!str_starts_with($line, self::RATE_REQUEST_META_PREFIX)) {
                continue;
            }

            $json = trim(substr($line, strlen(self::RATE_REQUEST_META_PREFIX)));
            if ($json === '') {
                return null;
            }

            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function isRateRequestRecord(?string $notes): bool
    {
        return is_array($this->extractRateRequestMeta($notes));
    }

    private function applyRateChangeRequestFromMeta(SalaryAdjustment $salaryAdjustment, array $meta, Request $request): void
    {
        $requestType = strtolower((string) ($meta['request_type'] ?? ''));

        if ($requestType === 'employee_custom_pay_rate_update') {
            $this->applyEmployeeCustomRateUpdateRequest($salaryAdjustment, $meta, $request);
            return;
        }

        if ($requestType === 'employee_custom_pay_rate_clear') {
            $this->applyEmployeeCustomRateClearRequest($salaryAdjustment, $meta, $request);
            return;
        }

        if (in_array($requestType, ['position_rate_update', 'position_rate_bulk_update'], true)) {
            $this->applyPositionRateUpdateRequest($salaryAdjustment, $meta, $request);
        }
    }

    private function applyEmployeeCustomRateUpdateRequest(SalaryAdjustment $salaryAdjustment, array $meta, Request $request): void
    {
        $employeeId = (int) ($meta['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            throw new \RuntimeException('Missing employee context for custom pay-rate update request.');
        }

        $employee = Employee::with('positionRate')->find($employeeId);
        if (!$employee) {
            throw new \RuntimeException('Employee not found for custom pay-rate update request.');
        }

        if (!array_key_exists('new_custom_pay_rate', $meta) || $meta['new_custom_pay_rate'] === null) {
            throw new \RuntimeException('Missing target custom pay rate in approval request metadata.');
        }

        $newCustomPayRate = (float) $meta['new_custom_pay_rate'];
        $oldCustomPayRate = $employee->custom_pay_rate !== null ? (float) $employee->custom_pay_rate : null;
        $oldEffectiveRate = (float) $employee->getBasicSalary();

        $employee->custom_pay_rate = $newCustomPayRate;
        $employee->save();

        $newEffectiveRate = (float) $employee->fresh()->getBasicSalary();

        AuditLog::create([
            'user_id' => Auth::id(),
            'module' => 'employees',
            'action' => 'pay_rate_update',
            'model_type' => Employee::class,
            'model_id' => $employee->id,
            'description' => "Custom pay rate updated via approved salary exception request #{$salaryAdjustment->id} for employee {$employee->employee_number}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => [
                'employee_id' => $employee->id,
                'custom_pay_rate' => $oldCustomPayRate,
                'effective_pay_rate' => $oldEffectiveRate,
            ],
            'new_values' => [
                'employee_id' => $employee->id,
                'custom_pay_rate' => $newCustomPayRate,
                'effective_pay_rate' => $newEffectiveRate,
                'reason' => $meta['requested_reason'] ?? null,
                'approved_via_adjustment_id' => $salaryAdjustment->id,
            ],
        ]);
    }

    private function applyEmployeeCustomRateClearRequest(SalaryAdjustment $salaryAdjustment, array $meta, Request $request): void
    {
        $employeeId = (int) ($meta['employee_id'] ?? 0);
        if ($employeeId <= 0) {
            throw new \RuntimeException('Missing employee context for custom pay-rate clear request.');
        }

        $employee = Employee::with('positionRate')->find($employeeId);
        if (!$employee) {
            throw new \RuntimeException('Employee not found for custom pay-rate clear request.');
        }

        $oldCustomPayRate = $employee->custom_pay_rate !== null ? (float) $employee->custom_pay_rate : null;
        $oldEffectiveRate = (float) $employee->getBasicSalary();

        $employee->custom_pay_rate = null;
        $employee->save();

        $newEffectiveRate = (float) $employee->fresh()->getBasicSalary();

        AuditLog::create([
            'user_id' => Auth::id(),
            'module' => 'employees',
            'action' => 'pay_rate_clear',
            'model_type' => Employee::class,
            'model_id' => $employee->id,
            'description' => "Custom pay rate cleared via approved salary exception request #{$salaryAdjustment->id} for employee {$employee->employee_number}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => [
                'employee_id' => $employee->id,
                'custom_pay_rate' => $oldCustomPayRate,
                'effective_pay_rate' => $oldEffectiveRate,
            ],
            'new_values' => [
                'employee_id' => $employee->id,
                'custom_pay_rate' => null,
                'effective_pay_rate' => $newEffectiveRate,
                'reason' => $meta['requested_reason'] ?? null,
                'approved_via_adjustment_id' => $salaryAdjustment->id,
            ],
        ]);
    }

    private function applyPositionRateUpdateRequest(SalaryAdjustment $salaryAdjustment, array $meta, Request $request): void
    {
        $positionRateId = (int) ($meta['position_rate_id'] ?? 0);
        if ($positionRateId <= 0) {
            throw new \RuntimeException('Missing position rate context for update request.');
        }

        $positionRate = PositionRate::find($positionRateId);
        if (!$positionRate) {
            throw new \RuntimeException('Position rate not found for update request.');
        }

        $oldValues = $positionRate->toArray();
        $updatePayload = [];
        $positionPayload = is_array($meta['position_payload'] ?? null)
            ? $meta['position_payload']
            : [];

        foreach (['position_name', 'daily_rate', 'category', 'description', 'is_active'] as $field) {
            if (array_key_exists($field, $positionPayload)) {
                $updatePayload[$field] = $positionPayload[$field];
            }
        }

        if (!array_key_exists('daily_rate', $updatePayload) && array_key_exists('new_daily_rate', $meta)) {
            $updatePayload['daily_rate'] = (float) $meta['new_daily_rate'];
        }

        $updatePayload['updated_by'] = Auth::id();
        $positionRate->update($updatePayload);

        $freshPositionRate = $positionRate->fresh();
        $applyEmployeeSync = (bool) ($meta['apply_employee_sync'] ?? false);
        $affectedEmployees = 0;

        if ($applyEmployeeSync) {
            $affectedEmployees = Employee::where('position_id', $positionRate->id)
                ->update([
                    'basic_salary' => $freshPositionRate->daily_rate,
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]);
        } else {
            $affectedEmployees = Employee::where('position_id', $positionRate->id)->count();
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'module' => 'position_rates',
            'action' => 'update_position_rate',
            'model_type' => PositionRate::class,
            'model_id' => $positionRate->id,
            'description' => "Position rate '{$freshPositionRate->position_name}' updated via approved salary exception request #{$salaryAdjustment->id}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => array_merge($oldValues, [
                'approved_via_adjustment_id' => $salaryAdjustment->id,
                'affected_employee_count' => $affectedEmployees,
            ]),
            'new_values' => array_merge($freshPositionRate->toArray(), [
                'approved_via_adjustment_id' => $salaryAdjustment->id,
                'affected_employee_count' => $affectedEmployees,
                'apply_employee_sync' => $applyEmployeeSync,
            ]),
        ]);
    }

    private function hasDuplicateAdjustment(array $data, ?int $ignoreId = null): bool
    {
        $query = SalaryAdjustment::query()
            ->where('employee_id', (int) $data['employee_id'])
            ->where('type', $data['type'])
            ->whereIn('status', ['pending', 'applied'])
            ->whereBetween('amount', [
                (float) $data['amount'] - 0.009,
                (float) $data['amount'] + 0.009,
            ]);

        $referencePeriod = trim((string) ($data['reference_period'] ?? ''));
        if ($referencePeriod !== '') {
            $query->where('reference_period', $referencePeriod);
        } else {
            $query->whereNull('reference_period');
        }

        if (!empty($data['effective_date'])) {
            $query->whereDate('effective_date', Carbon::parse($data['effective_date'])->toDateString());
        } else {
            $query->whereNull('effective_date');
        }

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function buildOverlapWarnings(Employee $employee, ?string $effectiveDate = null): array
    {
        $reference = $effectiveDate ? Carbon::parse($effectiveDate) : now();
        $windowStart = $reference->copy()->subDays(30)->startOfDay();
        $windowEnd = $reference->copy()->addDays(7)->endOfDay();

        $warnings = [];

        $employeeRateLogs = AuditLog::query()
            ->where('module', 'employees')
            ->whereIn('action', ['pay_rate_update', 'pay_rate_clear'])
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->latest()
            ->take(150)
            ->get();

        $matchingEmployeeRateLog = $employeeRateLogs->first(function ($log) use ($employee) {
            $oldValues = is_array($log->old_values) ? $log->old_values : [];
            $newValues = is_array($log->new_values) ? $log->new_values : [];
            $employeeId = $newValues['employee_id'] ?? $oldValues['employee_id'] ?? null;
            return (int) $employeeId === (int) $employee->id;
        });

        if ($matchingEmployeeRateLog) {
            $warnings[] = 'Employee pay rate was recently changed on ' .
                Carbon::parse($matchingEmployeeRateLog->created_at)->format('M d, Y h:i A') . '.';
        }

        if ($employee->position_id) {
            $positionRateLogs = AuditLog::query()
                ->where('module', 'position_rates')
                ->where('action', 'update_position_rate')
                ->whereBetween('created_at', [$windowStart, $windowEnd])
                ->latest()
                ->take(150)
                ->get();

            $matchingPositionRateLog = $positionRateLogs->first(function ($log) use ($employee) {
                $oldValues = is_array($log->old_values) ? $log->old_values : [];
                $newValues = is_array($log->new_values) ? $log->new_values : [];

                $positionRateId =
                    $newValues['position_rate_id']
                    ?? $oldValues['position_rate_id']
                    ?? $newValues['id']
                    ?? $oldValues['id']
                    ?? null;

                return (int) $positionRateId === (int) $employee->position_id;
            });

            if ($matchingPositionRateLog) {
                $warnings[] = 'Employee position rate was recently updated on ' .
                    Carbon::parse($matchingPositionRateLog->created_at)->format('M d, Y h:i A') . '.';
            }
        }

        return array_values(array_unique($warnings));
    }
}
