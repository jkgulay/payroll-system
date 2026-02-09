<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GovernmentRate;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GovernmentRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = GovernmentRate::query()->with(['createdBy', 'updatedBy']);

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $rates = $query->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('min_salary')
            ->get();

        return response()->json(['data' => $rates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:sss,philhealth,pagibig,tax',
            'name' => 'required|string|max:255',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'employee_rate' => 'nullable|numeric|min:0|max:100',
            'employer_rate' => 'nullable|numeric|min:0|max:100',
            'employee_fixed' => 'nullable|numeric|min:0',
            'employer_fixed' => 'nullable|numeric|min:0',
            'total_contribution' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
            'end_date' => 'nullable|date|after:effective_date',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();

        $rate = GovernmentRate::create($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'government_rates',
            'action' => 'create_rate',
            'description' => "Government rate '{$rate->name}' ({$rate->type}) created",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'new_values' => $rate->toArray(),
        ]);

        return response()->json($rate, 201);
    }

    public function show(GovernmentRate $governmentRate): JsonResponse
    {
        $governmentRate->load(['createdBy', 'updatedBy']);
        return response()->json($governmentRate);
    }

    public function update(Request $request, GovernmentRate $governmentRate): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|in:sss,philhealth,pagibig,tax',
            'name' => 'sometimes|string|max:255',
            'min_salary' => 'nullable|numeric|min:0',
            'max_salary' => 'nullable|numeric|min:0|gte:min_salary',
            'employee_rate' => 'nullable|numeric|min:0|max:100',
            'employer_rate' => 'nullable|numeric|min:0|max:100',
            'employee_fixed' => 'nullable|numeric|min:0',
            'employer_fixed' => 'nullable|numeric|min:0',
            'total_contribution' => 'nullable|numeric|min:0',
            'effective_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after:effective_date',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $validated['updated_by'] = auth()->id();

        $oldValues = $governmentRate->toArray();
        $governmentRate->update($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'government_rates',
            'action' => 'update_rate',
            'description' => "Government rate '{$governmentRate->name}' ({$governmentRate->type}) updated",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $governmentRate->fresh()->toArray(),
        ]);

        return response()->json($governmentRate);
    }

    public function destroy(GovernmentRate $governmentRate): JsonResponse
    {
        $rateData = $governmentRate->toArray();
        $governmentRate->forceDelete();

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'government_rates',
            'action' => 'delete_rate',
            'description' => "Government rate '{$rateData['name']}' ({$rateData['type']}) deleted",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $rateData,
        ]);

        return response()->json(['message' => 'Government rate deleted successfully']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:government_rates,id',
        ]);

        GovernmentRate::whereIn('id', $validated['ids'])->forceDelete();

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'government_rates',
            'action' => 'bulk_delete_rates',
            'description' => 'Bulk deleted ' . count($validated['ids']) . ' government rate(s)',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => ['deleted_ids' => $validated['ids']],
        ]);

        return response()->json([
            'message' => 'Successfully deleted ' . count($validated['ids']) . ' rate(s)'
        ]);
    }

    public function getForSalary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:sss,philhealth,pagibig,tax',
            'salary' => 'required|numeric|min:0',
            'date' => 'nullable|date',
        ]);

        $contribution = GovernmentRate::getContributionForSalary(
            $validated['type'],
            $validated['salary'],
            $validated['date'] ?? null
        );

        return response()->json($contribution);
    }
}
