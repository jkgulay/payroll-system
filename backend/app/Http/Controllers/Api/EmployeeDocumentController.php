<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmployeeDocument;
use App\Models\EmployeeGovernmentInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmployeeDocumentController extends Controller
{
    /**
     * Get all documents for an employee
     */
    public function index(Request $request, $employeeId)
    {
        try {
            $documents = EmployeeDocument::where('employee_id', $employeeId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'documents' => $documents,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a new document
     */
    public function store(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'document_type' => 'required|string|max:50',
            'document_name' => 'required|string|max:255',
            'document_file' => 'required|file|max:10240', // 10MB max
            'notes' => 'nullable|string',
        ]);

        try {
            $file = $request->file('document_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('documents/employees/' . $employeeId, $fileName, 'public');

            $document = EmployeeDocument::create([
                'employee_id' => $employeeId,
                'document_type' => $validated['document_type'],
                'document_name' => $validated['document_name'],
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'uploaded_by' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'create',
                'model' => 'EmployeeDocument',
                'model_id' => $document->id,
                'description' => "Uploaded document: {$validated['document_name']} for employee ID {$employeeId}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Document uploaded successfully',
                'document' => $document,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a document
     */
    public function download($documentId)
    {
        try {
            $document = EmployeeDocument::findOrFail($documentId);

            if (!Storage::disk('public')->exists($document->file_path)) {
                return response()->json([
                    'message' => 'File not found'
                ], 404);
            }

            return Storage::disk('public')->download($document->file_path, $document->document_name);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to download document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a document
     */
    public function destroy(Request $request, $documentId)
    {
        try {
            $document = EmployeeDocument::findOrFail($documentId);

            // Delete file from storage
            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete',
                'model' => 'EmployeeDocument',
                'model_id' => $document->id,
                'description' => "Deleted document: {$document->document_name} for employee ID {$document->employee_id}",
                'ip_address' => $request->ip(),
            ]);

            $document->delete();

            return response()->json([
                'message' => 'Document deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get government information for an employee
     */
    public function getGovernmentInfo($employeeId)
    {
        try {
            $govInfo = EmployeeGovernmentInfo::where('employee_id', $employeeId)->first();

            return response()->json([
                'government_info' => $govInfo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve government information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update government information
     */
    public function updateGovernmentInfo(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'sss_number' => 'nullable|string|max:20',
            'philhealth_number' => 'nullable|string|max:20',
            'pagibig_number' => 'nullable|string|max:20',
            'tin_number' => 'nullable|string|max:20',
            'tax_status' => 'nullable|string|max:10',
            'withholding_tax' => 'nullable|numeric',
        ]);

        try {
            $govInfo = EmployeeGovernmentInfo::updateOrCreate(
                ['employee_id' => $employeeId],
                array_merge($validated, ['updated_by' => Auth::id()])
            );

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'update',
                'model' => 'EmployeeGovernmentInfo',
                'model_id' => $govInfo->id,
                'description' => "Updated government information for employee ID {$employeeId}",
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Government information updated successfully',
                'government_info' => $govInfo,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update government information',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
