<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceModificationRequest;
use Illuminate\Http\Request;

class AttendanceModificationRequestController extends Controller
{
    private function resolveModule(Request $request): string
    {
        return $request->input('module', $request->query('module', 'attendance'));
    }

    /**
     * List requests - admins see all, payrollists see their own
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $module = $this->resolveModule($request);
        $query = AttendanceModificationRequest::with(['requester', 'reviewer'])
            ->forModule($module);

        if (in_array($user->role, ['admin', 'hr'])) {
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
        } else {
            $query->where('requested_by', $user->id);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Create a new access request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'module' => 'required|string|in:attendance,deductions',
            'date' => 'nullable|date',
            'reason' => 'required|string|max:500',
        ]);

        $user = $request->user();
        $module = $validated['module'];

        if (in_array($user->role, ['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Admin and HR users have direct access and do not need to request approval.',
            ], 422);
        }

        // Build the uniqueness check
        $existingQuery = AttendanceModificationRequest::where('requested_by', $user->id)
            ->where('module', $module)
            ->whereIn('status', ['pending', 'approved']);

        if (!empty($validated['date'])) {
            $existingQuery->where('date', $validated['date']);
        } else {
            $existingQuery->whereNull('date');
        }

        $existing = $existingQuery->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => $existing->status === 'pending'
                    ? 'You already have a pending request.'
                    : 'You already have an approved request.',
            ], 422);
        }

        $modRequest = AttendanceModificationRequest::create([
            'module' => $module,
            'requested_by' => $user->id,
            'date' => $validated['date'] ?? null,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        $modRequest->load('requester');

        return response()->json([
            'success' => true,
            'message' => 'Access request submitted successfully.',
            'data' => $modRequest,
        ], 201);
    }

    /**
     * Check if user has access for a specific module (and optionally date)
     */
    public function checkAccess(Request $request)
    {
        $request->validate([
            'module' => 'required|string|in:attendance,deductions',
            'date' => 'nullable|date',
        ]);

        $user = $request->user();
        $module = $request->query('module', 'attendance');

        if (in_array($user->role, ['admin', 'hr'])) {
            return response()->json([
                'has_access' => true,
                'status' => 'admin',
            ]);
        }

        $query = AttendanceModificationRequest::where('requested_by', $user->id)
            ->where('module', $module)
            ->orderBy('created_at', 'desc');

        if ($request->has('date') && $request->date) {
            $query->where('date', $request->date);
        } else {
            $query->whereNull('date');
        }

        $modRequest = $query->first();

        if (!$modRequest) {
            $moduleLabel = $module === 'deductions' ? 'deductions' : 'attendance for this date';
            return response()->json([
                'has_access' => false,
                'status' => 'none',
                'message' => "No request found. You must request access to manage {$moduleLabel}.",
            ]);
        }

        return response()->json([
            'has_access' => $modRequest->status === 'approved',
            'status' => $modRequest->status,
            'request' => $modRequest,
            'message' => match ($modRequest->status) {
                'pending' => 'Your request is pending admin approval.',
                'approved' => 'Access granted.',
                'rejected' => 'Your request was rejected. ' . ($modRequest->review_notes ?? ''),
            },
        ]);
    }

    /**
     * Approve a request (admin/hr only)
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
     * Reject a request (admin/hr only)
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

        $module = $this->resolveModule($request);
        $count = AttendanceModificationRequest::pending()->forModule($module)->count();

        return response()->json(['count' => $count]);
    }
}
