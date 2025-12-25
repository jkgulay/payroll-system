<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeBonus;
use Illuminate\Http\Request;

class BonusController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeBonus::with('employee');

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('bonus_type')) {
            $query->where('bonus_type', $request->bonus_type);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'bonus_type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'bonus_date' => 'required|date',
        ]);

        $bonus = EmployeeBonus::create($validated);

        return response()->json($bonus->load('employee'), 201);
    }

    public function show(EmployeeBonus $bonus)
    {
        return response()->json($bonus->load('employee'));
    }

    public function update(Request $request, EmployeeBonus $bonus)
    {
        $validated = $request->validate([
            'amount' => 'numeric|min:0',
        ]);

        $bonus->update($validated);

        return response()->json($bonus);
    }

    public function destroy(EmployeeBonus $bonus)
    {
        $bonus->delete();

        return response()->json(['message' => 'Bonus deleted successfully']);
    }
}
