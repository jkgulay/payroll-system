<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceModificationRequest;
use Illuminate\Http\Request;

class AttendanceModificationRequestController extends Controller
{
    /**
     * List requests - admins see all, payrollists see their own
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = AttendanceModificationRequest::with(['requester', 'reviewer']);

        if (in_array($user->role, ['admin', 'hr'])) {
            // Admin/HR can see all requests, optionally filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
        } else {
            // Payrollists only see their own requests
            $query->where('requested_by', $user->id);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Create a new modification request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);

        $user = $request->user();

        // Admin/HR don't need requests - they have direct access
        if (in_array($user->role, ['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Admin and HR users have direct access and do not need to request approval.',
            ], 422);
        }

        // Check if there's already a pending/approved request for this date by this user
        $existing = AttendanceModificationRequest::where('requested_by', $user->id)
            ->where('date', $validated['date'])
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => $existing->status === 'pending'
                    ? 'You already have a pending request for this date.'
                    : 'You already have an approved request for this date.',
            ], 422);
        }

        $modRequest = AttendanceModificationRequest::create([
            'requested_by' => $user->id,
            'date' => $validated['date'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        $modRequest->load('requester');

        return response()->json([
            'success' => true,
            'message' => 'Modification request submitted successfully.',
            'data' => $modRequest,
        ], 201);
    }

    /**
     * Check if user has access to modify attendance for a specific date
     */
    public function checkAccess(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $user = $request->user();

        // Admin/HR always have access
        if (in_array($user->role, ['admin', 'hr'])) {
            return response()->json([
                'has_access' => true,
                'status' => 'admin',
            ]);
        }

        $modRequest = AttendanceModificationRequest::where('requested_by', $user->id)
            ->where('date', $validated['date'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$modRequest) {
            return response()->json([
                'has_access' => false,
                'status' => 'none',
                'message' => 'No request found. You must request access to modify attendance for this date.',
            ]);
        }

        return response()->json([
            'has_access' => $modRequest->status === 'approved',
            'status' => $modRequest->status,
            'request' => $modRequest,
            'message' => match ($modRequest->status) {
                'pending' => 'Your request is pending admin approval.',
                'approved' => 'Access granted. You can modify attendance records.',
                'rejected' => 'Your request was rejected. ' . ($modRequest->review_notes ?? ''),
            },
        ]);
    }

    /**
     * Approve a modification request (admin/hr only)
     */
    public function approve(Request $request, AttendanceModificationRequest $modificationRequest)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($modificationRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been ' . $modificationRequest->status . '.',
            ], 422);
        }

        $modificationRequest->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $request->input('notes'),
        ]);

        $modificationRequest->load(['requester', 'reviewer']);

        return response()->json([
            'success' => true,
            'message' => 'Request approved successfully.',
            'data' => $modificationRequest,
        ]);
    }

    /**
     * Reject a modification request (admin/hr only)
     */
    public function reject(Request $request, AttendanceModificationRequest $modificationRequest)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($modificationRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been ' . $modificationRequest->status . '.',
            ], 422);
        }

        $validated = $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        $modificationRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['notes'],
        ]);

        $modificationRequest->load(['requester', 'reviewer']);

        return response()->json([
            'success' => true,
            'message' => 'Request rejected.',
            'data' => $modificationRequest,
        ]);
    }

    /**
     * Get count of pending requests (for admin badge)
     */
    public function pendingCount(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'hr'])) {
            return response()->json(['count' => 0]);
        }

        $count = AttendanceModificationRequest::pending()->count();

        return response()->json(['count' => $count]);
    }
}
