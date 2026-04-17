<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PositionRate;
use App\Models\Employee;
use App\Models\AuditLog;
use App\Models\SalaryAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PositionRateController extends Controller
{
    private const RATE_REQUEST_META_PREFIX = '[RATE_REQUEST_META]';

    /**
     * Get all position rates
     */
    public function index(Request $request): JsonResponse
    {
        $query = PositionRate::query();

        // Filter by active status
        if ($request->has('active_only') && $request->active_only) {
            $query->active();
        }

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Search by position name
        if ($request->has('search')) {
            $query->where('position_name', 'ILIKE', '%' . $request->search . '%');
        }

        $positionRates = $query->orderBy('position_name')->get();

        $pendingByPositionRate = [];
        $pendingRequests = SalaryAdjustment::query()
            ->select(['id', 'reference_period', 'created_at'])
            ->where('status', 'pending')
            ->where(function ($query) {
                $query
                    ->where('reference_period', 'like', 'APPROVAL:POSITION_RATE_UPDATE:%')
                    ->orWhere('reference_period', 'like', 'APPROVAL:POSITION_RATE_BULK_UPDATE:%');
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($pendingRequests as $pendingRequest) {
            $parsed = $this->parsePositionPendingReference((string) $pendingRequest->reference_period);
            if (!$parsed) {
                continue;
            }

            $positionRateId = $parsed['position_rate_id'];

            // Keep the latest pending request per position rate.
            if (isset($pendingByPositionRate[$positionRateId])) {
                continue;
            }

            $pendingByPositionRate[$positionRateId] = [
                'id' => (int) $pendingRequest->id,
                'type' => $parsed['type'],
                'label' => $this->resolvePositionPendingRequestLabel($parsed['type']),
                'requested_at' => $pendingRequest->created_at?->toDateTimeString(),
            ];
        }

        $positionRates->each(function ($positionRate) use ($pendingByPositionRate) {
            $count = $positionRate->getEmployeeCount();
            $positionRate->setAttribute('employee_count', $count);
            $positionRate->setAttribute('employees_count', $count);

            $pending = $pendingByPositionRate[(int) $positionRate->id] ?? null;
            $positionRate->setAttribute('has_pending_rate_update_request', $pending !== null);
            $positionRate->setAttribute('pending_rate_update_request_id', $pending['id'] ?? null);
            $positionRate->setAttribute('pending_rate_update_request_type', $pending['type'] ?? null);
            $positionRate->setAttribute('pending_rate_update_request_label', $pending['label'] ?? null);
            $positionRate->setAttribute('pending_rate_update_requested_at', $pending['requested_at'] ?? null);
        });

        return response()->json($positionRates);
    }

    /**
     * Create new position rate
     */
    public function store(Request $request): JsonResponse
    {
        if ($accessError = $this->ensureCanManagePositionRates()) {
            return $accessError;
        }

        $validated = $request->validate([
            'position_name' => 'required|string|max:100|unique:position_rates,position_name',
            'daily_rate' => 'required|numeric|min:0',
            'category' => 'nullable|string|in:skilled,semi-skilled,technical,support',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['created_by'] = auth()->id();

        $positionRate = PositionRate::create($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'position_rates',
            'action' => 'create_position_rate',
            'description' => "Position rate '{$positionRate->position_name}' created",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'new_values' => $positionRate->toArray(),
        ]);

        return response()->json([
            'message' => 'Position rate created successfully',
            'data' => $positionRate,
        ], 201);
    }

    /**
     * Get single position rate
     */
    public function show(PositionRate $positionRate): JsonResponse
    {
        $count = $positionRate->getEmployeeCount();
        $positionRate->employee_count = $count;
        $positionRate->employees_count = $count;
        return response()->json($positionRate);
    }

    /**
     * Update position rate
     */
    public function update(Request $request, PositionRate $positionRate): JsonResponse
    {
        if ($accessError = $this->ensureCanManagePositionRates()) {
            return $accessError;
        }

        $validated = $request->validate([
            'position_name' => 'required|string|max:100|unique:position_rates,position_name,' . $positionRate->id,
            'daily_rate' => 'required|numeric|min:0',
            'category' => 'nullable|string|in:skilled,semi-skilled,technical,support',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $oldValues = $positionRate->toArray();
        $oldDailyRate = (float) $positionRate->daily_rate;
        $newDailyRate = (float) $validated['daily_rate'];
        $hasAnyChange =
            ((string) $positionRate->position_name !== (string) $validated['position_name'])
            || (abs($oldDailyRate - $newDailyRate) > 0.0001)
            || ((string) ($positionRate->category ?? '') !== (string) ($validated['category'] ?? ''))
            || ((string) ($positionRate->description ?? '') !== (string) ($validated['description'] ?? ''))
            || ((bool) $positionRate->is_active !== (bool) ($validated['is_active'] ?? $positionRate->is_active));

        if (!$hasAnyChange) {
            return response()->json([
                'message' => 'No change detected. Position rate details are already up to date.',
            ], 422);
        }

        if ($this->requiresAdminApprovalForRateChange()) {
            $referencePeriod = 'APPROVAL:POSITION_RATE_UPDATE:' . $positionRate->id;
            $existingPending = $this->findPendingRateApprovalRequest($referencePeriod);
            if ($existingPending) {
                return response()->json([
                    'message' => 'A pending admin approval request already exists for this position rate update.',
                    'request_id' => $existingPending->id,
                ], 422);
            }

            $requestType = ($newDailyRate - $oldDailyRate) < 0 ? 'deduction' : 'addition';
            $anchorEmployee = $this->resolveRequestAnchorEmployee($positionRate->id);
            if (!$anchorEmployee) {
                return response()->json([
                    'message' => 'Unable to submit approval request because there is no employee anchor record available for this position.',
                ], 422);
            }

            $notes = $this->buildRateApprovalNotes([
                'request_type' => 'position_rate_update',
                'position_rate_id' => $positionRate->id,
                'position_name' => $positionRate->position_name,
                'old_daily_rate' => $oldDailyRate,
                'new_daily_rate' => $newDailyRate,
                'position_payload' => $validated,
                'apply_employee_sync' => false,
                'requested_by' => auth()->id(),
                'requested_by_role' => strtolower((string) (auth()->user()?->role ?? '')),
                'requested_reason' => $request->input('reason'),
                'requested_at' => now()->toDateTimeString(),
            ], $request->input('reason'));

            $approvalRequest = SalaryAdjustment::create([
                'employee_id' => $anchorEmployee->id,
                'amount' => $this->normalizeApprovalRequestAmount($newDailyRate - $oldDailyRate),
                'type' => $requestType,
                'reason' => 'Position Rate Update Request',
                'reference_period' => $referencePeriod,
                'effective_date' => now()->toDateString(),
                'status' => 'pending',
                'created_by' => auth()->id(),
                'notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'salary_adjustments',
                'action' => 'create_adjustment',
                'description' => "Position rate update request submitted for '{$positionRate->position_name}'",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'new_values' => array_merge($approvalRequest->toArray(), [
                    'approval_required' => true,
                    'request_scope' => 'position_rate_update',
                ]),
            ]);

            return response()->json([
                'message' => 'Position rate update submitted for admin approval. It is now visible in Salary Exception Records.',
                'approval_required' => true,
                'request' => $approvalRequest->load(['employee', 'createdBy']),
                'data' => $positionRate->fresh(),
            ], 202);
        }

        $validated['updated_by'] = auth()->id();
        $positionRate->update($validated);
        $freshPositionRate = $positionRate->fresh();
        $newDailyRate = (float) $freshPositionRate->daily_rate;
        $affectedEmployees = Employee::where('position_id', $positionRate->id)->count();

        $description = "Position rate '{$freshPositionRate->position_name}' updated";
        if (abs($oldDailyRate - $newDailyRate) > 0.0001) {
            $description .= ' from ₱' . number_format($oldDailyRate, 2) .
                ' to ₱' . number_format($newDailyRate, 2);
        }
        if ($affectedEmployees > 0) {
            $description .= " affecting {$affectedEmployees} employee(s)";
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'position_rates',
            'action' => 'update_position_rate',
            'model_type' => PositionRate::class,
            'model_id' => $positionRate->id,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => array_merge($oldValues, [
                'affected_employee_count' => $affectedEmployees,
            ]),
            'new_values' => array_merge($freshPositionRate->toArray(), [
                'affected_employee_count' => $affectedEmployees,
            ]),
        ]);

        return response()->json([
            'message' => 'Position rate updated successfully',
            'data' => $freshPositionRate,
        ]);
    }

    /**
     * Delete position rate
     */
    public function destroy(PositionRate $positionRate): JsonResponse
    {
        if ($accessError = $this->ensureCanManagePositionRates()) {
            return $accessError;
        }

        // Check if any employees use this position
        $employeeCount = $positionRate->getEmployeeCount();

        if ($employeeCount > 0) {
            return response()->json([
                'error' => "Cannot delete position rate. {$employeeCount} employee(s) are assigned to this position."
            ], 422);
        }

        $positionRateData = $positionRate->toArray();
        $positionRate->delete();

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'position_rates',
            'action' => 'delete_position_rate',
            'description' => "Position rate '{$positionRateData['position_name']}' deleted",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $positionRateData,
        ]);

        return response()->json([
            'message' => 'Position rate deleted successfully',
        ]);
    }

    /**
     * Bulk update employees with the same position
     */
    public function bulkUpdateEmployees(Request $request, PositionRate $positionRate): JsonResponse
    {
        if ($accessError = $this->ensureCanManagePositionRates()) {
            return $accessError;
        }

        $validated = $request->validate([
            'new_rate' => 'required|numeric|min:0',
        ]);

        $oldRate = (float) $positionRate->daily_rate;
        $newRate = (float) $validated['new_rate'];

        if ($this->requiresAdminApprovalForRateChange()) {
            $referencePeriod = 'APPROVAL:POSITION_RATE_BULK_UPDATE:' . $positionRate->id;
            $existingPending = $this->findPendingRateApprovalRequest($referencePeriod);
            if ($existingPending) {
                return response()->json([
                    'message' => 'A pending admin approval request already exists for this bulk position rate update.',
                    'request_id' => $existingPending->id,
                ], 422);
            }

            $requestType = ($newRate - $oldRate) < 0 ? 'deduction' : 'addition';
            $anchorEmployee = $this->resolveRequestAnchorEmployee($positionRate->id);
            if (!$anchorEmployee) {
                return response()->json([
                    'message' => 'Unable to submit approval request because there is no employee anchor record available for this position.',
                ], 422);
            }

            $notes = $this->buildRateApprovalNotes([
                'request_type' => 'position_rate_bulk_update',
                'position_rate_id' => $positionRate->id,
                'position_name' => $positionRate->position_name,
                'old_daily_rate' => $oldRate,
                'new_daily_rate' => $newRate,
                'position_payload' => ['daily_rate' => $newRate],
                'apply_employee_sync' => true,
                'requested_by' => auth()->id(),
                'requested_by_role' => strtolower((string) (auth()->user()?->role ?? '')),
                'requested_reason' => $request->input('reason'),
                'requested_at' => now()->toDateTimeString(),
            ], $request->input('reason'));

            $approvalRequest = SalaryAdjustment::create([
                'employee_id' => $anchorEmployee->id,
                'amount' => $this->normalizeApprovalRequestAmount($newRate - $oldRate),
                'type' => $requestType,
                'reason' => 'Position Rate Bulk Update Request',
                'reference_period' => $referencePeriod,
                'effective_date' => now()->toDateString(),
                'status' => 'pending',
                'created_by' => auth()->id(),
                'notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'salary_adjustments',
                'action' => 'create_adjustment',
                'description' => "Position rate bulk update request submitted for '{$positionRate->position_name}'",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'new_values' => array_merge($approvalRequest->toArray(), [
                    'approval_required' => true,
                    'request_scope' => 'position_rate_bulk_update',
                ]),
            ]);

            return response()->json([
                'message' => 'Position rate bulk update submitted for admin approval. It is now visible in Salary Exception Records.',
                'approval_required' => true,
                'request' => $approvalRequest->load(['employee', 'createdBy']),
                'position_rate' => $positionRate->fresh(),
            ], 202);
        }

        DB::beginTransaction();
        try {
            // Update the position rate
            $positionRate->update([
                'daily_rate' => $validated['new_rate'],
                'updated_by' => auth()->id(),
            ]);

            // Update all employees with this position
            $updatedCount = Employee::where('position_id', $positionRate->id)
                ->update([
                    'basic_salary' => $validated['new_rate'],
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);

            $freshPositionRate = $positionRate->fresh();
            $newRate = (float) $freshPositionRate->daily_rate;

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'position_rates',
                'action' => 'update_position_rate',
                'model_type' => PositionRate::class,
                'model_id' => $positionRate->id,
                'description' => "Position rate '{$freshPositionRate->position_name}' bulk-synced from ₱" .
                    number_format($oldRate, 2) . ' to ₱' . number_format($newRate, 2) .
                    " for {$updatedCount} employee(s)",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_values' => [
                    'position_rate_id' => $positionRate->id,
                    'daily_rate' => $oldRate,
                ],
                'new_values' => [
                    'position_rate_id' => $positionRate->id,
                    'daily_rate' => $newRate,
                    'synced_employee_count' => $updatedCount,
                ],
            ]);

            DB::commit();

            return response()->json([
                'message' => "Updated {$updatedCount} employee(s) successfully",
                'updated_count' => $updatedCount,
                'position_rate' => $freshPositionRate,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update employees: ' . $e->getMessage()], 500);
        }
    }

    private function requiresAdminApprovalForRateChange(): bool
    {
        $role = strtolower((string) (auth()->user()?->role ?? ''));
        return in_array($role, ['hr', 'payrollist'], true);
    }

    private function ensureCanManagePositionRates(): ?JsonResponse
    {
        $role = strtolower((string) (auth()->user()?->role ?? ''));
        if (!in_array($role, ['admin', 'hr', 'payrollist'], true)) {
            return response()->json([
                'message' => 'You are not authorized to modify position rates.',
            ], 403);
        }

        return null;
    }

    private function findPendingRateApprovalRequest(string $referencePeriod): ?SalaryAdjustment
    {
        return SalaryAdjustment::query()
            ->where('reference_period', $referencePeriod)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    private function normalizeApprovalRequestAmount(float $delta): float
    {
        $normalized = round(abs($delta), 2);
        return $normalized >= 0 ? $normalized : 0.0;
    }

    private function resolveRequestAnchorEmployee(int $positionRateId): ?Employee
    {
        $positionEmployee = Employee::query()
            ->where('position_id', $positionRateId)
            ->orderBy('id')
            ->first();

        if ($positionEmployee) {
            return $positionEmployee;
        }

        $requesterEmployeeId = auth()->user()?->employee_id;
        if ($requesterEmployeeId) {
            return Employee::find($requesterEmployeeId);
        }

        return null;
    }

    private function buildRateApprovalNotes(array $meta, ?string $reason = null): string
    {
        $lines = [
            self::RATE_REQUEST_META_PREFIX . json_encode($meta, JSON_UNESCAPED_SLASHES),
            'Requested by user #' . (string) ($meta['requested_by'] ?? auth()->id()),
            'Request type: ' . (string) ($meta['request_type'] ?? 'position_rate_update'),
            'Position: ' . (string) ($meta['position_name'] ?? 'N/A'),
            'Daily rate change: ₱' . number_format((float) ($meta['old_daily_rate'] ?? 0), 2) .
                ' -> ₱' . number_format((float) ($meta['new_daily_rate'] ?? 0), 2),
        ];

        if (!empty($reason)) {
            $lines[] = 'Reason: ' . $reason;
        }

        return implode(PHP_EOL, $lines);
    }

    private function parsePositionPendingReference(string $referencePeriod): ?array
    {
        if (preg_match('/^APPROVAL:POSITION_RATE_UPDATE:(\d+)$/', $referencePeriod, $matches)) {
            return [
                'position_rate_id' => (int) $matches[1],
                'type' => 'position_rate_update',
            ];
        }

        if (preg_match('/^APPROVAL:POSITION_RATE_BULK_UPDATE:(\d+)$/', $referencePeriod, $matches)) {
            return [
                'position_rate_id' => (int) $matches[1],
                'type' => 'position_rate_bulk_update',
            ];
        }

        return null;
    }

    private function resolvePositionPendingRequestLabel(string $type): string
    {
        return match ($type) {
            'position_rate_bulk_update' => 'Pending bulk rate update request',
            default => 'Pending position rate update request',
        };
    }

    /**
     * Get position rate by name
     */
    public function getByName(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'position_name' => 'required|string',
        ]);

        $positionRate = PositionRate::where('position_name', $validated['position_name'])
            ->active()
            ->first();

        if (!$positionRate) {
            return response()->json([
                'error' => 'Position rate not found',
                'default_rate' => 450, // Fallback rate
            ], 404);
        }

        return response()->json($positionRate);
    }

    /**
     * Get all active position names (for dropdowns)
     */
    public function getPositionNames(): JsonResponse
    {
        $positions = PositionRate::active()
            ->orderBy('position_name')
            ->pluck('position_name');

        return response()->json($positions);
    }
}
