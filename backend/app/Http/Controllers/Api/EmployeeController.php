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
        $query = Employee::with(['project']);

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

        // Filter by project
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by status
        if ($request->has('employment_status')) {
            $query->where('employment_status', $request->employment_status);
        }

        // Filter by employment type
        if ($request->has('employment_type')) {
            $query->where('employment_type', $request->employment_type);
        }

        // Filter by position
        if ($request->has('position') && $request->position) {
            $query->where('position', $request->position);
        }

        $employees = $query->paginate($request->get('per_page', 15));

        return response()->json($employees);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'email' => 'nullable|email|unique:employees,email',
            'mobile_number' => 'nullable|string|max:20',
            'project_id' => 'required|exists:projects,id',
            'worker_address' => 'nullable|string',
            'position' => 'nullable|string|max:100',
            'employment_status' => 'required|in:regular,probationary,contractual,active,resigned,terminated,retired',
            'employment_type' => 'required|in:regular,contractual,part_time',
            'date_hired' => 'required|date',
            'basic_salary' => 'required|numeric|min:0',
            'salary_type' => 'required|in:daily,weekly,semi-monthly,monthly',
            // Allowances
            'has_water_allowance' => 'nullable|boolean',
            'water_allowance' => 'nullable|numeric|min:0',
            'has_cola' => 'nullable|boolean',
            'cola' => 'nullable|numeric|min:0',
            'has_incentives' => 'nullable|boolean',
            'incentives' => 'nullable|numeric|min:0',
            'has_ppe' => 'nullable|boolean',
            'ppe' => 'nullable|numeric|min:0',
            // User account role
            'role' => 'required|in:accountant,employee',
        ]);

        DB::beginTransaction();
        try {
            // Generate employee number (EMP001, EMP002, etc.)
            $lastEmployee = Employee::orderBy('id', 'desc')->first();
            $nextNumber = $lastEmployee ? ((int) substr($lastEmployee->employee_number, 3)) + 1 : 1;
            $validated['employee_number'] = 'EMP' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // Create employee
            $employee = Employee::create($validated);

            // Generate password: LastName + EmployeeID + 2 random digits
            $randomDigits = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
            $autoPassword = $validated['last_name'] . $validated['employee_number'] . '@' . $randomDigits;

            // Generate username: Use email if provided, otherwise firstname.lastname
            if (!empty($validated['email'])) {
                $username = $validated['email'];
                $email = $validated['email'];
            } else {
                // Generate username from firstname.lastname
                $baseUsername = strtolower($validated['first_name'] . '.' . $validated['last_name']);
                // Remove special characters and spaces
                $baseUsername = preg_replace('/[^a-z0-9.]/', '', $baseUsername);

                // Ensure username is unique
                $username = $baseUsername;
                $counter = 1;
                while (User::where('username', $username)->exists()) {
                    $username = $baseUsername . $counter;
                    $counter++;
                }

                // Use a placeholder email or null
                $email = null;
            }

            // Always create user account
            User::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($autoPassword),
                'role' => $validated['role'],
                'is_active' => true,
                'must_change_password' => true, // Force password change on first login
            ]);

            // Store temporary password for response (in real app, send via email)
            $employee->temporary_password = $autoPassword;

            DB::commit();
            return response()->json([
                'employee' => $employee,
                'role' => $validated['role'],
                'username' => $username,
                'temporary_password' => $autoPassword,
                'message' => 'Employee created successfully. Username: ' . $username . ', Temporary password: ' . $autoPassword
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create employee: ' . $e->getMessage()], 500);
        }
    }

    public function show(Employee $employee)
    {
        $employee->load(['project', 'allowances', 'loans', 'deductions']);
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
            'project_id' => 'nullable|exists:projects,id',
            'worker_address' => 'nullable|string',
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
