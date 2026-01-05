<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use App\Models\AuditLog;
use App\Helpers\DateHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Filter by contract type
        if ($request->has('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }

        // Filter by activity status
        if ($request->has('activity_status')) {
            $query->where('activity_status', $request->activity_status);
        }

        // Filter by employment type
        if ($request->has('employment_type')) {
            $query->where('employment_type', $request->employment_type);
        }

        // Filter by position
        if ($request->has('position') && $request->position) {
            $query->where('position_id', $request->position);
        }

        $perPage = $request->get('per_page', 50); // Increased default from 15 to 50
        $employees = $query->latest('created_at')->paginate($perPage);

        return response()->json($employees);
    }

    public function store(StoreEmployeeRequest $request)
    {
        // Validation is handled by StoreEmployeeRequest
        $validated = $request->validated();

        // Validate role separately (it's for user account, not employee record)
        $role = $request->validate([
            'role' => 'nullable|in:admin,accountant,employee',
        ])['role'] ?? 'employee'; // Default to 'employee' if not provided

        // Set defaults for required database fields if not provided
        $validated['date_of_birth'] = $validated['date_of_birth'] ?? DateHelper::yearsAgo(25);
        $validated['project_id'] = $validated['project_id'] ?? 1; // Default to first project
        $validated['contract_type'] = $validated['contract_type'] ?? 'regular';
        $validated['activity_status'] = $validated['activity_status'] ?? 'active';
        $validated['employment_type'] = $validated['employment_type'] ?? 'regular';
        $validated['date_hired'] = $validated['date_hired'] ?? DateHelper::today();
        $validated['basic_salary'] = $validated['basic_salary'] ?? 450; // Minimum wage default
        $validated['salary_type'] = $validated['salary_type'] ?? 'daily';

        // Normalize gender to lowercase for consistency
        if (!empty($validated['gender'])) {
            $validated['gender'] = strtolower($validated['gender']);
        } else {
            $validated['gender'] = 'male'; // Default
        }

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
                'role' => $role,
                'is_active' => true,
                'must_change_password' => true, // Force password change on first login
            ]);

            // Store temporary password for response (in real app, send via email)
            $employee->temporary_password = $autoPassword;

            DB::commit();
            return response()->json([
                'employee' => $employee,
                'role' => $role,
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
            'employee_number' => 'sometimes|string|unique:employees,employee_number,' . $employee->id,
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'gender' => 'nullable|in:male,female,other',
            'email' => 'nullable|email|unique:employees,email,' . $employee->id,
            'mobile_number' => 'nullable|string|max:20',
            'project_id' => 'nullable|exists:projects,id',
            'worker_address' => 'nullable|string',
            'position' => 'nullable|string|max:100',
            'contract_type' => 'nullable|in:regular,probationary,contractual',
            'activity_status' => 'nullable|in:active,on_leave,resigned,terminated,retired',
            'employment_type' => 'nullable|in:regular,contractual,part_time',
            'date_hired' => 'nullable|date',
            'basic_salary' => 'nullable|numeric|min:450',
            'salary_type' => 'nullable|in:daily,monthly,hourly',
        ]);

        // Normalize gender to lowercase for consistency
        if (isset($validated['gender'])) {
            $validated['gender'] = strtolower($validated['gender']);
        }

        // Track salary changes for audit
        if (isset($validated['basic_salary']) && $validated['basic_salary'] != $employee->basic_salary) {
            AuditLog::logSalaryChange($employee, $employee->basic_salary, $validated['basic_salary']);
        }

        // Track position changes (may affect salary)
        if (isset($validated['position']) && $validated['position'] != $employee->position) {
            $oldSalary = $employee->basic_salary;
            $newSalary = $validated['basic_salary'] ?? $oldSalary;
            AuditLog::logPositionChange(
                $employee,
                $employee->position,
                $validated['position'],
                $oldSalary,
                $newSalary
            );
        }

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

    /**
     * Get employee credentials (username, email, role)
     */
    public function getCredentials(Employee $employee)
    {
        $user = User::where('id', $employee->user_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User account not found'], 404);
        }

        return response()->json([
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * Reset employee password and generate new temporary password
     */
    public function resetPassword(Employee $employee)
    {
        $user = User::where('id', $employee->user_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User account not found'], 404);
        }

        // Generate new temporary password: LastName + EmployeeNumber + @ + 2 random digits
        $randomDigits = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
        $newPassword = $employee->last_name . $employee->employee_number . '@' . $randomDigits;

        // Update user password
        $user->password = Hash::make($newPassword);
        $user->must_change_password = true;
        $user->save();

        // Log the action
        Log::info("Password reset for employee {$employee->employee_number} by user " . auth()->id());

        return response()->json([
            'message' => 'Password reset successfully',
            'temporary_password' => $newPassword,
        ]);
    }
}
