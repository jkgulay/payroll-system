<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GovernmentRate;
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

        $governmentRate->update($validated);

        return response()->json($governmentRate);
    }

    public function destroy(GovernmentRate $governmentRate): JsonResponse
    {
        $governmentRate->forceDelete(); // Permanent delete
        return response()->json(['message' => 'Government rate deleted successfully']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:government_rates,id',
        ]);

        GovernmentRate::whereIn('id', $validated['ids'])->forceDelete();

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
