<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayrollService;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    /**
     * Get all payroll periods
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payroll::with(['preparedBy', 'checkedBy', 'approvedBy'])
            ->orderBy('period_start_date', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('year')) {
            $query->whereYear('period_start_date', $request->year);
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
        ]);

        try {
            $payroll = $this->payrollService->createPayroll($validated);
            return response()->json($payroll, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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

        try {
            $this->payrollService->processPayroll($payroll, $employeeIds);
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
