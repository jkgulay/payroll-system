<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeGovernmentInfo;
use App\Models\User;
use App\Models\AuditLog;
use App\Helpers\DateHelper;
use App\Helpers\EmployeeFieldMapper;
use App\Validators\EmployeeValidator;
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

        // Filter by department
        if ($request->has('department') && $request->department) {
            $query->where('department', $request->department);
        }

        // Filter by contract type
        if ($request->has('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }

        // Filter by activity status (supports single value, array, or comma-separated)
        if ($request->has('activity_status')) {
            $activityStatus = $request->activity_status;
            if (is_array($activityStatus)) {
                $query->whereIn('activity_status', $activityStatus);
            } elseif (str_contains($activityStatus, ',')) {
                $statuses = array_map('trim', explode(',', $activityStatus));
                $query->whereIn('activity_status', $statuses);
            } else {
                $query->where('activity_status', $activityStatus);
            }
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
        $requestedRole = $request->validate([
            'role' => 'nullable|in:admin,hr,employee,payrollist',
        ])['role'] ?? null;

        // Normalize employee data using helper
        $validated = EmployeeFieldMapper::normalizeEmployeeData($validated);

        // Determine role from position or use requested role
        $role = $requestedRole ?? EmployeeFieldMapper::determineRoleFromPosition($validated, 'employee');

        // Remove temporary helper data
        unset($validated['_position_rate']);

        DB::beginTransaction();
        try {
            // Generate employee number (EMP001, EMP002, etc.)
            $lastEmployee = Employee::orderBy('id', 'desc')->first();
            $nextNumber = $lastEmployee ? ((int) substr($lastEmployee->employee_number, 3)) + 1 : 1;
            $validated['employee_number'] = 'EMP' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // Create employee
            $employee = Employee::create($validated);

            // Generate credentials using helper
            $username = EmployeeFieldMapper::generateUsername($validated);
            $autoPassword = EmployeeFieldMapper::generateTemporaryPassword(
                $validated['last_name'],
                $validated['employee_number']
            );
            $email = $validated['email'] ?? null;

            // Always create user account
            $user = User::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($autoPassword),
                'role' => $role,
                'is_active' => true,
                'must_change_password' => true, // Force password change on first login
                'employee_id' => $employee->id, // Link user to employee
            ]);

            // Link employee to user (for backwards compatibility with code that uses employee->user_id)
            $employee->user_id = $user->id;
            $employee->save();

            // Sync government IDs to employee_government_info (keeps both tables consistent)
            $govIds = array_filter([
                'sss_number'        => $validated['sss_number'] ?? null,
                'philhealth_number' => $validated['philhealth_number'] ?? null,
                'pagibig_number'    => $validated['pagibig_number'] ?? null,
                'tin_number'        => $validated['tin_number'] ?? null,
            ], fn($v) => !is_null($v));
            if (!empty($govIds)) {
                EmployeeGovernmentInfo::updateOrCreate(
                    ['employee_id' => $employee->id],
                    $govIds
                );
            }

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

        // Normalize employee data (handles position mapping, gender normalization, etc.)
        $validated = EmployeeFieldMapper::normalizeEmployeeData($validated);

        // Remove temporary helper data
        unset($validated['_position_rate']);

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

                // Auto-update user role based on position using the same logic as employee creation
                if ($employee->user_id) {
                    $newRole = EmployeeFieldMapper::determineRoleFromPosition([
                        '_position_rate' => $newPosition
                    ], 'employee');

                    \App\Models\User::where('id', $employee->user_id)->update(['role' => $newRole]);
                }

                // Sync basic_salary to match the new position's daily_rate (prevents stale salary data)
                // Only when no custom override is active on this employee
                if (empty($employee->custom_pay_rate) && empty($validated['custom_pay_rate'])) {
                    $validated['basic_salary'] = $newPosition->daily_rate;
                }
            }
        }

        $employee->update($validated);

        // Sync any changed government IDs to employee_government_info
        $govIdFields = ['sss_number', 'philhealth_number', 'pagibig_number', 'tin_number'];
        $govIds = array_filter(
            array_intersect_key($validated, array_flip($govIdFields)),
            fn($v) => !is_null($v)
        );
        if (!empty($govIds)) {
            EmployeeGovernmentInfo::updateOrCreate(
                ['employee_id' => $employee->id],
                $govIds
            );
        }

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
            'has_account' => true,
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

        // Generate new temporary password using helper
        $newPassword = EmployeeFieldMapper::generateTemporaryPassword(
            $employee->last_name,
            $employee->employee_number
        );

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

        // Determine role based on position using helper
        $data = ['position_id' => $employee->position_id];
        if ($employee->position_id) {
            $data['_position_rate'] = \App\Models\PositionRate::find($employee->position_id);
        }
        $role = EmployeeFieldMapper::determineRoleFromPosition($data, 'employee');

        // Validate employee has required fields
        EmployeeValidator::validateForUserCreation($employee);

        // Generate credentials using helper
        $username = EmployeeFieldMapper::generateUsername([
            'email' => $employee->email,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
        ]);
        $temporaryPassword = EmployeeFieldMapper::generateTemporaryPassword(
            $employee->last_name,
            $employee->employee_number
        );

        // Create user account
        $user = User::create([
            'username' => $username,
            'email' => $employee->email ?? $username . '@company.com',
            'password' => Hash::make($temporaryPassword),
            'role' => $role,
            'is_active' => true,
            'must_change_password' => true,
            'employee_id' => $employee->id,
        ]);

        // Store temporary password on user object (not saved to DB)
        $user->temporary_password = $temporaryPassword;

        // Link user to employee (bidirectional)
        $employee->user_id = $user->id;
        $employee->save();

        // Log the action
        Log::info("User account created for employee {$employee->employee_number} (ID: {$employee->id}) by user " . auth()->id());

        return $user;
    }

    /**
     * Update employee's custom pay rate
     * Allows admin to set a custom rate that overrides position-based rate
     */
    public function updatePayRate(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'custom_pay_rate' => 'required|numeric|min:0|max:999999.99',
            'reason' => 'nullable|string|max:500', // Optional reason for audit trail
        ]);

        $oldRate = $employee->getBasicSalary();
        $newRate = $validated['custom_pay_rate'];

        // Update the custom pay rate
        $employee->custom_pay_rate = $newRate;
        $employee->save();

        // Log the change for audit
        $description = "Pay rate updated from ₱" . number_format((float)$oldRate, 2) . " to ₱" . number_format((float)$newRate, 2) .
            " for employee {$employee->employee_number} ({$employee->full_name})";
        if (!empty($validated['reason'])) {
            $description .= ". Reason: {$validated['reason']}";
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'employees',
            'action' => 'pay_rate_update',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => ['custom_pay_rate' => $oldRate, 'employee_id' => $employee->id],
            'new_values' => ['custom_pay_rate' => $newRate, 'employee_id' => $employee->id],
        ]);

        Log::info("Pay rate updated for employee {$employee->employee_number} from {$oldRate} to {$newRate} by user " . auth()->id());

        return response()->json([
            'message' => 'Pay rate updated successfully',
            'employee' => $employee->load(['project', 'positionRate']),
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
        ]);
    }

    /**
     * Clear employee's custom pay rate (revert to position-based rate)
     */
    public function clearCustomPayRate(Request $request, Employee $employee)
    {
        if ($employee->custom_pay_rate === null) {
            return response()->json([
                'message' => 'Employee does not have a custom pay rate set',
            ], 422);
        }

        $oldRate = $employee->custom_pay_rate;
        $employee->custom_pay_rate = null;
        $employee->save();

        // Get the rate that will now be used (position rate or basic_salary)
        $newRate = $employee->getBasicSalary();

        // Log the change for audit
        $description = "Custom pay rate cleared. Rate changed from ₱" . number_format((float)$oldRate, 2) . " to ₱" . number_format((float)$newRate, 2) .
            " (position-based rate) for employee {$employee->employee_number} ({$employee->full_name})";
        $reason = $request->input('reason');
        if (!empty($reason)) {
            $description .= ". Reason: {$reason}";
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'employees',
            'action' => 'pay_rate_clear',
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => ['custom_pay_rate' => $oldRate, 'employee_id' => $employee->id],
            'new_values' => ['custom_pay_rate' => null, 'position_rate' => $newRate, 'employee_id' => $employee->id],
        ]);

        Log::info("Custom pay rate cleared for employee {$employee->employee_number} by user " . auth()->id());

        return response()->json([
            'message' => 'Custom pay rate cleared successfully. Reverted to position-based rate.',
            'employee' => $employee->load(['project', 'positionRate']),
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
        ]);
    }

    /**
     * Get list of unique departments
     */
    public function getDepartments()
    {
        try {
            $departments = Employee::whereNotNull('department')
                ->where('department', '!=', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department');

            return response()->json($departments);
        } catch (\Exception $e) {
            Log::error('Error fetching departments: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch departments'], 500);
        }
    }
}
