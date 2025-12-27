<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountantResume;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AccountantResumeController extends Controller
{
    /**
     * Upload a new resume (Accountant)
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'resume' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('resume');
            $originalFilename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileType = $extension;
            $fileSize = $file->getSize();
            
            // Generate unique filename
            $storedFilename = Str::uuid() . '.' . $extension;
            
            // Store file in storage/app/public/resumes
            $filePath = $file->storeAs('resumes', $storedFilename, 'public');

            // Create resume record
            $resume = AccountantResume::create([
                'user_id' => auth()->id(),
                'original_filename' => $originalFilename,
                'stored_filename' => $storedFilename,
                'file_path' => $filePath,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'status' => 'pending',
            ]);

            // Log the action
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'resume_uploaded',
                    'description' => "Uploaded resume: {$originalFilename}",
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $logError) {
                // Log error but don't fail the upload
                Log::warning('Failed to create audit log: ' . $logError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Resume uploaded successfully and sent for admin approval',
                'data' => $resume
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload resume',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get accountant's own resumes
     */
    public function myResumes()
    {
        try {
            $resumes = AccountantResume::where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->get();

            // Add reviewer info if available
            $resumes->load('reviewer:id,username,email');

            return response()->json([
                'success' => true,
                'data' => $resumes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch resumes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approved resumes for the accountant
     */
    public function approvedResumes()
    {
        try {
            $resumes = AccountantResume::where('user_id', auth()->id())
                ->approved()
                ->orderBy('reviewed_at', 'desc')
                ->get();

            $resumes->load('reviewer:id,username,email');

            return response()->json([
                'success' => true,
                'data' => $resumes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch approved resumes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete own resume (only if pending)
     */
    public function destroy($id)
    {
        try {
            $resume = AccountantResume::where('user_id', auth()->id())
                ->where('id', $id)
                ->firstOrFail();

            if ($resume->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a reviewed resume'
                ], 403);
            }

            // Delete file from storage
            if (Storage::disk('public')->exists($resume->file_path)) {
                Storage::disk('public')->delete($resume->file_path);
            }

            $originalFilename = $resume->original_filename;
            $resume->delete();

            // Log the action
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'resume_deleted',
                    'description' => "Deleted resume: {$originalFilename}",
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to create audit log: ' . $logError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Resume deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete resume',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all pending resumes (Admin only)
     */
    public function pendingResumes()
    {
        try {
            $resumes = AccountantResume::pending()
                ->orderBy('created_at', 'desc')
                ->get();

            $resumes->load('user:id,username,email');

            return response()->json([
                'success' => true,
                'data' => $resumes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending resumes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all resumes with filters (Admin only)
     */
    public function allResumes(Request $request)
    {
        try {
            $query = AccountantResume::query();

            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Filter by user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $resumes = $query->orderBy('created_at', 'desc')->get();
            $resumes->load('user:id,username,email', 'reviewer:id,username,email');

            return response()->json([
                'success' => true,
                'data' => $resumes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch resumes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a resume (Admin only)
     */
    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $resume = AccountantResume::findOrFail($id);

            if ($resume->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Resume has already been reviewed'
                ], 400);
            }

            $resume->update([
                'status' => 'approved',
                'admin_notes' => $request->admin_notes,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            // Log the action
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'resume_approved',
                    'description' => "Approved resume for user ID: {$resume->user_id}",
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to create audit log: ' . $logError->getMessage());
            }

            $resume->load('user:id,username,email', 'reviewer:id,username,email');

            return response()->json([
                'success' => true,
                'message' => 'Resume approved successfully',
                'data' => $resume
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve resume',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a resume (Admin only)
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'admin_notes' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $resume = AccountantResume::findOrFail($id);

            if ($resume->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Resume has already been reviewed'
                ], 400);
            }

            $resume->update([
                'status' => 'rejected',
                'admin_notes' => $request->admin_notes,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            // Log the action
            try {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'resume_rejected',
                    'description' => "Rejected resume for user ID: {$resume->user_id}",
                    'ip_address' => $request->ip(),
                ]);
            } catch (\Exception $logError) {
                Log::warning('Failed to create audit log: ' . $logError->getMessage());
            }

            $resume->load('user:id,username,email', 'reviewer:id,username,email');

            return response()->json([
                'success' => true,
                'message' => 'Resume rejected',
                'data' => $resume
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject resume',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download resume file
     */
    public function download($id)
    {
        try {
            $resume = AccountantResume::findOrFail($id);

            // Check permissions
            if (auth()->user()->role !== 'admin' && $resume->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            if (!Storage::disk('public')->exists($resume->file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $filePath = storage_path('app/public/' . $resume->file_path);
            return response()->download($filePath, $resume->original_filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to download file',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
