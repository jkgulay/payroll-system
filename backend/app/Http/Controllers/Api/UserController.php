<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        $query = User::with('employee:id,first_name,last_name,middle_name,employee_number');

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->has('role') && !empty($request->role)) {
            $query->where('role', $request->role);
        }

        // Status filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate or get all
        if ($request->has('per_page') && $request->per_page !== 'all') {
            $users = $query->paginate($request->per_page ?? 15);
        } else {
            $users = $query->get();
        }

        return response()->json($users);
    }

    /**
     * Display the specified user
     */
    public function show($id)
    {
        $user = User::with('employee')->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'nullable|exists:employees,id',
            'username' => 'required|string|max:255|unique:users,username',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'accountant', 'payrollist', 'employee'])],
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'employee_id' => $request->employee_id,
                'username' => $request->username,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'is_active' => $request->is_active ?? true,
                'must_change_password' => true,
            ]);

            // Log user creation
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'user_management',
                'action' => 'create_user',
                'description' => "Created new user: {$user->username} ({$user->role})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'new_values' => [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role,
                ],
            ]);

            return response()->json([
                'message' => 'User created successfully',
                'user' => $user->load('employee')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'nullable|exists:employees,id',
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'accountant', 'payrollist', 'employee'])],
            'is_active' => 'boolean',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $oldValues = $user->only(['username', 'name', 'email', 'role', 'is_active']);

            $updateData = [
                'employee_id' => $request->employee_id,
                'username' => $request->username,
                'name' => $request->name,
                'email' => $request->email,
                'role' => $request->role,
                'is_active' => $request->is_active,
            ];

            // Only update password if provided
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
                $updateData['must_change_password'] = true;
            }

            $user->update($updateData);

            // Log user update
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'user_management',
                'action' => 'update_user',
                'description' => "Updated user: {$user->username}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_values' => $oldValues,
                'new_values' => $user->only(['username', 'name', 'email', 'role', 'is_active']),
            ]);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user->load('employee')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting own account
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        try {
            $username = $user->username;
            $role = $user->role;

            $user->delete();

            // Log user deletion
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'user_management',
                'action' => 'delete_user',
                'description' => "Deleted user: {$username} ({$role})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_values' => [
                    'user_id' => $id,
                    'username' => $username,
                    'role' => $role,
                ],
            ]);

            return response()->json([
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent deactivating own account
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot deactivate your own account'
            ], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'activated' : 'deactivated';

        // Log status change
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'user_management',
            'action' => 'toggle_user_status',
            'description' => "User {$user->username} {$status}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => [
                'is_active' => $user->is_active,
            ],
        ]);

        return response()->json([
            'message' => "User {$status} successfully",
            'user' => $user
        ]);
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => true,
        ]);

        // Log password reset
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'user_management',
            'action' => 'reset_password',
            'description' => "Reset password for user: {$user->username}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Password reset successfully'
        ]);
    }

    /**
     * Get available employees for user creation
     */
    public function getAvailableEmployees()
    {
        $employees = Employee::whereDoesntHave('user')
            ->where('activity_status', 'active')
            ->select('id', 'employee_number', 'first_name', 'last_name', 'middle_name')
            ->orderBy('employee_number')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                ];
            });

        return response()->json($employees);
    }

    /**
     * Get user statistics
     */
    public function getStats()
    {
        $stats = [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'by_role' => [
                'admin' => User::where('role', 'admin')->count(),
                'accountant' => User::where('role', 'accountant')->count(),
                'payrollist' => User::where('role', 'payrollist')->count(),
                'employee' => User::where('role', 'employee')->count(),
            ],
        ];

        return response()->json($stats);
    }
}
