<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLoan;
use App\Models\LoanPayment;
use App\Models\Employee;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LoanController extends Controller
{
    public function __construct()
    {
        // Employee can request loans (create)
        // HR can create loans for employees
        // Admin approves/rejects loans
        $this->middleware('role:admin')->only(['approve', 'reject']);
        $this->middleware('role:admin,hr')->only(['update', 'destroy']);
    }

    public function index(Request $request)
    {
        $query = EmployeeLoan::with(['employee', 'requestedBy', 'approvedBy', 'rejectedBy']);

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by loan type
        if ($request->has('loan_type')) {
            $query->where('loan_type', $request->loan_type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter pending loans for admin approval
        if ($request->has('pending_only') && $request->pending_only) {
            $query->where('status', 'pending');
        }

        // If employee, only show their own loans
        if (auth()->user()->role === 'employee') {
            $query->where('employee_id', auth()->user()->employee_id);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'loan_type' => 'required|in:sss,pag_ibig,company,emergency,salary_advance,other',
            'principal_amount' => 'required|numeric|min:100',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'loan_term_months' => 'required|integer|min:1|max:60',
            'payment_frequency' => 'required|in:monthly,semi_monthly',
            'loan_date' => 'required|date',
            'first_payment_date' => 'required|date|after:loan_date',
            'purpose' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Set default interest rate if not provided
        if (!isset($validated['interest_rate'])) {
            $validated['interest_rate'] = $this->getDefaultInterestRate($validated['loan_type']);
        }

        // Calculate total amount with interest
        $interestAmount = ($validated['principal_amount'] * $validated['interest_rate'] / 100);
        $totalAmount = $validated['principal_amount'] + $interestAmount;

        // Calculate amortization based on payment frequency
        $paymentsPerYear = $validated['payment_frequency'] === 'semi_monthly' ? 24 : 12;
        $totalPayments = ($validated['loan_term_months'] / 12) * $paymentsPerYear;

        $amortization = $totalAmount / $totalPayments;

        // Calculate maturity date
        $maturityDate = Carbon::parse($validated['first_payment_date'])
            ->addMonths($validated['loan_term_months']);

        // Generate loan number
        $loanNumber = $this->generateLoanNumber($validated['loan_type']);

        // Determine who is creating the loan
        $user = auth()->user();
        $isEmployeeRequest = $user->role === 'employee';
        $isHrRequest = $user->role === 'hr';
        $isAdminCreate = $user->role === 'admin';

        $loanData = array_merge($validated, [
            'loan_number' => $loanNumber,
            'total_amount' => round($totalAmount, 2),
            'monthly_amortization' => $validated['payment_frequency'] === 'monthly' ? round($amortization, 2) : round($amortization * 2, 2),
            'semi_monthly_amortization' => $validated['payment_frequency'] === 'semi_monthly' ? round($amortization, 2) : 0,
            'balance' => round($totalAmount, 2),
            'amount_paid' => 0,
            'maturity_date' => $maturityDate,
            'status' => $isAdminCreate ? 'active' : 'pending', // Admin-created loans are active
            'requested_by' => $isEmployeeRequest ? $user->id : null,
            'created_by' => ($isHrRequest || $isAdminCreate) ? $user->id : null,
            'approved_by' => $isAdminCreate ? $user->id : null,
            'approved_at' => $isAdminCreate ? now() : null,
        ]);

        DB::beginTransaction();
        try {
            $loan = EmployeeLoan::create($loanData);

            // Load employee relationship for audit log
            $loan->load('employee');

            // Create audit log
            $description = match ($user->role) {
                'employee' => "Employee requested loan: {$loan->loan_number}",
                'hr' => "HR requested loan for employee: {$loan->employee->full_name}",
                'admin' => "Admin created and activated loan for employee: {$loan->employee->full_name}",
                default => "Loan created: {$loan->loan_number}",
            };

            AuditLog::create([
                'module' => 'loans',
                'action' => 'create',
                'description' => $description,
                'user_id' => $user->id,
                'record_id' => $loan->id,
                'old_values' => null,
                'new_values' => json_encode($loanData),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            $message = match ($user->role) {
                'employee' => 'Loan request submitted for approval',
                'hr' => 'Loan request submitted for admin approval',
                'admin' => 'Loan created and activated successfully',
                default => 'Loan created successfully',
            };

            return response()->json([
                'message' => $message,
                'data' => $loan->load(['employee', 'requestedBy', 'createdBy', 'approvedBy'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create loan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(EmployeeLoan $loan)
    {
        // Employees can only view their own loans
        if (auth()->user()->role === 'employee' && $loan->employee_id !== auth()->user()->employee_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($loan->load([
            'employee',
            'requestedBy',
            'createdBy',
            'approvedBy',
            'rejectedBy',
            'payments'
        ]));
    }

    public function update(Request $request, EmployeeLoan $loan)
    {
        // Cannot update approved or active loans
        if (in_array($loan->status, ['active', 'paid'])) {
            return response()->json([
                'message' => 'Cannot update active or paid loans'
            ], 422);
        }

        $validated = $request->validate([
            'loan_type' => 'sometimes|in:sss,pag_ibig,company,emergency,salary_advance,other',
            'principal_amount' => 'sometimes|numeric|min:100',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'loan_term_months' => 'sometimes|integer|min:1|max:60',
            'payment_frequency' => 'sometimes|in:monthly,semi_monthly',
            'first_payment_date' => 'sometimes|date',
            'purpose' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Recalculate if principal, interest, or term changed
        if (isset($validated['principal_amount']) || isset($validated['interest_rate']) || isset($validated['loan_term_months'])) {
            $principal = $validated['principal_amount'] ?? $loan->principal_amount;
            $interestRate = $validated['interest_rate'] ?? $loan->interest_rate;
            $termMonths = $validated['loan_term_months'] ?? $loan->loan_term_months;
            $paymentFreq = $validated['payment_frequency'] ?? $loan->payment_frequency;

            $interestAmount = ($principal * $interestRate / 100);
            $totalAmount = $principal + $interestAmount;

            $paymentsPerYear = $paymentFreq === 'semi_monthly' ? 24 : 12;
            $totalPayments = ($termMonths / 12) * $paymentsPerYear;
            $amortization = $totalAmount / $totalPayments;

            $validated['total_amount'] = round($totalAmount, 2);
            $validated['monthly_amortization'] = $paymentFreq === 'monthly' ? round($amortization, 2) : round($amortization * 2, 2);
            $validated['semi_monthly_amortization'] = $paymentFreq === 'semi_monthly' ? round($amortization, 2) : 0;
            $validated['balance'] = round($totalAmount, 2);
        }

        DB::beginTransaction();
        try {
            $oldValues = $loan->toArray();
            $loan->update($validated);

            // Create audit log
            AuditLog::create([
                'module' => 'loans',
                'action' => 'update',
                'description' => "Loan updated: {$loan->loan_number}",
                'user_id' => auth()->id(),
                'record_id' => $loan->id,
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode($validated),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Loan updated successfully',
                'data' => $loan->load(['employee', 'requestedBy', 'createdBy', 'approvedBy'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update loan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(EmployeeLoan $loan)
    {
        // Cannot delete approved or active loans
        if (in_array($loan->status, ['active', 'paid'])) {
            return response()->json([
                'message' => 'Cannot delete active or paid loans. Please cancel instead.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $loanNumber = $loan->loan_number;

            // Create audit log before deletion
            AuditLog::create([
                'module' => 'loans',
                'action' => 'delete',
                'description' => "Loan deleted: {$loanNumber}",
                'user_id' => auth()->id(),
                'record_id' => $loan->id,
                'old_values' => json_encode($loan->toArray()),
                'new_values' => null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $loan->delete();

            DB::commit();

            return response()->json(['message' => 'Loan deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete loan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function approve(Request $request, EmployeeLoan $loan)
    {
        if ($loan->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending loans can be approved'
            ], 422);
        }

        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $loan->update([
                'status' => 'active', // Changed from 'approved' to 'active'
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'approval_notes' => $validated['approval_notes'] ?? null,
            ]);

            // Create audit log
            AuditLog::create([
                'module' => 'loans',
                'action' => 'approve',
                'description' => "Loan approved and activated: {$loan->loan_number}",
                'user_id' => auth()->id(),
                'record_id' => $loan->id,
                'old_values' => json_encode(['status' => 'pending']),
                'new_values' => json_encode(['status' => 'active', 'approval_notes' => $validated['approval_notes'] ?? null]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Loan approved and activated successfully',
                'data' => $loan->load(['employee', 'requestedBy', 'approvedBy'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to approve loan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reject(Request $request, EmployeeLoan $loan)
    {
        if ($loan->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending loans can be rejected'
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $loan->update([
                'status' => 'rejected',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            // Create audit log
            AuditLog::create([
                'module' => 'loans',
                'action' => 'reject',
                'description' => "Loan rejected: {$loan->loan_number}",
                'user_id' => auth()->id(),
                'record_id' => $loan->id,
                'old_values' => json_encode(['status' => 'pending']),
                'new_values' => json_encode(['status' => 'rejected', 'rejection_reason' => $validated['rejection_reason']]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Loan rejected',
                'data' => $loan->load(['employee', 'requestedBy', 'rejectedBy'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to reject loan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recordPayment(Request $request, EmployeeLoan $loan)
    {
        if ($loan->status !== 'active') {
            return response()->json([
                'message' => 'Only active loans can accept payments'
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validated['amount'] > $loan->balance) {
            return response()->json([
                'message' => 'Payment amount cannot exceed loan balance'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create payment record
            $payment = LoanPayment::create([
                'employee_loan_id' => $loan->id,
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'] ?? 'payroll_deduction',
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            // Update loan balance
            $newBalance = $loan->balance - $validated['amount'];
            $newAmountPaid = $loan->amount_paid + $validated['amount'];

            $updateData = [
                'amount_paid' => $newAmountPaid,
                'balance' => $newBalance,
            ];

            // Mark as paid if balance is zero
            if ($newBalance <= 0) {
                $updateData['status'] = 'paid';
            }

            $loan->update($updateData);

            // Create audit log
            AuditLog::create([
                'module' => 'loans',
                'action' => 'payment',
                'description' => "Payment recorded for loan {$loan->loan_number}: ₱" . number_format($validated['amount'], 2),
                'user_id' => auth()->id(),
                'record_id' => $loan->id,
                'old_values' => json_encode(['balance' => $loan->balance, 'amount_paid' => $loan->amount_paid]),
                'new_values' => json_encode(['balance' => $newBalance, 'amount_paid' => $newAmountPaid]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment recorded successfully',
                'data' => $loan->load(['employee', 'payments'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to record payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper methods
     */
    protected function getDefaultInterestRate(string $loanType): float
    {
        $rates = [
            'sss' => 10.0,
            'pag_ibig' => 5.5,
            'company' => 8.0,
            'emergency' => 5.0,
            'salary_advance' => 0.0,
            'other' => 6.0,
        ];

        return $rates[$loanType] ?? 0.0;
    }

    protected function generateLoanNumber(string $loanType): string
    {
        $prefix = strtoupper(substr($loanType, 0, 3));
        $year = date('Y');
        $month = date('m');

        // Get the count of loans for this type this month
        $count = EmployeeLoan::where('loan_type', $loanType)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count() + 1;

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $count);
    }
}
