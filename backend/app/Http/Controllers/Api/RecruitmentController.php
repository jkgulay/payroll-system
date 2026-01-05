<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\JobPosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RecruitmentController extends Controller
{
    // Job Postings
    public function getJobPostings(Request $request)
    {
        $query = JobPosting::with(['department', 'location', 'postedBy'])
            ->withCount('applicants');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function storeJobPosting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'location_id' => 'required|exists:locations,id',
            'description' => 'required|string',
            'employment_type' => 'required|in:regular,contractual,part_time',
            'vacancies' => 'required|integer|min:1',
            'posting_date' => 'required|date',
            'status' => 'required|in:draft,active,closed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jobPosting = JobPosting::create([
            ...$request->all(),
            'posted_by' => auth()->id(),
        ]);

        return response()->json($jobPosting->load(['department', 'location']), 201);
    }

    public function updateJobPosting(Request $request, $id)
    {
        $jobPosting = JobPosting::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'position' => 'string|max:255',
            'department_id' => 'exists:departments,id',
            'location_id' => 'exists:locations,id',
            'employment_type' => 'in:regular,contractual,part_time',
            'status' => 'in:draft,active,closed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jobPosting->update($request->all());

        return response()->json($jobPosting->load(['department', 'location']));
    }

    // Applicants
    public function getApplicants(Request $request)
    {
        $query = Applicant::with(['jobPosting', 'reviewedBy', 'approvedBy'])
            ->withCount(['documents', 'interviews']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('job_posting_id')) {
            $query->where('job_posting_id', $request->job_posting_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('applicant_number', 'like', "%{$search}%");
            });
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function storeApplicant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'job_posting_id' => 'nullable|exists:job_postings,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:applicants,email',
            'mobile_number' => 'required|string|max:20',
            'date_of_birth' => 'required|date',
            'position_applied' => 'required|string',
            'application_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generate applicant number
        $lastApplicant = Applicant::latest()->first();
        $number = $lastApplicant ? intval(substr($lastApplicant->applicant_number, 4)) + 1 : 1;
        $applicantNumber = 'APL-' . str_pad($number, 6, '0', STR_PAD_LEFT);

        $applicant = Applicant::create([
            ...$request->all(),
            'applicant_number' => $applicantNumber,
        ]);

        return response()->json($applicant, 201);
    }

    public function updateApplicantStatus(Request $request, $id)
    {
        $applicant = Applicant::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,screening,interview,assessment,approved,rejected,hired',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updateData = ['status' => $request->status];

        if ($request->status === 'approved') {
            $updateData['approved_by'] = auth()->id();
            $updateData['approved_at'] = now();
        }

        if ($request->has('remarks')) {
            $updateData['remarks'] = $request->remarks;
        }

        $applicant->update($updateData);

        return response()->json($applicant);
    }

    public function convertToEmployee(Request $request, $id)
    {
        $applicant = Applicant::findOrFail($id);

        if ($applicant->status !== 'approved') {
            return response()->json(['message' => 'Applicant must be approved first'], 400);
        }

        if ($applicant->hired_as_employee) {
            return response()->json(['message' => 'Applicant already converted to employee'], 400);
        }

        $validator = Validator::make($request->all(), [
            'employee_number' => 'required|string|unique:employees,employee_number',
            'department_id' => 'required|exists:departments,id',
            'location_id' => 'required|exists:locations,id',
            'employment_type' => 'required|in:regular,contractual,part_time',
            'contract_type' => 'required|in:regular,probationary,contractual',
            'activity_status' => 'required|in:active,on_leave,resigned,terminated,retired',
            'salary_type' => 'required|in:daily,monthly,hourly',
            'salary_rate' => 'required|numeric|min:0',
            'date_hired' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $employee = \App\Models\Employee::create([
                'employee_number' => $request->employee_number,
                'first_name' => $applicant->first_name,
                'middle_name' => $applicant->middle_name,
                'last_name' => $applicant->last_name,
                'suffix' => $applicant->suffix,
                'date_of_birth' => $applicant->date_of_birth,
                'gender' => $applicant->gender,
                'civil_status' => $applicant->civil_status,
                'nationality' => $applicant->nationality,
                'email' => $applicant->email,
                'mobile_number' => $applicant->mobile_number,
                'phone_number' => $applicant->phone_number,
                'address_line1' => $applicant->address_line1,
                'address_line2' => $applicant->address_line2,
                'city' => $applicant->city,
                'province' => $applicant->province,
                'postal_code' => $applicant->postal_code,
                'department_id' => $request->department_id,
                'location_id' => $request->location_id,
                'employment_type' => $request->employment_type,
                'contract_type' => $request->contract_type,
                'activity_status' => $request->activity_status,
                'salary_type' => $request->salary_type,
                'salary_rate' => $request->salary_rate,
                'date_hired' => $request->date_hired,
            ]);

            $applicant->update([
                'status' => 'hired',
                'hired_as_employee' => $employee->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Applicant successfully converted to employee',
                'employee' => $employee,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to convert applicant', 'error' => $e->getMessage()], 500);
        }
    }
}
