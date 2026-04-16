<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAllowance;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AllowanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,hr,payrollist')->only(['store', 'update', 'destroy']);
        $this->middleware('role:admin')->only(['updateApproval', 'updateBulkApproval', 'updateBatchApproval']);
    }

    public function index(Request $request)
    {
        $query = EmployeeAllowance::with([
            'employee',
            'approver:id,name,username',
            'rejector:id,name,username',
        ]);

        $perPage = max(1, min((int) $request->input('per_page', 15), 200));

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('allowance_type')) {
            $query->where('allowance_type', $request->allowance_type);
        }

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('request_batch_id')) {
            $query->where('request_batch_id', $request->string('request_batch_id')->toString());
        }

        return response()->json(
            $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'employee_ids' => 'nullable|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'allowance_type' => 'required|in:water,cola,incentive,ppe,transportation,meal,communication,housing,clothing,medical,education,performance,hazard,other',
            'allowance_name' => 'nullable|string|max:100',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:daily,weekly,semi_monthly,monthly',
            'effective_date' => 'required|date',
            'end_date' => 'nullable|date|after:effective_date',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $employeeIds = collect($validated['employee_ids'] ?? []);

        if (!empty($validated['employee_id'])) {
            $employeeIds->push((int) $validated['employee_id']);
        }

        $employeeIds = $employeeIds->unique()->values();
        $requestBatchId = $employeeIds->count() > 1 ? Str::uuid()->toString() : null;

        if ($employeeIds->isEmpty()) {
            return response()->json([
                'message' => 'Please select at least one employee.',
                'errors' => [
                    'employee_id' => ['Please select at least one employee.'],
                ],
            ], 422);
        }

        // Auto-generate allowance_name from allowance_type if not provided
        if (empty($validated['allowance_name'])) {
            $validated['allowance_name'] = ucwords(str_replace('_', ' ', $validated['allowance_type'])) . ' Allowance';
        }

        // Taxable allowance classification is deprecated in unified allowances.
        $validated['is_taxable'] = false;

        $user = auth()->user();

        $validated['created_by'] = $user->id;

        // Admin-created allowances are auto-approved. HR/Payrollist entries are pending approval.
        if ($user->role === 'admin') {
            $validated['status'] = 'approved';
            $validated['approved_by'] = $user->id;
            $validated['approved_at'] = now();
            $validated['rejected_by'] = null;
            $validated['rejected_at'] = null;
            $validated['rejection_reason'] = null;
        } else {
            $validated['status'] = 'pending';
            $validated['approved_by'] = null;
            $validated['approved_at'] = null;
            $validated['rejected_by'] = null;
            $validated['rejected_at'] = null;
            $validated['rejection_reason'] = null;
        }

        $basePayload = collect($validated)
            ->except(['employee_id', 'employee_ids'])
            ->merge(['request_batch_id' => $requestBatchId])
            ->toArray();

        $createdAllowances = collect();
        $skippedDuplicateCount = 0;

        DB::transaction(function () use (
            $employeeIds,
            $basePayload,
            $user,
            $createdAllowances,
            &$skippedDuplicateCount
        ) {
            foreach ($employeeIds as $employeeId) {
                // Guard against accidental double-submit/network replay creating identical pending records.
                $recentDuplicateExists = EmployeeAllowance::query()
                    ->where('employee_id', $employeeId)
                    ->where('allowance_type', $basePayload['allowance_type'])
                    ->where('allowance_name', $basePayload['allowance_name'])
                    ->where('amount', $basePayload['amount'])
                    ->where('frequency', $basePayload['frequency'])
                    ->whereDate('effective_date', $basePayload['effective_date'])
                    ->where('status', $basePayload['status'] ?? 'pending')
                    ->where('created_by', $user->id)
                    ->where('created_at', '>=', now()->subMinutes(2))
                    ->where(function ($query) use ($basePayload) {
                        if (empty($basePayload['end_date'])) {
                            $query->whereNull('end_date');
                            return;
                        }

                        $query->whereDate('end_date', $basePayload['end_date']);
                    })
                    ->exists();

                if ($recentDuplicateExists) {
                    $skippedDuplicateCount++;
                    continue;
                }

                $allowance = EmployeeAllowance::create([
                    ...$basePayload,
                    'employee_id' => $employeeId,
                ]);

                AuditLog::create([
                    'user_id' => $user->id,
                    'module' => 'allowances',
                    'action' => 'create_allowance',
                    'description' => "Allowance '{$allowance->allowance_name}' created for employee #{$allowance->employee_id} with status {$allowance->status}",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'new_values' => $allowance->toArray(),
                ]);

                $createdAllowances->push($allowance);
            }
        });

        $createdCount = $createdAllowances->count();
        $isApproved = ($basePayload['status'] ?? null) === 'approved';

        if ($createdCount === 0 && $skippedDuplicateCount > 0) {
            return response()->json([
                'message' => 'Duplicate allowance submission detected. No new allowances were created.',
                'created_count' => 0,
                'skipped_duplicate_count' => $skippedDuplicateCount,
            ], 409);
        }

        if ($createdCount === 1) {
            $allowance = $createdAllowances->first()->load(['employee', 'approver', 'rejector']);

            return response()->json([
                'message' => $isApproved
                    ? 'Allowance created and approved successfully'
                    : 'Allowance created and submitted for approval',
                'created_count' => 1,
                'skipped_duplicate_count' => $skippedDuplicateCount,
                'data' => $allowance,
            ], 201);
        }

        $message = $isApproved
            ? "{$createdCount} allowances created and approved successfully"
            : "{$createdCount} allowances created and submitted for approval";

        if ($skippedDuplicateCount > 0) {
            $message .= " ({$skippedDuplicateCount} duplicate entr" . ($skippedDuplicateCount > 1 ? 'ies were' : 'y was') . " skipped)";
        }

        return response()->json([
            'message' => $message,
            'created_count' => $createdCount,
            'skipped_duplicate_count' => $skippedDuplicateCount,
            'request_batch_id' => $requestBatchId,
            'data' => $createdAllowances
                ->map(fn($allowance) => $allowance->load(['employee', 'approver', 'rejector']))
                ->values(),
        ], 201);
    }

    public function show(EmployeeAllowance $allowance)
    {
        return response()->json([
            'data' => $allowance->load(['employee', 'approver', 'rejector']),
        ]);
    }

    public function update(Request $request, EmployeeAllowance $allowance)
    {
        $validated = $request->validate([
            'allowance_type' => 'sometimes|in:water,cola,incentive,ppe,transportation,meal,communication,housing,clothing,medical,education,performance,hazard,other',
            'allowance_name' => 'nullable|string|max:100',
            'amount' => 'sometimes|numeric|min:0',
            'frequency' => 'sometimes|in:daily,weekly,semi_monthly,monthly',
            'effective_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after:effective_date',
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        if (array_key_exists('allowance_name', $validated) && empty($validated['allowance_name'])) {
            $effectiveType = $validated['allowance_type'] ?? $allowance->allowance_type;
            $validated['allowance_name'] = ucwords(str_replace('_', ' ', $effectiveType)) . ' Allowance';
        }

        $user = auth()->user();
        $oldValues = $allowance->toArray();

        $allowance->fill($validated);
        $allowance->is_taxable = false;

        // Non-admin edits must be re-approved before payroll can use the allowance.
        if ($user->role !== 'admin') {
            $allowance->status = 'pending';
            $allowance->approved_by = null;
            $allowance->approved_at = null;
            $allowance->rejected_by = null;
            $allowance->rejected_at = null;
            $allowance->rejection_reason = null;
        }

        $allowance->save();

        AuditLog::create([
            'user_id' => $user->id,
            'module' => 'allowances',
            'action' => 'update_allowance',
            'description' => "Allowance '{$allowance->allowance_name}' updated (status: {$allowance->status})",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $allowance->fresh()->toArray(),
        ]);

        return response()->json([
            'message' => $allowance->status === 'pending'
                ? 'Allowance updated and resubmitted for approval'
                : 'Allowance updated successfully',
            'data' => $allowance->load(['employee', 'approver', 'rejector']),
        ]);
    }

    public function updateApproval(Request $request, EmployeeAllowance $allowance)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        if ($allowance->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending allowances can be approved or rejected.',
            ], 400);
        }

        $oldValues = $allowance->toArray();

        if ($validated['action'] === 'approve') {
            $allowance->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $message = 'Allowance approved successfully';
            $auditAction = 'approve_allowance';
        } else {
            $allowance->update([
                'status' => 'rejected',
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $validated['rejection_reason'] ?? null,
                'is_active' => false,
            ]);

            $message = 'Allowance rejected successfully';
            $auditAction = 'reject_allowance';
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'allowances',
            'action' => $auditAction,
            'description' => "Allowance '{$allowance->allowance_name}' {$validated['action']}d",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $allowance->fresh()->toArray(),
        ]);

        return response()->json([
            'message' => $message,
            'data' => $allowance->load(['employee', 'approver', 'rejector']),
        ]);
    }

    public function updateBulkApproval(Request $request)
    {
        $validated = $request->validate([
            'allowance_ids' => 'required|array|min:1',
            'allowance_ids.*' => 'integer|exists:employee_allowances,id',
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $allowanceIds = collect($validated['allowance_ids'])->unique()->values();

        $allowances = EmployeeAllowance::whereIn('id', $allowanceIds)->get();
        $pendingAllowances = $allowances->where('status', 'pending')->values();

        if ($pendingAllowances->isEmpty()) {
            return response()->json([
                'message' => 'No pending allowances found in the selected records.',
            ], 400);
        }

        $processedCount = 0;

        DB::transaction(function () use ($pendingAllowances, $validated, &$processedCount) {
            foreach ($pendingAllowances as $allowance) {
                $oldValues = $allowance->toArray();

                if ($validated['action'] === 'approve') {
                    $allowance->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                        'rejected_by' => null,
                        'rejected_at' => null,
                        'rejection_reason' => null,
                    ]);

                    $auditAction = 'approve_allowance';
                } else {
                    $allowance->update([
                        'status' => 'rejected',
                        'approved_by' => null,
                        'approved_at' => null,
                        'rejected_by' => auth()->id(),
                        'rejected_at' => now(),
                        'rejection_reason' => $validated['rejection_reason'] ?? null,
                        'is_active' => false,
                    ]);

                    $auditAction = 'reject_allowance';
                }

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'allowances',
                    'action' => $auditAction,
                    'description' => "Allowance '{$allowance->allowance_name}' {$validated['action']}d via bulk action",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'old_values' => $oldValues,
                    'new_values' => $allowance->fresh()->toArray(),
                ]);

                $processedCount++;
            }
        });

        $skippedCount = $allowanceIds->count() - $processedCount;
        $actionLabel = $validated['action'] === 'approve' ? 'approved' : 'rejected';

        return response()->json([
            'message' => $skippedCount > 0
                ? "{$processedCount} allowances {$actionLabel}. {$skippedCount} skipped because they were not pending."
                : "{$processedCount} allowances {$actionLabel} successfully.",
            'processed_count' => $processedCount,
            'skipped_count' => $skippedCount,
        ]);
    }

    public function updateBatchApproval(Request $request)
    {
        $validated = $request->validate([
            'batch_id' => 'required|string|max:64',
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $pendingAllowances = EmployeeAllowance::query()
            ->where('request_batch_id', $validated['batch_id'])
            ->where('status', 'pending')
            ->get();

        if ($pendingAllowances->isEmpty()) {
            return response()->json([
                'message' => 'No pending allowances found for this batch.',
            ], 400);
        }

        $processedCount = 0;

        DB::transaction(function () use ($pendingAllowances, $validated, &$processedCount) {
            foreach ($pendingAllowances as $allowance) {
                $oldValues = $allowance->toArray();

                if ($validated['action'] === 'approve') {
                    $allowance->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                        'rejected_by' => null,
                        'rejected_at' => null,
                        'rejection_reason' => null,
                    ]);

                    $auditAction = 'approve_allowance';
                } else {
                    $allowance->update([
                        'status' => 'rejected',
                        'approved_by' => null,
                        'approved_at' => null,
                        'rejected_by' => auth()->id(),
                        'rejected_at' => now(),
                        'rejection_reason' => $validated['rejection_reason'] ?? null,
                        'is_active' => false,
                    ]);

                    $auditAction = 'reject_allowance';
                }

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'allowances',
                    'action' => $auditAction,
                    'description' => "Allowance '{$allowance->allowance_name}' {$validated['action']}d via request batch",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'old_values' => $oldValues,
                    'new_values' => $allowance->fresh()->toArray(),
                ]);

                $processedCount++;
            }
        });

        $actionLabel = $validated['action'] === 'approve' ? 'approved' : 'rejected';

        return response()->json([
            'message' => "{$processedCount} allowances {$actionLabel} successfully for this batch.",
            'processed_count' => $processedCount,
            'batch_id' => $validated['batch_id'],
        ]);
    }

    public function destroy(EmployeeAllowance $allowance)
    {
        $allowanceData = $allowance->toArray();
        $allowance->delete();

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'allowances',
            'action' => 'delete_allowance',
            'description' => "Allowance '{$allowanceData['allowance_name']}' deleted",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $allowanceData,
        ]);

        return response()->json(['message' => 'Allowance deleted successfully']);
    }
}
