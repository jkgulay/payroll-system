<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeBonus;
use App\Models\AuditLog;
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

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'bonuses',
            'action' => 'create_bonus',
            'description' => "Bonus '{$bonus->bonus_type}' created for employee #{$bonus->employee_id}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'new_values' => $bonus->toArray(),
        ]);

        return response()->json([
            'message' => 'Bonus created successfully',
            'data' => $bonus->load('employee'),
        ], 201);
    }

    public function show(EmployeeBonus $bonus)
    {
        return response()->json([
            'data' => $bonus->load('employee'),
        ]);
    }

    public function update(Request $request, EmployeeBonus $bonus)
    {
        $validated = $request->validate([
            'bonus_type' => 'sometimes|string',
            'amount' => 'sometimes|numeric|min:0',
            'bonus_date' => 'sometimes|date',
        ]);

        $oldValues = $bonus->toArray();
        $bonus->update($validated);

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'bonuses',
            'action' => 'update_bonus',
            'description' => "Bonus '{$bonus->bonus_type}' updated",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $bonus->fresh()->toArray(),
        ]);

        return response()->json([
            'message' => 'Bonus updated successfully',
            'data' => $bonus->load('employee'),
        ]);
    }

    public function destroy(EmployeeBonus $bonus)
    {
        $bonusData = $bonus->toArray();
        $bonus->delete();

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'bonuses',
            'action' => 'delete_bonus',
            'description' => "Bonus '{$bonusData['bonus_type']}' deleted",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $bonusData,
        ]);

        return response()->json(['message' => 'Bonus deleted successfully']);
    }
}
