<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeLoan;
use App\Models\AuditLog;
use App\Models\EmployeeDeduction;
use App\Models\User;
use App\Exports\PayrollExport;
use App\Exports\PayrollWordExport;
use App\Services\PayrollService;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }
    public function index()
    {
        $payrolls = Payroll::with(['creator', 'finalizer'])
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($payrolls);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_name' => 'required|string|max:255',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
            // Only employees with attendance
            'has_attendance' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $payroll = new Payroll([
                'period_name' => $validated['period_name'],
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'payment_date' => $validated['payment_date'],
                'status' => 'draft',
                'created_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Explicitly save and ensure the ID is generated before proceeding
            $payroll->save();

            // Verify the payroll was saved and has an ID
            if (!$payroll->id) {
                throw new \Exception('Failed to generate payroll ID');
            }

            // Generate payroll items for all active employees
            $filters = [
                'has_attendance' => $validated['has_attendance'] ?? false,
            ];
            $this->generatePayrollItems($payroll, $filters);

            DB::commit();

            // Reload with count
            $payroll->loadCount('items');

            // Log payroll creation
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'payroll',
                'action' => 'create_payroll',
                'description' => "Payroll '{$payroll->period_name}' created with {$payroll->items_count} employee(s)",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'new_values' => [
                    'payroll_id' => $payroll->id,
                    'period_name' => $payroll->period_name,
                    'period_start' => $payroll->period_start,
                    'period_end' => $payroll->period_end,
                    'items_count' => $payroll->items_count,
                ],
            ]);

            return response()->json([
                'message' => 'Payroll created successfully',
                'payroll' => $payroll->load(['items.employee', 'creator']),
                'items_count' => $payroll->items_count,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating payroll: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $payroll = Payroll::with(['items.employee.position', 'creator', 'finalizer'])
            ->findOrFail($id);

        return response()->json($payroll);
    }

    public function update(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);

        if ($payroll->status !== 'draft') {
            return response()->json([
                'message' => 'Cannot modify finalized or paid payroll'
            ], 422);
        }

        $validated = $request->validate([
            'period_name' => 'sometimes|string|max:255',
            'period_start' => 'sometimes|date',
            'period_end' => 'sometimes|date|after_or_equal:period_start',
            'payment_date' => 'sometimes|date',
            'notes' => 'nullable|string',
        ]);

        $oldValues = $payroll->toArray();
        $payroll->update($validated);

        // Log payroll update
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'payroll',
            'action' => 'update_payroll',
            'description' => "Payroll '{$payroll->period_name}' updated",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $payroll->fresh()->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payroll updated successfully',
            'data' => $payroll->load(['items.employee', 'creator'])
        ]);
    }

    public function destroy(Payroll $payroll)
    {
        $payrollData = $payroll->toArray();

        // Allow deletion of any payroll status (draft, finalized, paid)
        $payroll->delete();

        // Log payroll deletion
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'payroll',
            'action' => 'delete_payroll',
            'description' => "Payroll '{$payrollData['period_name']}' (ID: {$payrollData['id']}) deleted",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $payrollData,
        ]);

        return response()->json([
            'message' => 'Payroll deleted successfully'
        ]);
    }

    public function finalize($id)
    {
        $payroll = Payroll::findOrFail($id);

        if ($payroll->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft payrolls can be finalized'
            ], 422);
        }

        $payroll->update([
            'status' => 'finalized',
            'finalized_by' => auth()->id(),
            'finalized_at' => now(),
        ]);

        // Log payroll finalization
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'payroll',
            'action' => 'finalize_payroll',
            'description' => "Payroll '{$payroll->period_name}' finalized",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'new_values' => [
                'payroll_id' => $payroll->id,
                'status' => 'finalized',
                'finalized_at' => now()->toDateTimeString(),
            ],
        ]);

        return response()->json([
            'message' => 'Payroll finalized successfully',
            'payroll' => $payroll->load(['items.employee', 'creator', 'finalizer'])
        ]);
    }

    public function reprocess($id)
    {
        $payroll = Payroll::findOrFail($id);

        if ($payroll->status === 'paid') {
            return response()->json([
                'message' => 'Cannot reprocess paid payrolls'
            ], 422);
        }

        try {
            $payrollService = new \App\Services\PayrollService();
            $payrollService->reprocessPayroll($payroll);

            // Log payroll reprocessing
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'payroll',
                'action' => 'reprocess_payroll',
                'description' => "Payroll '{$payroll->period_name}' reprocessed",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'new_values' => [
                    'payroll_id' => $payroll->id,
                    'items_count' => $payroll->items()->count(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payroll reprocessed successfully',
                'data' => $payroll->fresh()->load(['items.employee', 'creator'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reprocess payroll: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadPayslip($payrollId, $employeeId)
    {
        // Validate parameters
        $payrollId = (int) $payrollId;
        $employeeId = (int) $employeeId;

        $payroll = Payroll::findOrFail($payrollId);

        $item = PayrollItem::where('payroll_id', $payrollId)
            ->where('employee_id', $employeeId)
            ->with('employee.positionRate')
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Payroll item not found'], 404);
        }

        $pdf = Pdf::loadView('payroll.payslip', [
            'payroll' => $payroll,
            'item' => $item,
            'employee' => $item->employee,
        ]);

        return $pdf->download("payslip_{$item->employee->employee_number}_{$payroll->period_name}.pdf");
    }

    public function downloadRegister(Request $request, Payroll $payroll)
    {
        // Increase memory limit for PDF generation
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');

        // Validate filter parameters
        $validated = $request->validate([
            'filter_type' => 'nullable|in:all,department,staff_type',
            'departments' => 'nullable|array',
            'departments.*' => 'string',
            'staff_types' => 'nullable|array',
            'staff_types.*' => 'string',
            'format' => 'nullable|in:pdf,excel,word',
        ]);

        // Default to PDF if format not specified
        $format = $validated['format'] ?? 'pdf';

        // Load payroll items with employee relationship
        $itemsQuery = $payroll->items()->with('employee.positionRate');

        // Apply filters if provided
        if (!empty($validated['filter_type']) && $validated['filter_type'] !== 'all') {
            if ($validated['filter_type'] === 'department' && !empty($validated['departments'])) {
                $itemsQuery->whereHas('employee', function ($q) use ($validated) {
                    $q->whereIn('department', $validated['departments']);
                });
            } elseif ($validated['filter_type'] === 'staff_type' && !empty($validated['staff_types'])) {
                $itemsQuery->whereHas('employee.positionRate', function ($q) use ($validated) {
                    $q->whereIn('position_name', $validated['staff_types']);
                });
            }
        }

        // Get filtered items
        $payroll->setRelation('items', $itemsQuery->get());

        // Build filename base
        $filenameBase = "payroll_register_{$payroll->payroll_number}";
        $filterInfo = null;
        
        if (!empty($validated['filter_type']) && $validated['filter_type'] !== 'all') {
            if ($validated['filter_type'] === 'department' && !empty($validated['departments'])) {
                if (count($validated['departments']) == 1) {
                    $filterInfo = 'Department: ' . implode(', ', $validated['departments']);
                }
            } elseif ($validated['filter_type'] === 'staff_type' && !empty($validated['staff_types'])) {
                if (count($validated['staff_types']) == 1) {
                    $filterInfo = 'Staff Type: ' . implode(', ', $validated['staff_types']);
                }
            }
            if ($filterInfo) {
                $filenameBase .= '_filtered';
            }
        }

        // Handle different export formats
        if ($format === 'excel') {
            return $this->exportRegisterToExcel($payroll, $filenameBase);
        } elseif ($format === 'word') {
            return $this->exportRegisterToWord($payroll, $filenameBase);
        } else {
            // Default PDF export
            return $this->exportRegisterToPdf($payroll, $validated, $filenameBase);
        }
    }

    private function exportRegisterToPdf(Payroll $payroll, array $validated, string $filenameBase)
    {
        // Group items by department or staff type if multiple filters selected
        $groupedItems = null;
        $filterInfo = null;
        $filterType = $validated['filter_type'] ?? 'all';

        if (!empty($validated['filter_type']) && $validated['filter_type'] !== 'all') {
            if ($validated['filter_type'] === 'department' && !empty($validated['departments'])) {
                // Group by department if multiple departments selected
                if (count($validated['departments']) > 1) {
                    $groupedItems = $payroll->items->groupBy(function ($item) {
                        return $item->employee->department ?? 'N/A';
                    });
                } else {
                    $filterInfo = 'Department: ' . implode(', ', $validated['departments']);
                }
            } elseif ($validated['filter_type'] === 'staff_type' && !empty($validated['staff_types'])) {
                // Group by staff type if multiple staff types selected
                if (count($validated['staff_types']) > 1) {
                    $groupedItems = $payroll->items->groupBy(function ($item) {
                        return $item->employee->positionRate->position_name ?? 'N/A';
                    });
                } else {
                    $filterInfo = 'Staff Type: ' . implode(', ', $validated['staff_types']);
                }
            }
        }

        $pdf = Pdf::loadView('payroll.register', compact('payroll', 'filterInfo', 'groupedItems', 'filterType'));
        return $pdf->download($filenameBase . '.pdf');
    }

    private function exportRegisterToExcel(Payroll $payroll, string $filenameBase)
    {
        return Excel::download(new PayrollExport($payroll), $filenameBase . '.xlsx');
    }

    private function exportRegisterToWord(Payroll $payroll, string $filenameBase)
    {
        $export = new PayrollWordExport($payroll);
        $phpWord = $export->generate();
        
        $filename = $filenameBase . '.docx';
        $tempFile = tempnam(sys_get_temp_dir(), 'payroll_');
        
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);
        
        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function exportToExcel(Payroll $payroll)
    {
        $payroll->load(['items.employee.positionRate']);

        $filename = "payroll_{$payroll->period_name}_" . now()->format('YmdHis') . ".xlsx";

        return Excel::download(new PayrollExport($payroll), $filename);
    }

    private function generatePayrollItems(Payroll $payroll, array $filters = [])
    {
        if (config('app.debug')) {
            Log::debug('Payroll Generation', [
                'filters' => $filters,
                'has_attendance' => $filters['has_attendance'] ?? 'not set'
            ]);
        }

        // Include employees who were working during the payroll period
        // This includes: active employees, on_leave employees, or anyone with attendance during the period
        $query = Employee::where(function ($q) use ($payroll) {
            $q->whereIn('activity_status', ['active', 'on_leave'])
                ->orWhereHas('attendances', function ($subQ) use ($payroll) {
                    $subQ->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
                        ->where('status', '!=', 'absent');
                });
        });

        // Filter by attendance if requested
        if (!empty($filters['has_attendance'])) {
            $query->whereHas('attendances', function ($q) use ($payroll) {
                $q->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
                    ->where('status', '!=', 'absent');
            });
        }

        // Order by employee number for consistent selection
        $query->orderBy('employee_number');

        $employees = $query->get();

        if ($employees->isEmpty()) {
            $errorMsg = 'No employees found for this payroll period';

            // Provide more specific error message
            if (!empty($filters['has_attendance'])) {
                $errorMsg .= '. Note: The "Only include employees with attendance" option is enabled, but no active employees have attendance records for this period.';
            } else {
                $errorMsg .= '. No active or on-leave employees found with activity during this period.';
            }

            // Throw a validation exception instead of generic exception
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                response()->json([
                    'message' => 'Validation failed',
                    'error' => $errorMsg,
                ], 422)
            );
        }

        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;

        foreach ($employees as $employee) {
            // Use PayrollService for holiday-aware calculation
            $item = $this->payrollService->calculatePayrollItem($payroll, $employee);

            $payrollItem = PayrollItem::create($item);

            // Record deduction installments for this employee
            $this->recordDeductionInstallments($payroll, $employee);

            $totalGross += $item['gross_pay'];
            $totalDeductions += $item['total_deductions'];
            $totalNet += $item['net_pay'];
        }

        $payroll->update([
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet,
        ]);
    }

    /**
     * Record deduction installment payments when payroll is generated
     */
    private function recordDeductionInstallments(Payroll $payroll, Employee $employee)
    {
        // Get active deductions for this employee in this payroll period
        $activeDeductions = EmployeeDeduction::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('start_date', '<=', $payroll->period_end)
            ->where(function ($query) use ($payroll) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $payroll->period_start);
            })
            ->get();

        foreach ($activeDeductions as $deduction) {
            // Calculate payment amount (use amount_per_cutoff, but not more than balance)
            $paymentAmount = min($deduction->amount_per_cutoff, $deduction->balance);

            if ($paymentAmount > 0) {
                $newBalance = $deduction->balance - $paymentAmount;
                $newInstallmentsPaid = $deduction->installments_paid + 1;

                $updateData = [
                    'installments_paid' => $newInstallmentsPaid,
                    'balance' => $newBalance,
                ];

                // Mark as completed if balance is zero or all installments paid
                if ($newBalance <= 0 || $newInstallmentsPaid >= $deduction->installments) {
                    $updateData['status'] = 'completed';
                    $updateData['balance'] = 0; // Ensure balance is exactly zero
                }

                $deduction->update($updateData);
            }
        }
    }
}
