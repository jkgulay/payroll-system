<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with(['department', 'location']);

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by department
        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by location
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Filter by status
        if ($request->has('employment_status')) {
            $query->where('employment_status', $request->employment_status);
        }

        $employees = $query->paginate($request->get('per_page', 15));

        return response()->json($employees);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_number' => 'required|string|unique:employees',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date',
            'email' => 'required|email|unique:employees',
            'mobile_number' => 'nullable|string|max:20',
            'department_id' => 'required|exists:departments,id',
            'location_id' => 'required|exists:locations,id',
            'position' => 'nullable|string|max:100',
            'employment_status' => 'required|in:regular,probationary,contractual,active,resigned,terminated,retired',
            'employment_type' => 'required|in:regular,contractual,part_time',
            'date_hired' => 'required|date',
            'basic_salary' => 'required|numeric|min:0',
            'salary_type' => 'required|in:daily,weekly,semi-monthly,monthly',
            // Optional user account creation fields
            'create_user_account' => 'nullable|boolean',
            'username' => 'nullable|string|unique:users,username',
            'password' => 'nullable|string|min:6',
        ]);

        DB::beginTransaction();
        try {
            // Create employee
            $employee = Employee::create($validated);

            // Create user account if requested
            if ($request->input('create_user_account', false) && $request->username && $request->password) {
                User::create([
                    'username' => $request->username,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'employee',
                    'is_active' => true,
                ]);
            }

            DB::commit();
            return response()->json($employee, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create employee: ' . $e->getMessage()], 500);
        }
    }

    public function show(Employee $employee)
    {
        $employee->load(['department', 'location', 'allowances', 'loans', 'deductions']);
        return response()->json($employee);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'employee_number' => 'required|string|unique:employees,employee_number,' . $employee->id,
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:employees,email,' . $employee->id,
            'mobile_number' => 'nullable|string|max:20',
            'department_id' => 'nullable|exists:departments,id',
            'location_id' => 'nullable|exists:locations,id',
            'position' => 'nullable|string|max:100',
            'employment_status' => 'required|in:active,resigned,terminated,retired',
            'employment_type' => 'required|in:regular,contractual,part_time',
            'date_hired' => 'required|date',
            'basic_salary' => 'required|numeric|min:0',
            'salary_type' => 'required|in:daily,monthly,hourly',
        ]);

        $employee->update($validated);

        return response()->json($employee);
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return response()->json(['message' => 'Employee deleted successfully']);
    }

    public function allowances(Employee $employee)
    {
        return response()->json($employee->allowances);
    }

    public function loans(Employee $employee)
    {
        return response()->json($employee->loans);
    }

    public function deductions(Employee $employee)
    {
        return response()->json($employee->deductions);
    }
}
