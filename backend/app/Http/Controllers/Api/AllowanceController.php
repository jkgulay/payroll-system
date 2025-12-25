<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeAllowance;
use Illuminate\Http\Request;

class AllowanceController extends Controller
{
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
            'allowance_type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:daily,semi_monthly,monthly',
            'start_date' => 'required|date',
        ]);

        $allowance = EmployeeAllowance::create($validated);

        return response()->json($allowance->load('employee'), 201);
    }

    public function show(EmployeeAllowance $allowance)
    {
        return response()->json($allowance->load('employee'));
    }

    public function update(Request $request, EmployeeAllowance $allowance)
    {
        $validated = $request->validate([
            'amount' => 'numeric|min:0',
            'frequency' => 'in:daily,semi_monthly,monthly',
        ]);

        $allowance->update($validated);

        return response()->json($allowance);
    }

    public function destroy(EmployeeAllowance $allowance)
    {
        $allowance->delete();

        return response()->json(['message' => 'Allowance deleted successfully']);
    }
}
