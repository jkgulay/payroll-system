<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDeduction;
use App\Models\Employee;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeductionController extends Controller
{
    public function __construct()
    {
        // Admin and accountant can manage deductions
        $this->middleware('role:admin,accountant')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $query = EmployeeDeduction::with(['employee', 'createdBy', 'approvedBy']);

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by deduction type
        if ($request->has('deduction_type')) {
            $query->where('deduction_type', $request->deduction_type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->has('category')) {
            if ($request->category === 'government') {
                $query->government();
            } elseif ($request->category === 'company') {
                $query->company();
            }
        }

        // If employee, only show their own deductions
        if (auth()->user()->role === 'employee') {
            $query->where('employee_id', auth()->user()->employee_id);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'deduction_type' => 'required|in:ppe,tools,uniform,absence,sss,philhealth,pagibig,tax,loan,other',
            'deduction_name' => 'nullable|string|max:100',
            'total_amount' => 'required|numeric|min:1',
            'amount_per_cutoff' => 'required|numeric|min:1',
            'installments' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'description' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Auto-generate deduction_name from deduction_type if not provided
        if (empty($validated['deduction_name'])) {
            $validated['deduction_name'] = ucwords(str_replace('_', ' ', $validated['deduction_type'])) . ' Deduction';
        }

        // Calculate installments if not provided
        if (!isset($validated['installments']) && isset($validated['end_date'])) {
            $start = Carbon::parse($validated['start_date']);
            $end = Carbon::parse($validated['end_date']);
            $months = $start->diffInMonths($end);
            $validated['installments'] = $months * 2; // Semi-monthly (2 cutoffs per month)
        } elseif (!isset($validated['installments'])) {
            $validated['installments'] = ceil($validated['total_amount'] / $validated['amount_per_cutoff']);
        }

        // Calculate end date if not provided
        if (!isset($validated['end_date'])) {
            $installmentsInMonths = ceil($validated['installments'] / 2);
            $validated['end_date'] = Carbon::parse($validated['start_date'])
                ->addMonths($installmentsInMonths)
                ->toDateString();
        }

        $deductionData = array_merge($validated, [
            'balance' => $validated['total_amount'],
            'installments_paid' => 0,
            'status' => 'active',
            'created_by' => auth()->id(),
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        DB::beginTransaction();
        try {
            $deduction = EmployeeDeduction::create($deductionData);

            // Load employee relationship for audit log
            $deduction->load('employee');

            // Create audit log
            AuditLog::create([
                'module' => 'deductions',
                'action' => 'create',
                'description' => "Deduction created for employee: {$deduction->employee->full_name} - {$deduction->deduction_name}",
                'user_id' => auth()->id(),
                'record_id' => $deduction->id,
                'old_values' => null,
                'new_values' => json_encode($deductionData),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Deduction created successfully',
                'data' => $deduction->load(['employee', 'createdBy', 'approvedBy'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create deduction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(EmployeeDeduction $deduction)
    {
        // Employees can only view their own deductions
        if (auth()->user()->role === 'employee' && $deduction->employee_id !== auth()->user()->employee_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($deduction->load([
            'employee',
            'createdBy',
            'approvedBy'
        ]));
    }

    public function update(Request $request, EmployeeDeduction $deduction)
    {
        // Cannot update completed deductions
        if ($deduction->status === 'completed') {
            return response()->json([
                'message' => 'Cannot update completed deductions'
            ], 422);
        }

        $validated = $request->validate([
            'deduction_type' => 'sometimes|in:ppe,tools,uniform,absence,sss,philhealth,pagibig,tax,loan,other',
            'deduction_name' => 'nullable|string|max:100',
            'total_amount' => 'sometimes|numeric|min:1',
            'amount_per_cutoff' => 'sometimes|numeric|min:1',
            'installments' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after:start_date',
            'description' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:active,completed,cancelled',
        ]);

        // Recalculate balance if total amount changed
        if (isset($validated['total_amount'])) {
            $amountPaid = $deduction->total_amount - $deduction->balance;
            $validated['balance'] = max(0, $validated['total_amount'] - $amountPaid);
        }

        DB::beginTransaction();
        try {
            $oldValues = $deduction->toArray();
            $deduction->update($validated);

            // Create audit log
            AuditLog::create([
                'module' => 'deductions',
                'action' => 'update',
                'description' => "Deduction updated: {$deduction->deduction_name}",
                'user_id' => auth()->id(),
                'record_id' => $deduction->id,
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode($validated),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Deduction updated successfully',
                'data' => $deduction->load(['employee', 'createdBy', 'approvedBy'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update deduction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(EmployeeDeduction $deduction)
    {
        // Cannot delete active deductions with balance
        if ($deduction->status === 'active' && $deduction->balance > 0 && $deduction->installments_paid > 0) {
            return response()->json([
                'message' => 'Cannot delete active deductions with payments made. Please cancel instead.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $deductionName = $deduction->deduction_name;

            // Create audit log before deletion
            AuditLog::create([
                'module' => 'deductions',
                'action' => 'delete',
                'description' => "Deduction deleted: {$deductionName}",
                'user_id' => auth()->id(),
                'record_id' => $deduction->id,
                'old_values' => json_encode($deduction->toArray()),
                'new_values' => null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $deduction->delete();

            DB::commit();

            return response()->json(['message' => 'Deduction deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete deduction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Record an installment payment (called automatically by payroll)
     */
    public function recordInstallment(Request $request, EmployeeDeduction $deduction)
    {
        if ($deduction->status !== 'active') {
            return response()->json([
                'message' => 'Only active deductions can accept payments'
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
        ]);

        if ($validated['amount'] > $deduction->balance) {
            return response()->json([
                'message' => 'Payment amount cannot exceed deduction balance'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $newBalance = $deduction->balance - $validated['amount'];
            $newInstallmentsPaid = $deduction->installments_paid + 1;

            $updateData = [
                'installments_paid' => $newInstallmentsPaid,
                'balance' => $newBalance,
            ];

            // Mark as completed if balance is zero or all installments paid
            if ($newBalance <= 0 || $newInstallmentsPaid >= $deduction->installments) {
                $updateData['status'] = 'completed';
            }

            $deduction->update($updateData);

            // Create audit log
            AuditLog::create([
                'module' => 'deductions',
                'action' => 'installment',
                'description' => "Installment payment for {$deduction->deduction_name}: ₱" . number_format($validated['amount'], 2),
                'user_id' => auth()->id(),
                'record_id' => $deduction->id,
                'old_values' => json_encode(['balance' => $deduction->balance, 'installments_paid' => $deduction->installments_paid]),
                'new_values' => json_encode(['balance' => $newBalance, 'installments_paid' => $newInstallmentsPaid]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Installment payment recorded successfully',
                'data' => $deduction->fresh()->load('employee')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to record installment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
