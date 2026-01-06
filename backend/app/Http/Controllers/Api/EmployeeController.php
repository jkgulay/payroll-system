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
        $query = Employee::with(['project', 'positionRate']);

        // Search - case-insensitive search across multiple fields
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('middle_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%")
                    // Search by full name (first + last, first + middle + last)
                    ->orWhereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", ['%' . strtolower($search) . '%'])
                    ->orWhereRaw("LOWER(CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name)) LIKE ?", ['%' . strtolower($search) . '%'])
                    // Search by position name
                    ->orWhereHas('positionRate', function ($posQuery) use ($search) {
                        $posQuery->where('position_name', 'ilike', "%{$search}%");
                    })
                    // Search by project name
                    ->orWhereHas('project', function ($projQuery) use ($search) {
                        $projQuery->where('name', 'ilike', "%{$search}%");
                    });
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

        // Filter by work schedule (new) or employment_type (legacy)
        if ($request->has('work_schedule')) {
            $query->where('work_schedule', $request->work_schedule);
        } elseif ($request->has('employment_type')) {
            // Legacy support: map old values to new
            $schedule = $request->employment_type === 'part_time' ? 'part_time' : 'full_time';
            $query->where('work_schedule', $schedule);
        }

        // Filter by position
        if ($request->has('position') && $request->position) {
            // Check if it's a position ID (numeric) or position name (string)
            if (is_numeric($request->position)) {
                $query->where('position_id', $request->position);
            } else {
                // Convert position name to position_id
                $positionRate = \App\Models\PositionRate::where('position_name', $request->position)->first();
                if ($positionRate) {
                    $query->where('position_id', $positionRate->id);
                }
            }
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

        // Handle work_schedule (new) or employment_type (legacy)
        if (isset($validated['work_schedule'])) {
            // Already set, keep it
        } elseif (isset($validated['employment_type'])) {
            // Legacy: map old values to new
            $validated['work_schedule'] = $validated['employment_type'] === 'part_time' ? 'part_time' : 'full_time';
            unset($validated['employment_type']);
        } else {
            $validated['work_schedule'] = 'full_time'; // Default
        }

        $validated['date_hired'] = $validated['date_hired'] ?? DateHelper::today();
        $validated['basic_salary'] = $validated['basic_salary'] ?? 450; // Minimum wage default
        $validated['salary_type'] = $validated['salary_type'] ?? 'daily';

        // Normalize gender to lowercase for consistency
        if (!empty($validated['gender'])) {
            $validated['gender'] = strtolower($validated['gender']);
        } else {
            $validated['gender'] = 'male'; // Default
        }

        // Map position string to position_id if position is provided but position_id is not
        if (!empty($validated['position']) && empty($validated['position_id'])) {
            $positionRate = \App\Models\PositionRate::where('position_name', $validated['position'])->first();
            if ($positionRate) {
                $validated['position_id'] = $positionRate->id;
                // ALWAYS set basic_salary from position rate (this ensures consistency)
                $validated['basic_salary'] = $positionRate->daily_rate;

                // Auto-assign role: if position is "Accountant", set role to "accountant"
                if (strtolower($validated['position']) === 'accountant') {
                    $role = 'accountant';
                }
            }
        }

        // If position_id is provided but basic_salary is not, get rate from PositionRate
        if (!empty($validated['position_id']) && empty($validated['basic_salary'])) {
            $positionRate = \App\Models\PositionRate::find($validated['position_id']);
            if ($positionRate) {
                $validated['basic_salary'] = $positionRate->daily_rate;

                // Auto-assign role: if position is "Accountant", set role to "accountant"
                if (strtolower($positionRate->position_name) === 'accountant') {
                    $role = 'accountant';
                }
            }
        }

        // CRITICAL: Remove position string from validated data BEFORE create
        // We only save position_id, not position (column doesn't exist in DB)
        if (isset($validated['position'])) {
            unset($validated['position']);
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

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $validated = $request->validated();

        // Map position string to position_id
        // This handles both cases: new position provided OR position changed
        if (!empty($validated['position'])) {
            $positionRate = \App\Models\PositionRate::where('position_name', $validated['position'])->first();
            if ($positionRate) {
                // Update position_id and salary even if position_id was already set
                $validated['position_id'] = $positionRate->id;
                // ALWAYS set basic_salary from position rate when position changes
                $validated['basic_salary'] = $positionRate->daily_rate;
            }
        }

        // If position_id is provided, always sync basic_salary with position rate
        if (!empty($validated['position_id'])) {
            $positionRate = \App\Models\PositionRate::find($validated['position_id']);
            if ($positionRate) {
                $validated['basic_salary'] = $positionRate->daily_rate;
            }
        }

        // CRITICAL: Remove position string from validated data BEFORE filtering
        // We only save position_id, not position
        if (isset($validated['position'])) {
            unset($validated['position']);
        }

        // Normalize gender to lowercase for consistency
        if (isset($validated['gender'])) {
            $validated['gender'] = strtolower($validated['gender']);
        }

        // Track salary changes for audit
        if (isset($validated['basic_salary']) && $validated['basic_salary'] != $employee->basic_salary) {
            AuditLog::logSalaryChange($employee, $employee->basic_salary, $validated['basic_salary']);
        }

        // Track position changes (may affect salary) - using position_id now
        if (isset($validated['position_id']) && $validated['position_id'] != $employee->position_id) {
            $oldPosition = $employee->positionRate;
            $newPosition = \App\Models\PositionRate::find($validated['position_id']);
            $oldSalary = $employee->basic_salary;
            $newSalary = $validated['basic_salary'] ?? $oldSalary;
            if ($oldPosition && $newPosition) {
                AuditLog::logPositionChange(
                    $employee,
                    $oldPosition->position_name,
                    $newPosition->position_name,
                    $oldSalary,
                    $newSalary
                );

                // Auto-update user role: if new position is "Accountant", set role to "accountant"
                if (strtolower($newPosition->position_name) === 'accountant') {
                    if ($employee->user_id) {
                        \App\Models\User::where('id', $employee->user_id)->update(['role' => 'accountant']);
                    }
                }
                // If changing FROM Accountant to something else, set role back to "employee"
                elseif (strtolower($oldPosition->position_name) === 'accountant') {
                    if ($employee->user_id) {
                        \App\Models\User::where('id', $employee->user_id)->update(['role' => 'employee']);
                    }
                }
            }
        }

        $employee->update($validated);
        $employee->load(['project', 'positionRate']); // Load relationships for response

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
        // Check if user account exists, if not create one
        if (!$employee->user_id) {
            $user = $this->createUserForEmployee($employee);
        } else {
            $user = User::where('id', $employee->user_id)->first();
            if (!$user) {
                // User ID exists but user not found, create new user
                $user = $this->createUserForEmployee($employee);
            }
        }

        return response()->json([
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'temporary_password' => $user->temporary_password ?? null, // Include if just created
        ]);
    }

    /**
     * Reset employee password and generate new temporary password
     */
    public function resetPassword(Employee $employee)
    {
        // Check if user account exists, if not create one
        if (!$employee->user_id) {
            $user = $this->createUserForEmployee($employee);
            // Return the newly created credentials
            return response()->json([
                'message' => 'User account created and password set',
                'temporary_password' => $user->temporary_password,
            ]);
        }

        $user = User::where('id', $employee->user_id)->first();

        if (!$user) {
            // User ID exists but user not found, create new user
            $user = $this->createUserForEmployee($employee);
            return response()->json([
                'message' => 'User account created and password set',
                'temporary_password' => $user->temporary_password,
            ]);
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

    /**
     * Create a user account for an employee without one
     */
    private function createUserForEmployee(Employee $employee)
    {
        // Generate username from name (firstname.lastname)
        $username = strtolower($employee->first_name . '.' . $employee->last_name);

        // Check if username exists, if so append number
        $originalUsername = $username;
        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $username = $originalUsername . $counter;
            $counter++;
        }

        // Generate temporary password: LastName + EmployeeNumber + @ + 2 random digits
        $randomDigits = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
        $temporaryPassword = $employee->last_name . $employee->employee_number . '@' . $randomDigits;

        // Determine role based on position
        $role = 'employee'; // Default role
        if ($employee->position_id) {
            $position = \App\Models\PositionRate::find($employee->position_id);
            if ($position && strtolower($position->position_name) === 'accountant') {
                $role = 'accountant';
            }
        }

        // Create user account
        $user = User::create([
            'username' => $username,
            'email' => $employee->email ?? $username . '@company.com',
            'password' => Hash::make($temporaryPassword),
            'role' => $role,
            'is_active' => true,
            'must_change_password' => true,
        ]);

        // Store temporary password on user object (not saved to DB)
        $user->temporary_password = $temporaryPassword;

        // Link user to employee
        $employee->user_id = $user->id;
        $employee->save();

        // Log the action
        Log::info("User account created for employee {$employee->employee_number} (ID: {$employee->id}) by user " . auth()->id());

        return $user;
    }
}
