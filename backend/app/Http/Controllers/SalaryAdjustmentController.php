<?php

namespace App\Http\Controllers;

use App\Models\SalaryAdjustment;
use App\Models\Employee;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SalaryAdjustmentController extends Controller
{
    /**
     * Display a listing of all salary adjustments.
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
        $employees = Employee::where('activity_status', 'active')
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
            'reason' => 'nullable|string|max:255',
            'reference_period' => 'nullable|string|max:255',
            'effective_date' => 'nullable|date',
        ]);

        $adjustment = SalaryAdjustment::create([
            'employee_id' => $validated['employee_id'],
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'reason' => $validated['reason'] ?? null,
            'reference_period' => $validated['reference_period'] ?? null,
            'effective_date' => $validated['effective_date'] ?? null,
            'status' => 'pending',
            'created_by' => Auth::id(),
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
            'description' => "Salary adjustment created for employee #{$adjustment->employee_id}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'new_values' => $adjustment->toArray(),
        ]);

        return response()->json([
            'message' => 'Salary adjustment created successfully',
            'adjustment' => $adjustment->load(['employee', 'createdBy']),
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
        // Only allow updates if not yet applied
        if ($salaryAdjustment->status === 'applied') {
            return response()->json([
                'message' => 'Cannot update an adjustment that has already been applied to a payroll.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'type' => 'sometimes|in:deduction,addition',
            'reason' => 'nullable|string|max:255',
            'reference_period' => 'nullable|string|max:255',
            'effective_date' => 'nullable|date',
            'status' => 'sometimes|in:pending,cancelled',
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
            'message' => 'Salary adjustment updated successfully',
            'adjustment' => $salaryAdjustment->load(['employee', 'createdBy']),
        ]);
    }

    /**
     * Remove the specified salary adjustment.
     */
    public function destroy(SalaryAdjustment $salaryAdjustment)
    {
        // Only allow deletion if not yet applied
        if ($salaryAdjustment->status === 'applied') {
            return response()->json([
                'message' => 'Cannot delete an adjustment that has already been applied to a payroll.',
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
            'message' => 'Salary adjustment deleted successfully',
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
            'adjustments.*.reason' => 'nullable|string|max:255',
            'adjustments.*.reference_period' => 'nullable|string|max:255',
            'adjustments.*.effective_date' => 'nullable|date',
            'adjustments.*.notes' => 'nullable|string',
        ]);

        $created = [];
        foreach ($validated['adjustments'] as $adjustmentData) {
            $adjustmentData['created_by'] = Auth::id();
            $adjustmentData['status'] = 'pending';
            $created[] = SalaryAdjustment::create($adjustmentData);
        }

        Log::info('Bulk salary adjustments created', [
            'count' => count($created),
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => count($created) . ' salary adjustments created successfully',
            'adjustments' => $created,
        ], 201);
    }
}
