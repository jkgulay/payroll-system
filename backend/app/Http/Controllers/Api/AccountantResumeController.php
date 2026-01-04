<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadResumeRequest;
use App\Services\FileSecurityService;
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
    public function upload(UploadResumeRequest $request)
    {
        // Validation is handled by UploadResumeRequest
        $fileSecurityService = new FileSecurityService();
        
        try {
            $file = $request->file('resume');
            
            // Additional security validation
            $securityCheck = $fileSecurityService->validateFile($file);
            if (!$securityCheck['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'File security validation failed',
                    'errors' => $securityCheck['errors']
                ], 422);
            }

            // Optional virus scan (basic check)
            $virusScan = $fileSecurityService->scanForViruses($file);
            if ($virusScan['suspicious']) {
                Log::warning('Suspicious file upload attempt', [
                    'user_id' => auth()->id(),
                    'filename' => $file->getClientOriginalName(),
                    'reason' => $virusScan['reason']
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'File failed security scan: ' . $virusScan['reason'],
                ], 422);
            }

            $originalFilename = $file->getClientOriginalName();
            // Sanitize filename
            $sanitizedFilename = $fileSecurityService->sanitizeFilename($originalFilename);
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

            // Also get resumes from employee applications
            $applicationResumes = \App\Models\EmployeeApplication::query()
                ->whereNotNull('resume_path')
                ->with(['creator:id,username,email', 'reviewer:id,username,email'])
                ->orderBy('submitted_at', 'desc')
                ->get()
                ->map(function ($app) {
                    // Map application status to resume status
                    $status = match ($app->application_status) {
                        'pending' => 'pending',
                        'approved' => 'approved',
                        'rejected' => 'rejected',
                        default => 'pending'
                    };

                    // Get file extension
                    $extension = pathinfo($app->resume_path, PATHINFO_EXTENSION);

                    return (object)[
                        'id' => 'app_' . $app->id, // Prefix to differentiate from regular resumes
                        'user_id' => $app->created_by,
                        'user' => $app->creator,
                        'original_filename' => $app->first_name . ' ' . $app->last_name . ' - Resume.' . $extension,
                        'stored_filename' => basename($app->resume_path),
                        'file_path' => $app->resume_path,
                        'file_url' => url('storage/' . $app->resume_path),
                        'file_type' => $extension,
                        'file_size' => 0, // Size not tracked in applications
                        'status' => $status,
                        'admin_notes' => $app->rejection_reason,
                        'reviewed_by' => $app->reviewed_by,
                        'reviewed_at' => $app->reviewed_at,
                        'reviewer' => $app->reviewer,
                        'created_at' => $app->submitted_at,
                        'updated_at' => $app->updated_at,
                        'is_application' => true, // Flag to identify application resumes
                        'application_id' => $app->id,
                        'first_name' => $app->first_name,
                        'last_name' => $app->last_name,
                        'middle_name' => $app->middle_name
                    ];
                });

            // Filter application resumes by status if requested
            if ($request->has('status') && $request->status !== 'all') {
                $applicationResumes = $applicationResumes->filter(function ($resume) use ($request) {
                    return $resume->status === $request->status;
                });
            }

            // Merge both collections
            $allResumes = $resumes->concat($applicationResumes)->sortByDesc('created_at')->values();

            return response()->json([
                'success' => true,
                'data' => $allResumes
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
        // Check if this is an application resume
        if (str_starts_with($id, 'app_')) {
            return response()->json([
                'success' => false,
                'message' => 'Employee application resumes cannot be approved here. Please use the Pending Applications section in the dashboard to review and approve the full application.'
            ], 400);
        }

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
        // Check if this is an application resume
        if (str_starts_with($id, 'app_')) {
            return response()->json([
                'success' => false,
                'message' => 'Employee application resumes cannot be rejected here. Please use the Pending Applications section in the dashboard to review and reject the full application.'
            ], 400);
        }

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
