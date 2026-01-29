<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:hr,employee',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_active' => true,
        ]);

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Login user and create token
     */
    public function login(LoginRequest $request)
    {
        // Validation is handled by LoginRequest

        // Check if input is email or username
        $loginField = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($loginField, $request->email)
            ->where('role', $request->role)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Check if 2FA is enabled
        if ($user->two_factor_confirmed_at) {
            return response()->json([
                'requires_2fa' => true,
                'user_id' => $user->id,
                'message' => 'Please provide your two-factor authentication code',
            ]);
        }

        // Update last login
        $user->last_login_at = now();
        $user->save();

        // Load employee relationship to get full name
        $user->load('employee');

        // Get full name from employee if available
        $fullName = $user->employee ? $user->employee->full_name : $user->name;

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'requires_2fa' => false,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $fullName,
                'full_name' => $fullName,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
                'last_login_at' => $user->last_login_at,
                'avatar' => $user->avatar,
                'employee_id' => $user->employee_id,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Logout user (revoke token)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }
}
