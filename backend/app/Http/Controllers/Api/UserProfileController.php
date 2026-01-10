<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{
    /**
     * Get current user profile
     */
    public function getProfile()
    {
        try {
            $user = auth()->user();

            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:50|unique:users,username,' . auth()->id(),
            'email' => 'sometimes|email|unique:users,email,' . auth()->id(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            /** @var User $user */
            $user = auth()->user();

            if ($request->has('name')) {
                $user->name = $request->name;
            }

            if ($request->has('username')) {
                $user->username = $request->username;
            }

            if ($request->has('email')) {
                $user->email = $request->email;
            }

            $user->save();

            // Log the action
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'user_profile',
                    'action' => 'profile_updated',
                    'description' => 'Updated profile information',
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to create audit log: ' . $logError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            /** @var User $user */
            $user = auth()->user();

            // Check if current password is correct
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Update password
            $user->password = Hash::make($request->new_password);

            // Clear the must_change_password flag
            $user->must_change_password = false;

            $user->save();

            // Log the action
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'user_profile',
                    'action' => 'password_changed',
                    'description' => 'Changed password',
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to create audit log: ' . $logError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload user avatar
     */
    public function uploadAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            /** @var User $user */
            $user = auth()->user();

            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
            $user->save();

            // Log the action
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'user_profile',
                    'action' => 'avatar_uploaded',
                    'description' => 'Uploaded profile picture',
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to create audit log: ' . $logError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove user avatar
     */
    public function removeAvatar(Request $request)
    {
        try {
            /** @var User $user */
            $user = auth()->user();

            // Delete avatar file if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $user->avatar = null;
            $user->save();

            // Log the action
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'user_profile',
                    'action' => 'avatar_removed',
                    'description' => 'Removed profile picture',
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to create audit log: ' . $logError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile picture removed successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove profile picture',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
