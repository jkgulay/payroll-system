<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAllowance;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AllowanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,hr,payrollist')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $query = EmployeeAllowance::with('employee');

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('allowance_type')) {
            $query->where('allowance_type', $request->allowance_type);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
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

        // Auto-generate allowance_name from allowance_type if not provided
        if (empty($validated['allowance_name'])) {
            $validated['allowance_name'] = ucwords(str_replace('_', ' ', $validated['allowance_type'])) . ' Allowance';
        }

        // Set created_by to authenticated user
        $validated['created_by'] = auth()->id();

        $allowance = EmployeeAllowance::create($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'allowances',
            'action' => 'create_allowance',
            'description' => "Allowance '{$allowance->allowance_name}' created for employee #{$allowance->employee_id}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'new_values' => $allowance->toArray(),
        ]);

        return response()->json([
            'message' => 'Allowance created successfully',
            'data' => $allowance->load('employee'),
        ], 201);
    }

    public function show(EmployeeAllowance $allowance)
    {
        return response()->json([
            'data' => $allowance->load('employee'),
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

        $oldValues = $allowance->toArray();
        $allowance->update($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'allowances',
            'action' => 'update_allowance',
            'description' => "Allowance '{$allowance->allowance_name}' updated",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $allowance->fresh()->toArray(),
        ]);

        return response()->json([
            'message' => 'Allowance updated successfully',
            'data' => $allowance->load('employee'),
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
