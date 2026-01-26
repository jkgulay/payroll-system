<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeLoan;
use App\Models\EmployeeDeduction;
use App\Models\User;
use App\Exports\PayrollExport;
use App\Services\PayrollService;
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
            // Employee filtering options
            'filter_type' => 'nullable|in:all,position,project,department,staff_type',
            'position_ids' => 'nullable|array',
            'position_ids.*' => 'exists:position_rates,id',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'exists:projects,id',
            'departments' => 'nullable|array',
            'departments.*' => 'string',
            'staff_types' => 'nullable|array',
            'staff_types.*' => 'string',
            // Employee limit
            'employee_limit' => 'nullable|integer|min:1|max:1000',
            // Only employees with attendance
            'has_attendance' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $payroll = Payroll::create([
                'period_name' => $validated['period_name'],
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'payment_date' => $validated['payment_date'],
                'status' => 'draft',
                'created_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Refresh the model to ensure ID is available (critical for PostgreSQL)
            $payroll->refresh();

            // Generate payroll items for filtered employees
            $filters = [
                'type' => $validated['filter_type'] ?? 'all',
                'position_ids' => $validated['position_ids'] ?? [],
                'project_ids' => $validated['project_ids'] ?? [],
                'departments' => $validated['departments'] ?? [],
                'staff_types' => $validated['staff_types'] ?? [],
                'limit' => $validated['employee_limit'] ?? null,
                'has_attendance' => $validated['has_attendance'] ?? false,
            ];
            $this->generatePayrollItems($payroll, $filters);

            DB::commit();

            // Reload with count
            $payroll->loadCount('items');

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

        $payroll->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Payroll updated successfully',
            'data' => $payroll->load(['items.employee', 'creator'])
        ]);
    }

    public function destroy(Payroll $payroll)
    {
        // Allow deletion of any payroll status (draft, finalized, paid)
        $payroll->delete();

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
        // Increase memory limit for PDF generation
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');

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

    public function downloadRegister(Payroll $payroll)
    {
        // Increase memory limit for PDF generation
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');

        $payroll->load(['items.employee.positionRate']);

        $pdf = Pdf::loadView('payroll.register', compact('payroll'));

        return $pdf->download("payroll_register_{$payroll->payroll_number}.pdf");
    }

    public function exportToExcel(Payroll $payroll)
    {
        $payroll->load(['items.employee.positionRate']);

        $filename = "payroll_{$payroll->period_name}_" . now()->format('YmdHis') . ".xlsx";

        return Excel::download(new PayrollExport($payroll), $filename);
    }

    private function generatePayrollItems(Payroll $payroll, array $filters = [])
    {
        Log::info('=== Payroll Generation Debug ===');
        Log::info('Filters received:', $filters);
        Log::info('Has attendance value: ' . var_export($filters['has_attendance'] ?? 'not set', true));
        Log::info('Has attendance empty check: ' . var_export(empty($filters['has_attendance']), true));

        $query = Employee::where('activity_status', 'active');
        $initialCount = Employee::where('activity_status', 'active')->count();
        Log::info('Initial active employees: ' . $initialCount);

        // Apply filters based on filter type
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $filterApplied = false;

            if ($filters['type'] === 'position' && !empty($filters['position_ids'])) {
                $query->whereIn('position_id', $filters['position_ids']);
                $filterApplied = true;
            } elseif ($filters['type'] === 'project' && !empty($filters['project_ids'])) {
                $query->whereIn('project_id', $filters['project_ids']);
                $filterApplied = true;
            } elseif ($filters['type'] === 'department' && !empty($filters['departments'])) {
                $query->whereIn('department', $filters['departments']);
                $filterApplied = true;
            } elseif ($filters['type'] === 'staff_type' && !empty($filters['staff_types'])) {
                $query->whereIn('staff_type', $filters['staff_types']);
                $filterApplied = true;
            }

            // If filter type is set but no specific values provided, treat as 'all'
            if (!$filterApplied) {
                Log::info('Filter type "' . $filters['type'] . '" set but no specific values provided, treating as "all"');
            }
        }

        $afterTypeFilter = $query->count();
        Log::info('After type filter: ' . $afterTypeFilter);

        // Filter by attendance if requested
        if (!empty($filters['has_attendance'])) {
            Log::info('Applying attendance filter for period: ' . $payroll->period_start . ' to ' . $payroll->period_end);
            $query->whereHas('attendance', function ($q) use ($payroll) {
                $q->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
                    ->where('status', '!=', 'absent');
            });
            $afterAttendance = $query->count();
            Log::info('After attendance filter: ' . $afterAttendance);
        }

        // Order by employee number for consistent selection
        $query->orderBy('employee_number');

        // Apply limit if specified
        if (!empty($filters['limit'])) {
            $query->limit($filters['limit']);
        }

        $employees = $query->get();

        if ($employees->isEmpty()) {
            $errorMsg = 'No employees found matching the selected filters';

            // Provide more specific error message
            if (!empty($filters['has_attendance'])) {
                $errorMsg .= '. Note: The "Only include employees with attendance" option is enabled. ';
                if ($afterTypeFilter > 0) {
                    $errorMsg .= "Found {$afterTypeFilter} employee(s) in the selected department/filter, but none have attendance records for this period.";
                } else {
                    $errorMsg .= 'No employees found in the selected department/filter.';
                }
            } elseif ($afterTypeFilter === 0) {
                $errorMsg .= '. No employees found in the selected department/filter.';
            }

            // Throw a validation exception instead of generic exception
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                response()->json([
                    'message' => 'Validation failed',
                    'error' => $errorMsg,
                    'employee_count' => [
                        'total_active' => $initialCount ?? 0,
                        'after_filter' => $afterTypeFilter,
                        'with_attendance' => 0
                    ]
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
