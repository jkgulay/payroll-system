<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLoan;
use App\Models\LoanPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeLoan::with('employee');

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'loan_type' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0',
            'monthly_amortization' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'terms_months' => 'required|integer|min:1',
        ]);

        $loan = EmployeeLoan::create([
            ...$validated,
            'remaining_balance' => $validated['amount'],
            'status' => 'active',
        ]);

        return response()->json($loan->load('employee'), 201);
    }

    public function show(EmployeeLoan $loan)
    {
        return response()->json($loan->load(['employee', 'payments']));
    }

    public function update(Request $request, EmployeeLoan $loan)
    {
        $validated = $request->validate([
            'monthly_amortization' => 'numeric|min:0',
            'status' => 'in:active,completed,cancelled',
        ]);

        $loan->update($validated);

        return response()->json($loan);
    }

    public function destroy(EmployeeLoan $loan)
    {
        $loan->delete();

        return response()->json(['message' => 'Loan deleted successfully']);
    }

    public function recordPayment(Request $request, EmployeeLoan $loan)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
        ]);

        DB::beginTransaction();

        try {
            $payment = LoanPayment::create([
                'employee_loan_id' => $loan->id,
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'balance_after_payment' => $loan->remaining_balance - $validated['amount'],
            ]);

            $newBalance = $loan->remaining_balance - $validated['amount'];
            $loan->update([
                'remaining_balance' => $newBalance,
                'status' => $newBalance <= 0 ? 'completed' : 'active',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment recorded successfully',
                'payment' => $payment,
                'loan' => $loan,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to record payment', 'error' => $e->getMessage()], 500);
        }
    }
}
