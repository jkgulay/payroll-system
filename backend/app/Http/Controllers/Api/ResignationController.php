<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resignation;
use App\Models\Employee;
use App\Models\PayrollItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ResignationController extends Controller
{
    /**
     * Display a listing of resignations
     */
    public function index(Request $request)
    {
        $query = Resignation::withRelations();

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('resignation_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('resignation_date', '<=', $request->end_date);
        }

        // Search by employee name
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $resignations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($resignations);
    }

    /**
     * Store a new resignation (Employee submits)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'last_working_day' => 'required|date|after:today',
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if employee already has a pending or approved resignation
        $existingResignation = Resignation::where('employee_id', $request->employee_id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingResignation) {
            return response()->json([
                'message' => 'Employee already has a pending or approved resignation.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $resignation = Resignation::create([
                'employee_id' => $request->employee_id,
                'resignation_date' => now(),
                'last_working_day' => $request->last_working_day,
                'effective_date' => $request->last_working_day, // Default to requested date
                'reason' => $request->reason,
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Resignation submitted successfully.',
                'resignation' => $resignation->load('employee'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to submit resignation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resignation
     */
    public function show($id)
    {
        $resignation = Resignation::withRelations()->findOrFail($id);
        return response()->json($resignation);
    }

    /**
     * Update resignation (HR can modify)
     */
    public function update(Request $request, $id)
    {
        $resignation = Resignation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'effective_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,approved,rejected,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $resignation->update([
                'effective_date' => $request->effective_date ?? $resignation->effective_date,
                'remarks' => $request->remarks ?? $resignation->remarks,
                'status' => $request->status ?? $resignation->status,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Resignation updated successfully.',
                'resignation' => $resignation->load('employee'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update resignation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve resignation (HR)
     */
    public function approve(Request $request, $id)
    {
        $resignation = Resignation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'effective_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($resignation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending resignations can be approved.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $effectiveDate = $request->effective_date ?? $resignation->last_working_day;

            $resignation->update([
                'status' => 'approved',
                'effective_date' => $effectiveDate,
                'remarks' => $request->remarks,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            // Update employee status
            $employee = $resignation->employee;
            $employee->update([
                'resignation_id' => $resignation->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Resignation approved successfully.',
                'resignation' => $resignation->load('employee', 'processedBy'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to approve resignation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject resignation (HR)
     */
    public function reject(Request $request, $id)
    {
        $resignation = Resignation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'remarks' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($resignation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending resignations can be rejected.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $resignation->update([
                'status' => 'rejected',
                'remarks' => $request->remarks,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Resignation rejected.',
                'resignation' => $resignation->load('employee', 'processedBy'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to reject resignation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process final pay and complete resignation
     */
    public function processFinalPay(Request $request, $id)
    {
        $resignation = Resignation::findOrFail($id);

        if ($resignation->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved resignations can be processed for final pay.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $employee = $resignation->employee;
            
            // Calculate 13th month pay
            $thirteenthMonthAmount = $this->calculate13thMonthPay($employee, $resignation->effective_date);
            
            // Calculate unused leave credits (if applicable)
            $unusedLeaveAmount = $this->calculateUnusedLeaveAmount($employee);
            
            // Get any remaining salary/adjustments
            $remainingSalary = $request->remaining_salary ?? 0;
            
            // Calculate total final pay
            $finalPayAmount = $thirteenthMonthAmount + $unusedLeaveAmount + $remainingSalary;

            $resignation->update([
                'thirteenth_month_amount' => $thirteenthMonthAmount,
                'final_pay_amount' => $finalPayAmount,
                'final_pay_released' => false,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Final pay calculated successfully.',
                'resignation' => $resignation->load('employee'),
                'breakdown' => [
                    'thirteenth_month_pay' => $thirteenthMonthAmount,
                    'unused_leave' => $unusedLeaveAmount,
                    'remaining_salary' => $remainingSalary,
                    'total' => $finalPayAmount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to process final pay.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Release final pay
     */
    public function releaseFinalPay($id)
    {
        $resignation = Resignation::findOrFail($id);

        if ($resignation->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved resignations can have final pay released.',
            ], 422);
        }

        if (!$resignation->final_pay_amount) {
            return response()->json([
                'message' => 'Final pay must be calculated before releasing.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $resignation->update([
                'status' => 'completed',
                'final_pay_released' => true,
                'final_pay_release_date' => now(),
            ]);

            // Update employee status to resigned
            $employee = $resignation->employee;
            $employee->update([
                'activity_status' => 'resigned',
                'date_separated' => $resignation->effective_date,
                'separation_reason' => 'resignation',
                'is_active' => false,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Final pay released successfully. Employee status updated to resigned.',
                'resignation' => $resignation->load('employee'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to release final pay.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employee's resignation status
     */
    public function getEmployeeResignation($employeeId)
    {
        $resignation = Resignation::where('employee_id', $employeeId)
            ->withRelations()
            ->first();

        if (!$resignation) {
            return response()->json([
                'message' => 'No resignation found for this employee.',
            ], 404);
        }

        return response()->json($resignation);
    }

    /**
     * Calculate pro-rated 13th month pay
     */
    private function calculate13thMonthPay(Employee $employee, $effectiveDate)
    {
        $currentYear = Carbon::parse($effectiveDate)->year;
        $startDate = $currentYear . '-01-01';
        $endDate = Carbon::parse($effectiveDate)->format('Y-m-d');

        // Get total basic salary for the period
        $totalBasicPay = PayrollItem::whereHas('payroll', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('pay_date', [$startDate, $endDate])
                ->where('status', 'paid');
        })
            ->where('employee_id', $employee->id)
            ->sum('basic_pay');

        // Calculate 13th month pay (basic salary / 12)
        return $totalBasicPay / 12;
    }

    /**
     * Calculate unused leave credits amount
     */
    private function calculateUnusedLeaveAmount(Employee $employee)
    {
        // Get unused leave credits
        $unusedLeaves = $employee->leaveCredits()
            ->where('balance', '>', 0)
            ->sum('balance');

        if ($unusedLeaves <= 0) {
            return 0;
        }

        // Calculate daily rate
        $dailyRate = $employee->getBasicSalary();

        // Convert unused leaves to cash
        return $unusedLeaves * $dailyRate;
    }

    /**
     * Delete resignation (only if pending and before approval)
     */
    public function destroy($id)
    {
        $resignation = Resignation::findOrFail($id);

        if ($resignation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending resignations can be deleted.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $resignation->delete();

            DB::commit();

            return response()->json([
                'message' => 'Resignation deleted successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete resignation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
