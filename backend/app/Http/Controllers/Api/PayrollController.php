<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayrollService;
use App\Models\Payroll;
use App\Exports\PayrollPDFExport;
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
            ->withCount('payrollItems')
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

        // Calculate year, month, and period number for duplicate check
        $periodStart = \Carbon\Carbon::parse($validated['period_start_date']);
        $year = $periodStart->year;
        $month = $periodStart->month;

        // Determine pay period number (same logic as service)
        $payPeriodNumber = $validated['pay_period_number'] ??
            ($periodStart->day <= 15 ? 1 : 2);

        // Check for duplicate year/month/period combination
        $duplicate = Payroll::where('year', $year)
            ->where('month', $month)
            ->where('pay_period_number', $payPeriodNumber)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => "A payroll period already exists for " . $periodStart->format('F Y') . " - Period $payPeriodNumber. Please check existing payroll records.",
                'error' => 'Duplicate payroll period'
            ], 422);
        }

        // Check for overlapping payroll periods
        $overlapping = Payroll::where(function ($query) use ($validated) {
            $query->whereBetween('period_start', [$validated['period_start_date'], $validated['period_end_date']])
                ->orWhereBetween('period_end', [$validated['period_start_date'], $validated['period_end_date']])
                ->orWhere(function ($q) use ($validated) {
                    $q->where('period_start', '<=', $validated['period_start_date'])
                        ->where('period_end', '>=', $validated['period_end_date']);
                });
        });

        // Apply same filters for overlap check if targeted payroll
        if (!empty($validated['project_id'])) {
            $overlapping->where('project_id', $validated['project_id']);
        }
        if (!empty($validated['contract_type'])) {
            $overlapping->where('contract_type', $validated['contract_type']);
        }
        if (!empty($validated['position_id'])) {
            $overlapping->where('position_id', $validated['position_id']);
        }

        if ($overlapping->exists()) {
            return response()->json([
                'message' => 'A payroll period already exists for this date range. Please check existing payroll periods.',
                'error' => 'Overlapping payroll period'
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
                    'message' => 'A payroll period already exists for this month and pay period number. Please check existing payrolls or choose a different period.',
                    'error' => 'Duplicate payroll period'
                ], 422);
            }

            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'Database error',
                'details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        } catch (\Exception $e) {
            Log::error('Payroll creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $validated
            ]);
            return response()->json([
                'message' => $e->getMessage() ?: 'Failed to create payroll period',
                'error' => 'Creation failed',
                'details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Get payroll details with items
     */
    public function show(Payroll $payroll): JsonResponse
    {
        $payroll->load([
            'payrollItems.employee.project',
            'payrollItems.employee.positionRate',
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
        $regenerate = $request->input('regenerate', false);

        try {
            // If regenerating, delete existing payroll items first
            if ($regenerate || $payroll->payrollItems()->count() > 0) {
                // Delete existing payroll item details first
                foreach ($payroll->payrollItems as $item) {
                    $item->details()->delete();
                }
                // Then delete payroll items
                $payroll->payrollItems()->delete();

                // Reset status to draft
                $payroll->update(['status' => 'draft']);
            }

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
     * Reset payroll to draft status (delete all items but keep the period)
     */
    public function reset(Payroll $payroll): JsonResponse
    {
        try {
            // Delete existing payroll item details first
            foreach ($payroll->payrollItems as $item) {
                $item->details()->delete();
            }
            // Then delete payroll items
            $payroll->payrollItems()->delete();

            // Reset all totals and status
            $payroll->update([
                'status' => 'draft',
                'total_gross_pay' => 0,
                'total_deductions' => 0,
                'total_net_pay' => 0,
                'checked_by' => null,
                'checked_at' => null,
                'recommended_by' => null,
                'recommended_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'paid_by' => null,
                'paid_at' => null,
            ]);

            return response()->json([
                'message' => 'Payroll reset to draft successfully. You can now reprocess it.',
                'payroll' => $payroll->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to reset payroll: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete/cancel payroll
     */
    public function destroy(Payroll $payroll): JsonResponse
    {
        try {
            // Delete payroll item details first
            foreach ($payroll->payrollItems as $item) {
                $item->details()->delete();
            }

            // Delete all related payroll items
            $payroll->payrollItems()->forceDelete();

            // Permanently delete the payroll
            $payroll->forceDelete();

            return response()->json(['message' => 'Payroll deleted permanently']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete payroll: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export payroll report as PDF with signatures
     */
    public function exportPdf(Request $request, Payroll $payroll)
    {
        $includeSignatures = $request->input('include_signatures', true);

        $payroll->load(['payrollItems.employee.project']);

        $pdf = Pdf::loadView('payroll.report', [
            'payroll' => $payroll,
            'payslips' => $payroll->payrollItems,
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

    /**
     * Export comprehensive payroll PDF with filtering and signatures
     */
    public function exportComprehensivePDF(Request $request, Payroll $payroll)
    {
        try {
            // Log the request
            Log::info('PDF Export Request', [
                'payroll_id' => $payroll->id,
                'request_data' => $request->all()
            ]);

            $validated = $request->validate([
                'type' => 'required|in:all,employee,project',
                'filter_id' => 'nullable|integer',
                'signatures' => 'required|array',
                'signatures.prepared_by' => 'nullable|array',
                'signatures.checked_by' => 'nullable|array',
                'signatures.recommended_by' => 'nullable|array',
                'signatures.approved_by' => 'nullable|array',
            ]);

            // Ensure storage directories exist and are writable
            $fontsPath = storage_path('fonts');
            $cachePath = storage_path('framework/cache');

            if (!file_exists($fontsPath)) {
                mkdir($fontsPath, 0755, true);
            }
            if (!file_exists($cachePath)) {
                mkdir($cachePath, 0755, true);
            }

            // Check if directories are writable
            if (!is_writable($fontsPath)) {
                Log::error('Fonts directory not writable: ' . $fontsPath);
            }
            if (!is_writable($cachePath)) {
                Log::error('Cache directory not writable: ' . $cachePath);
            }

            Log::info('Creating PDF exporter');
            $exporter = new PayrollPDFExport($payroll);

            $signatureData = $validated['signatures'] ?? [];

            Log::info('Generating PDF', ['type' => $validated['type']]);
            $pdf = match ($validated['type']) {
                'employee' => $exporter->generateByEmployee($validated['filter_id'], $signatureData),
                'project' => $exporter->generateByProject($validated['filter_id'], $signatureData),
                default => $exporter->generate($signatureData),
            };

            $filename = $this->generateFilename($payroll, $validated['type'], $validated);

            Log::info('PDF generated successfully', ['filename' => $filename]);

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('PDF Export Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'payroll_id' => $payroll->id ?? null
            ]);

            return response()->json([
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Test PDF setup and environment
     */
    public function testPdfSetup(Request $request)
    {
        $checks = [];

        // Check PHP extensions
        $checks['php_version'] = PHP_VERSION;
        $checks['extensions'] = [
            'gd' => extension_loaded('gd'),
            'mbstring' => extension_loaded('mbstring'),
            'dom' => extension_loaded('dom'),
            'xml' => extension_loaded('xml'),
        ];

        // Check directories
        $checks['directories'] = [
            'fonts' => [
                'path' => storage_path('fonts'),
                'exists' => file_exists(storage_path('fonts')),
                'writable' => is_writable(storage_path('fonts')),
            ],
            'cache' => [
                'path' => storage_path('framework/cache'),
                'exists' => file_exists(storage_path('framework/cache')),
                'writable' => is_writable(storage_path('framework/cache')),
            ],
            'logs' => [
                'path' => storage_path('logs'),
                'exists' => file_exists(storage_path('logs')),
                'writable' => is_writable(storage_path('logs')),
            ],
        ];

        // Check dompdf config
        $checks['dompdf_config'] = [
            'font_dir' => config('dompdf.options.font_dir'),
            'font_cache' => config('dompdf.options.font_cache'),
            'temp_dir' => config('dompdf.options.temp_dir'),
        ];

        // Try to create a simple PDF
        try {
            $pdf = Pdf::loadHTML('<h1>Test PDF</h1>');
            $checks['simple_pdf_test'] = 'SUCCESS';
        } catch (\Exception $e) {
            $checks['simple_pdf_test'] = 'FAILED: ' . $e->getMessage();
        }

        return response()->json($checks);
    }

    /**
     * Generate appropriate filename based on export type
     */
    private function generateFilename(Payroll $payroll, string $type, array $validated): string
    {
        $base = 'payroll_' . $payroll->id;

        switch ($type) {
            case 'employee':
                if (isset($validated['filter_id'])) {
                    $employee = \App\Models\Employee::find($validated['filter_id']);
                    $base .= '_' . ($employee ? $employee->employee_number : 'employee');
                }
                break;
            case 'project':
                if (isset($validated['filter_id'])) {
                    $project = \App\Models\Project::find($validated['filter_id']);
                    $base .= '_' . ($project ? str_replace(' ', '_', $project->name) : 'project');
                }
                break;
        }

        return $base . '.pdf';
    }
}
