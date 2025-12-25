<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDeduction;
use Illuminate\Http\Request;

class DeductionController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeDeduction::with('employee');

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('deduction_type')) {
            $query->where('deduction_type', $request->deduction_type);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'deduction_type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'required|in:one_time,semi_monthly,monthly',
            'deduction_date' => 'required|date',
        ]);

        $deduction = EmployeeDeduction::create($validated);

        return response()->json($deduction->load('employee'), 201);
    }

    public function show(EmployeeDeduction $deduction)
    {
        return response()->json($deduction->load('employee'));
    }

    public function update(Request $request, EmployeeDeduction $deduction)
    {
        $validated = $request->validate([
            'amount' => 'numeric|min:0',
            'frequency' => 'in:one_time,semi_monthly,monthly',
        ]);

        $deduction->update($validated);

        return response()->json($deduction);
    }

    public function destroy(EmployeeDeduction $deduction)
    {
        $deduction->delete();

        return response()->json(['message' => 'Deduction deleted successfully']);
    }
}
