<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LeaveTypeController extends Controller
{
    public function index()
    {
        $query = LeaveType::query()->orderBy('name');

        if (!request()->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $leaveTypes = $query->get();
        return response()->json($leaveTypes);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:leave_types,code',
            'name' => 'required|string|max:255|unique:leave_types,name',
            'description' => 'nullable|string',
            'default_credits' => 'nullable|numeric|min:0',
            'max_days_per_year' => 'nullable|numeric|min:0',
            'is_paid' => 'sometimes|boolean',
            'requires_approval' => 'sometimes|boolean',
            'requires_medical_certificate' => 'sometimes|boolean',
            'is_convertible_to_cash' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $leaveType = LeaveType::create($this->normalizePayload($validated));

        return response()->json($leaveType, 201);
    }

    public function show(LeaveType $leaveType)
    {
        return response()->json($leaveType);
    }

    public function update(Request $request, LeaveType $leaveType)
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:leave_types,code,' . $leaveType->id,
            'name' => 'sometimes|string|max:255|unique:leave_types,name,' . $leaveType->id,
            'description' => 'sometimes|nullable|string',
            'default_credits' => 'sometimes|numeric|min:0',
            'max_days_per_year' => 'sometimes|numeric|min:0',
            'is_paid' => 'sometimes|boolean',
            'requires_approval' => 'sometimes|boolean',
            'requires_medical_certificate' => 'sometimes|boolean',
            'is_convertible_to_cash' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $leaveType->update($this->normalizePayload($validated, $leaveType));

        return response()->json($leaveType);
    }

    public function destroy(LeaveType $leaveType)
    {
        $leaveType->delete();

        return response()->json(['message' => 'Leave type deleted successfully']);
    }

    private function normalizePayload(array $validated, ?LeaveType $leaveType = null): array
    {
        $payload = [];

        if (array_key_exists('code', $validated) && $validated['code']) {
            $payload['code'] = $validated['code'];
        } elseif (!$leaveType) {
            $payload['code'] = $this->generateUniqueCode($validated['name']);
        }

        if (array_key_exists('name', $validated)) {
            $payload['name'] = $validated['name'];
        }

        if (array_key_exists('description', $validated)) {
            $payload['description'] = $validated['description'];
        }

        if (array_key_exists('default_credits', $validated) || array_key_exists('max_days_per_year', $validated)) {
            $payload['default_credits'] = $validated['default_credits'] ?? $validated['max_days_per_year'];
        } elseif (!$leaveType) {
            $payload['default_credits'] = 0;
        }

        if (array_key_exists('is_paid', $validated)) {
            $payload['is_paid'] = $validated['is_paid'];
        } elseif (!$leaveType) {
            $payload['is_paid'] = true;
        }

        if (array_key_exists('requires_approval', $validated) || array_key_exists('requires_medical_certificate', $validated)) {
            $payload['requires_approval'] = $validated['requires_approval']
                ?? (bool) $validated['requires_medical_certificate'];
        } elseif (!$leaveType) {
            $payload['requires_approval'] = true;
        }

        if (array_key_exists('is_convertible_to_cash', $validated)) {
            $payload['is_convertible_to_cash'] = $validated['is_convertible_to_cash'];
        } elseif (!$leaveType) {
            $payload['is_convertible_to_cash'] = false;
        }

        if (array_key_exists('is_active', $validated)) {
            $payload['is_active'] = $validated['is_active'];
        } elseif (!$leaveType) {
            $payload['is_active'] = true;
        }

        return $payload;
    }

    private function generateUniqueCode(string $name): string
    {
        $base = strtoupper(Str::slug($name, '_'));
        if ($base === '') {
            $base = 'LEAVE';
        }

        $code = $base;
        $suffix = 1;
        while (LeaveType::where('code', $code)->exists()) {
            $suffix++;
            $code = $base . '_' . $suffix;
        }

        return $code;
    }
}
