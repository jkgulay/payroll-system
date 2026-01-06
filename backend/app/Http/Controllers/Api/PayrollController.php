<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayrollService;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;

        // Apply authorization middleware to sensitive actions
        $this->middleware('payroll.permission:process')->only(['process']);
        $this->middleware('payroll.permission:check')->only(['check']);
        $this->middleware('payroll.permission:recommend')->only(['recommend']);
        $this->middleware('payroll.permission:approve')->only(['approve']);
        $this->middleware('payroll.permission:mark_paid')->only(['markPaid']);
    }

    /**
     * Get all payroll periods
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payroll::with(['preparedBy', 'checkedBy', 'approvedBy'])
            ->orderBy('period_start', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        $payrolls = $query->paginate(20);

        return response()->json($payrolls);
    }

    /**
     * Create new payroll period
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_start_date' => 'required|date',
            'period_end_date' => 'required|date|after:period_start_date',
            'payment_date' => 'required|date',
            'pay_period_number' => 'nullable|integer|in:1,2',
            // Support for targeted payroll generation
            'project_id' => 'nullable|exists:projects,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'exists:employees,id',
            'contract_type' => 'nullable|in:regular,probationary,contractual',
            'position_id' => 'nullable|exists:position_rates,id',
        ]);

        // Check for overlapping payroll periods
        $overlapping = Payroll::where(function ($query) use ($validated) {
            $query->whereBetween('period_start', [$validated['period_start_date'], $validated['period_end_date']])
                ->orWhereBetween('period_end', [$validated['period_start_date'], $validated['period_end_date']])
                ->orWhere(function ($q) use ($validated) {
                    $q->where('period_start', '<=', $validated['period_start_date'])
                        ->where('period_end', '>=', $validated['period_end_date']);
                });
        })->exists();

        if ($overlapping) {
            return response()->json([
                'error' => 'A payroll period already exists for this date range. Please check existing payroll periods.'
            ], 422);
        }

        try {
            $payroll = $this->payrollService->createPayroll($validated);
            return response()->json($payroll, 201);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Payroll creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);

            // Check for duplicate payroll period
            if (str_contains($e->getMessage(), 'payroll_year_month_pay_period_number_unique')) {
                return response()->json([
                    'error' => 'A payroll period already exists for this month and pay period number. Please check existing payrolls or choose a different period.',
                    'message' => 'Duplicate payroll period'
                ], 422);
            }

            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Failed to create payroll period'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Payroll creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return response()->json([
                'error' => $e->getMessage(),
                'message' => 'Failed to create payroll period'
            ], 500);
        }
    }

    /**
     * Get payroll details with items
     */
    public function show(Payroll $payroll): JsonResponse
    {
        $payroll->load([
            'payrollItems.employee.department',
            'payrollItems.employee.location',
            'payrollItems.details',
            'preparedBy',
            'checkedBy',
            'recommendedBy',
            'approvedBy',
            'paidBy',
        ]);

        return response()->json($payroll);
    }

    /**
     * Process payroll (calculate all employee payrolls)
     */
    public function process(Payroll $payroll, Request $request): JsonResponse
    {
        if (!$payroll->canEdit()) {
            return response()->json(['error' => 'Payroll cannot be edited in current status'], 422);
        }

        $employeeIds = $request->input('employee_ids', null);
        $filters = $request->only(['project_id', 'contract_type', 'position_id']);

        try {
            $this->payrollService->processPayroll($payroll, $employeeIds, $filters);
            $payroll->refresh();

            return response()->json([
                'message' => 'Payroll processed successfully',
                'payroll' => $payroll,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Check payroll (first approval)
     */
    public function check(Payroll $payroll): JsonResponse
    {
        if (!$payroll->canCheck()) {
            return response()->json(['error' => 'Payroll cannot be checked in current status'], 422);
        }

        $success = $this->payrollService->checkPayroll($payroll, auth()->id());

        if ($success) {
            return response()->json([
                'message' => 'Payroll checked successfully',
                'payroll' => $payroll->fresh(),
            ]);
        }

        return response()->json(['error' => 'Failed to check payroll'], 500);
    }

    /**
     * Recommend payroll (second approval)
     */
    public function recommend(Payroll $payroll): JsonResponse
    {
        if (!$payroll->canRecommend()) {
            return response()->json(['error' => 'Payroll cannot be recommended in current status'], 422);
        }

        $success = $this->payrollService->recommendPayroll($payroll, auth()->id());

        if ($success) {
            return response()->json([
                'message' => 'Payroll recommended successfully',
                'payroll' => $payroll->fresh(),
            ]);
        }

        return response()->json(['error' => 'Failed to recommend payroll'], 500);
    }

    /**
     * Approve payroll (final approval)
     */
    public function approve(Payroll $payroll): JsonResponse
    {
        if (!$payroll->canApprove()) {
            return response()->json(['error' => 'Payroll cannot be approved in current status'], 422);
        }

        $success = $this->payrollService->approvePayroll($payroll, auth()->id());

        if ($success) {
            return response()->json([
                'message' => 'Payroll approved successfully',
                'payroll' => $payroll->fresh(),
            ]);
        }

        return response()->json(['error' => 'Failed to approve payroll'], 500);
    }

    /**
     * Mark payroll as paid
     */
    public function markPaid(Payroll $payroll): JsonResponse
    {
        if (!$payroll->canMarkAsPaid()) {
            return response()->json(['error' => 'Payroll cannot be marked as paid in current status'], 422);
        }

        $success = $this->payrollService->markAsPaid($payroll, auth()->id());

        if ($success) {
            return response()->json([
                'message' => 'Payroll marked as paid successfully',
                'payroll' => $payroll->fresh(),
            ]);
        }

        return response()->json(['error' => 'Failed to mark payroll as paid'], 500);
    }

    /**
     * Get payroll summary/statistics
     */
    public function summary(Payroll $payroll): JsonResponse
    {
        $summary = [
            'payroll' => $payroll,
            'employee_count' => $payroll->getEmployeeCount(),
            'total_gross_pay' => $payroll->total_gross_pay,
            'total_deductions' => $payroll->total_deductions,
            'total_net_pay' => $payroll->total_net_pay,
            'status' => $payroll->status,
            'workflow' => [
                'prepared' => ['by' => $payroll->preparedBy?->name, 'at' => $payroll->prepared_at],
                'checked' => ['by' => $payroll->checkedBy?->name, 'at' => $payroll->checked_at],
                'recommended' => ['by' => $payroll->recommendedBy?->name, 'at' => $payroll->recommended_at],
                'approved' => ['by' => $payroll->approvedBy?->name, 'at' => $payroll->approved_at],
                'paid' => ['by' => $payroll->paidBy?->name, 'at' => $payroll->paid_at],
            ],
        ];

        return response()->json($summary);
    }

    /**
     * Delete/cancel payroll
     */
    public function destroy(Payroll $payroll): JsonResponse
    {
        if (!$payroll->canEdit()) {
            return response()->json(['error' => 'Payroll cannot be deleted in current status'], 422);
        }

        $payroll->update(['status' => 'cancelled']);
        $payroll->delete();

        return response()->json(['message' => 'Payroll cancelled successfully']);
    }

    /**
     * Export payroll report as PDF with signatures
     */
    public function exportPdf(Request $request, Payroll $payroll)
    {
        $includeSignatures = $request->input('include_signatures', true);

        $payroll->load(['payslips.employee.department']);

        $pdf = Pdf::loadView('payroll.report', [
            'payroll' => $payroll,
            'payslips' => $payroll->payslips,
            'includeSignatures' => $includeSignatures,
        ]);

        return $pdf->download('payroll_report_' . $payroll->id . '.pdf');
    }

    /**
     * Export payroll report as Excel with signatures
     */
    public function exportExcel(Request $request, Payroll $payroll)
    {
        $includeSignatures = $request->input('include_signatures', true);

        return Excel::download(
            new \App\Exports\PayrollReportExport($payroll, $includeSignatures),
            'payroll_report_' . $payroll->id . '.xlsx'
        );
    }
}
