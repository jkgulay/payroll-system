<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeApplication;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmployeeApplicationController extends Controller
{
    /**
     * Display a listing of employee applications.
     */
    public function index(Request $request)
    {
        $query = EmployeeApplication::with(['project', 'creator', 'reviewer']);

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('application_status', $request->status);
        }

        // Filter by project
        if ($request->has('project_id') && $request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        // Search by name or email
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Only show pending applications to admin by default
        if (Auth::user()->role === 'admin' && !$request->has('status')) {
            $query->pending();
        }

        $applications = $query->orderBy('submitted_at', 'desc')->get();

        return response()->json($applications);
    }

    /**
     * Store a newly created employee application.
     */
    public function store(Request $request)
    {
        // Check for pending or approved applications with the same email
        $existingApplication = EmployeeApplication::where('email', $request->email)
            ->whereIn('application_status', ['pending', 'approved'])
            ->first();

        if ($existingApplication) {
            return response()->json([
                'message' => 'An application with this email is already pending or has been approved.',
                'errors' => ['email' => ['This email already has a pending or approved application.']]
            ], 422);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'email' => 'required|email|unique:employees,email',
            'mobile_number' => 'nullable|string|max:20',
            'worker_address' => 'required|string',
            'project_id' => 'required|exists:projects,id',
            'position' => 'required|string|max:255',
            'date_hired' => 'nullable|date',
            'contract_type' => 'required|in:regular,probationary,contractual',
            'activity_status' => 'required|in:active,on_leave,resigned,terminated,retired',
            'employment_type' => 'required|in:regular,contractual,part_time',
            'salary_type' => 'required|in:daily,monthly',
            'basic_salary' => 'required|numeric|min:0',
            'resume' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
            'id_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'contract' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'certificates' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Handle file uploads
        if ($request->hasFile('resume')) {
            $validated['resume_path'] = $request->file('resume')->store('applications/resumes', 'public');
        }
        if ($request->hasFile('id_document')) {
            $validated['id_path'] = $request->file('id_document')->store('applications/ids', 'public');
        }
        if ($request->hasFile('contract')) {
            $validated['contract_path'] = $request->file('contract')->store('applications/contracts', 'public');
        }
        if ($request->hasFile('certificates')) {
            $validated['certificates_path'] = $request->file('certificates')->store('applications/certificates', 'public');
        }

        // Set application metadata
        $validated['application_status'] = 'pending';
        $validated['created_by'] = Auth::id();
        $validated['submitted_at'] = now();

        $application = EmployeeApplication::create($validated);

        // Load relationships
        $application->load(['project', 'creator']);

        return response()->json([
            'message' => 'Employee application submitted successfully',
            'application' => $application
        ], 201);
    }

    /**
     * Display the specified employee application.
     */
    public function show($id)
    {
        $application = EmployeeApplication::with(['project', 'creator', 'reviewer', 'employee'])
            ->findOrFail($id);

        return response()->json($application);
    }

    /**
     * Approve an employee application (Admin only).
     */
    public function approve(Request $request, $id)
    {
        // Ensure only admin can approve
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only admin can approve applications.'], 403);
        }

        $validated = $request->validate([
            'date_hired' => 'required|date',
        ]);

        $application = EmployeeApplication::findOrFail($id);

        if ($application->application_status !== 'pending') {
            return response()->json(['message' => 'Application has already been reviewed.'], 400);
        }

        DB::beginTransaction();
        try {
            // Generate employee number
            $lastEmployee = Employee::orderBy('id', 'desc')->first();
            $nextNumber = $lastEmployee ? ((int) substr($lastEmployee->employee_number, 3)) + 1 : 1;
            $employeeNumber = 'EMP' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // Create employee record
            $employee = Employee::create([
                'employee_number' => $employeeNumber,
                'first_name' => $application->first_name,
                'middle_name' => $application->middle_name,
                'last_name' => $application->last_name,
                'date_of_birth' => $application->date_of_birth,
                'gender' => $application->gender,
                'email' => $application->email,
                'mobile_number' => $application->mobile_number,
                'worker_address' => $application->worker_address,
                'project_id' => $application->project_id,
                'position' => $application->position,
                'date_hired' => $validated['date_hired'],
                'contract_type' => $application->contract_type,
                'activity_status' => $application->activity_status,
                'employment_type' => $application->employment_type,
                'salary_type' => $application->salary_type,
                'basic_salary' => $application->basic_salary,
                'is_active' => true,
            ]);

            // Auto-generate password
            $randomDigits = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
            $autoPassword = $application->last_name . $employeeNumber . '@' . $randomDigits;

            // Create user account
            $user = User::create([
                'username' => $application->email,
                'email' => $application->email,
                'password' => Hash::make($autoPassword),
                'role' => 'employee',
                'is_active' => true,
                'must_change_password' => true,
            ]);

            // Update application status
            $application->update([
                'application_status' => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'employee_id' => $employee->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Employee application approved successfully',
                'employee' => $employee->load('project'),
                'temporary_password' => $autoPassword,
                'role' => 'employee',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to approve application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject an employee application (Admin only).
     */
    public function reject(Request $request, $id)
    {
        // Ensure only admin can reject
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized. Only admin can reject applications.'], 403);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        $application = EmployeeApplication::findOrFail($id);

        if ($application->application_status !== 'pending') {
            return response()->json(['message' => 'Application has already been reviewed.'], 400);
        }

        $application->update([
            'application_status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return response()->json([
            'message' => 'Employee application rejected',
            'application' => $application->load(['project', 'creator', 'reviewer'])
        ]);
    }

    /**
     * Delete an employee application.
     */
    public function destroy($id)
    {
        $application = EmployeeApplication::findOrFail($id);

        // Delete associated files
        if ($application->resume_path) {
            Storage::disk('public')->delete($application->resume_path);
        }
        if ($application->id_path) {
            Storage::disk('public')->delete($application->id_path);
        }
        if ($application->contract_path) {
            Storage::disk('public')->delete($application->contract_path);
        }
        if ($application->certificates_path) {
            Storage::disk('public')->delete($application->certificates_path);
        }

        $application->delete();

        return response()->json(['message' => 'Employee application deleted successfully']);
    }
}
