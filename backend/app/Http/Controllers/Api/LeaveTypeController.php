<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index()
    {
        $leaveTypes = LeaveType::all();
        return response()->json($leaveTypes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:leave_types,name',
            'description' => 'nullable|string',
            'max_days_per_year' => 'required|integer|min:0',
            'is_paid' => 'boolean',
            'requires_medical_certificate' => 'boolean',
        ]);

        $leaveType = LeaveType::create($validated);

        return response()->json($leaveType, 201);
    }

    public function show(LeaveType $leaveType)
    {
        return response()->json($leaveType);
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $validated = $request->validate([
            'name' => 'string|unique:leave_types,name,' . $leaveType->id,
            'max_days_per_year' => 'integer|min:0',
            'is_paid' => 'boolean',
            'requires_medical_certificate' => 'boolean',
        ]);

        $leaveType->update($validated);

        return response()->json($leaveType);
    }

    public function destroy(LeaveType $leaveType)
    {
        $leaveType->delete();

        return response()->json(['message' => 'Leave type deleted successfully']);
    }
}
