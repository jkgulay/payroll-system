<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PositionRate;
use App\Models\Employee;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PositionRateController extends Controller
{
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

        $positionRates = $query->withCount('employees')->orderBy('position_name')->get();

        return response()->json($positionRates);
    }

    /**
     * Create new position rate
     */
    public function store(Request $request): JsonResponse
    {
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
        $positionRate->employee_count = $positionRate->getEmployeeCount();
        return response()->json($positionRate);
    }

    /**
     * Update position rate
     */
    public function update(Request $request, PositionRate $positionRate): JsonResponse
    {
        $validated = $request->validate([
            'position_name' => 'required|string|max:100|unique:position_rates,position_name,' . $positionRate->id,
            'daily_rate' => 'required|numeric|min:0',
            'category' => 'nullable|string|in:skilled,semi-skilled,technical,support',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['updated_by'] = auth()->id();

        $oldValues = $positionRate->toArray();
        $positionRate->update($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'position_rates',
            'action' => 'update_position_rate',
            'description' => "Position rate '{$positionRate->position_name}' updated",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $positionRate->fresh()->toArray(),
        ]);

        return response()->json([
            'message' => 'Position rate updated successfully',
            'data' => $positionRate->fresh(),
        ]);
    }

    /**
     * Delete position rate
     */
    public function destroy(PositionRate $positionRate): JsonResponse
    {
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
        $validated = $request->validate([
            'new_rate' => 'required|numeric|min:0',
        ]);

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

            DB::commit();

            return response()->json([
                'message' => "Updated {$updatedCount} employee(s) successfully",
                'updated_count' => $updatedCount,
                'position_rate' => $positionRate->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update employees: ' . $e->getMessage()], 500);
        }
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
