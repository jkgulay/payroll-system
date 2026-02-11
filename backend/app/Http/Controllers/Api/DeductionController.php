<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDeduction;
use App\Models\Employee;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DeductionController extends Controller
{
    public function __construct()
    {
        // Admin, HR, and Payrollist can manage deductions
        $this->middleware('role:admin,hr,payrollist')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        $query = EmployeeDeduction::with(['employee', 'createdBy', 'approvedBy']);

        // Search functionality - prioritize employee name search
        if ($request->has('search') && !empty($request->search)) {
            $search = mb_strtolower($request->search);
            $searchLike = "%{$search}%";

            $query->whereHas('employee', function ($empQuery) use ($searchLike) {
                $empQuery->whereRaw('lower(first_name) like ?', [$searchLike])
                    ->orWhereRaw('lower(last_name) like ?', [$searchLike])
                    ->orWhereRaw('lower(middle_name) like ?', [$searchLike])
                    ->orWhereRaw('lower(suffix) like ?', [$searchLike])
                    ->orWhereRaw('lower(employee_number) like ?', [$searchLike])
                    ->orWhereRaw("lower(concat(first_name, ' ', last_name)) like ?", [$searchLike])
                    ->orWhereRaw("lower(concat(last_name, ' ', first_name)) like ?", [$searchLike])
                    ->orWhereRaw("lower(concat(first_name, ' ', middle_name, ' ', last_name)) like ?", [$searchLike])
                    ->orWhereRaw("lower(concat(first_name, ' ', left(middle_name, 1), '. ', last_name)) like ?", [$searchLike])
                    ->orWhereRaw("lower(concat(first_name, ' ', last_name, ' ', suffix)) like ?", [$searchLike]);
            });
        }

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by department
        if ($request->has('department')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('project_id', $request->department);
            });
        }

        // Filter by position
        if ($request->has('position')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('position_id', $request->position);
            });
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

        $shouldPaginate = $request->boolean('paginate', true);
        $perPage = $request->integer('per_page', 15);

        return $shouldPaginate
            ? response()->json($query->latest()->paginate($perPage))
            : response()->json($query->latest()->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'deduction_type' => 'required|string|max:50',
            'deduction_name' => 'nullable|string|max:100',
            'total_amount' => 'required|numeric|min:0.01',
            'amount_per_cutoff' => 'required|numeric|min:0.01',
            'installments' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Auto-generate deduction_name from deduction_type if not provided
        if (empty($validated['deduction_name'])) {
            $validated['deduction_name'] = ucwords(str_replace('_', ' ', $validated['deduction_type'])) . ' Deduction';
        }

        // Auto-generate reference_number if not provided
        if (empty($validated['reference_number'])) {
            $validated['reference_number'] = 'DED-' . date('Y') . '-' . strtoupper(uniqid());
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

            // Get employee name safely
            $employeeName = $deduction->employee
                ? ($deduction->employee->full_name ?? ($deduction->employee->first_name . ' ' . $deduction->employee->last_name))
                : 'Unknown Employee';

            // Create audit log
            AuditLog::create([
                'module' => 'deductions',
                'action' => 'create',
                'description' => "Deduction created for employee: {$employeeName} - {$deduction->deduction_name}",
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
            Log::error('Failed to create deduction: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
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
            'deduction_type' => 'sometimes|in:ppe,tools,uniform,absence,cash_advance,cash_bond,sss,philhealth,pagibig,tax,loan,insurance,cooperative,other',
            'deduction_name' => 'nullable|string|max:100',
            'total_amount' => 'sometimes|numeric|min:0.01',
            'amount_per_cutoff' => 'sometimes|numeric|min:0.01',
            'installments' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
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
            Log::error('Failed to update deduction: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
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

            // Capture old values BEFORE updating
            $oldBalance = $deduction->balance;
            $oldInstallmentsPaid = $deduction->installments_paid;

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
                'old_values' => json_encode(['balance' => $oldBalance, 'installments_paid' => $oldInstallmentsPaid]),
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

    /**
     * Get all cash bond deductions
     */
    public function getCashBonds(Request $request)
    {
        $query = EmployeeDeduction::with(['employee', 'createdBy', 'approvedBy'])
            ->where('deduction_type', 'cash_bond');

        // Filter by employee
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: show only active and completed cash bonds
            $query->whereIn('status', ['active', 'completed']);
        }

        // If employee, only show their own cash bonds
        if (auth()->user()->role === 'employee') {
            $query->where('employee_id', auth()->user()->employee_id);
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * Create a cash bond deduction
     */
    public function createCashBond(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'total_amount' => 'required|numeric|min:1',
            'amount_per_cutoff' => 'required|numeric|min:1',
            'installments' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'description' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Calculate installments if not provided
        if (!isset($validated['installments'])) {
            $validated['installments'] = ceil($validated['total_amount'] / $validated['amount_per_cutoff']);
        }

        // Auto-generate reference_number if not provided
        if (empty($validated['reference_number'])) {
            $validated['reference_number'] = 'CB-' . date('Y') . '-' . strtoupper(uniqid());
        }

        // Calculate end date (semi-monthly)
        $installmentsInMonths = ceil($validated['installments'] / 2);
        $endDate = Carbon::parse($validated['start_date'])
            ->addMonths($installmentsInMonths)
            ->toDateString();

        $deductionData = array_merge($validated, [
            'deduction_type' => 'cash_bond',
            'deduction_name' => 'Cash Bond',
            'end_date' => $endDate,
            'balance' => $validated['total_amount'],
            'installments_paid' => 0,
            'status' => 'active',
            'created_by' => auth()->id(),
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        DB::beginTransaction();
        try {
            $cashBond = EmployeeDeduction::create($deductionData);

            // Load employee relationship for audit log
            $cashBond->load('employee');

            // Create audit log
            AuditLog::create([
                'module' => 'deductions',
                'action' => 'create',
                'description' => "Cash Bond created for employee: {$cashBond->employee->full_name} - ₱" . number_format($validated['total_amount'], 2),
                'user_id' => auth()->id(),
                'record_id' => $cashBond->id,
                'old_values' => null,
                'new_values' => json_encode($deductionData),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Cash Bond created successfully',
                'data' => $cashBond->load(['employee', 'createdBy', 'approvedBy'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create cash bond',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refund/return a cash bond (mark as completed and set balance to 0)
     */
    public function refundCashBond(Request $request, EmployeeDeduction $deduction)
    {
        if ($deduction->deduction_type !== 'cash_bond') {
            return response()->json([
                'message' => 'This deduction is not a cash bond'
            ], 422);
        }

        if ($deduction->status === 'completed') {
            return response()->json([
                'message' => 'This cash bond has already been refunded or completed'
            ], 422);
        }

        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0',
            'refund_date' => 'required|date',
            'refund_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $oldBalance = $deduction->balance;

            $deduction->update([
                'balance' => max(0, $oldBalance - $validated['refund_amount']),
                'status' => 'completed',
                'notes' => ($deduction->notes ? $deduction->notes . "\n\n" : '') .
                    "Refunded on " . Carbon::parse($validated['refund_date'])->format('Y-m-d') .
                    ": ₱" . number_format($validated['refund_amount'], 2) .
                    ($validated['refund_reason'] ? " - {$validated['refund_reason']}" : ''),
            ]);

            // Create audit log
            AuditLog::create([
                'module' => 'deductions',
                'action' => 'refund',
                'description' => "Cash Bond refunded for {$deduction->employee->full_name}: ₱" . number_format($validated['refund_amount'], 2),
                'user_id' => auth()->id(),
                'record_id' => $deduction->id,
                'old_values' => json_encode(['balance' => $oldBalance, 'status' => 'active']),
                'new_values' => json_encode(['balance' => 0, 'status' => 'completed']),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Cash Bond refunded successfully',
                'data' => $deduction->fresh()->load('employee')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to refund cash bond',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create deductions for multiple employees
     */
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'selection_mode' => 'required|in:individual,multiple,department,position',
            'employee_ids' => 'required_if:selection_mode,individual,multiple|array',
            'employee_ids.*' => 'exists:employees,id',
            'department' => 'required_if:selection_mode,department',
            'position' => 'required_if:selection_mode,position',
            'deduction_type' => 'required|string|max:50',
            'deduction_name' => 'nullable|string|max:100',
            'total_amount' => 'required|numeric|min:0.01',
            'amount_per_cutoff' => 'required|numeric|min:0.01',
            'installments' => 'nullable|integer|min:1',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string|max:500',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Get employee IDs based on selection mode
        $employeeIds = [];

        if ($validated['selection_mode'] === 'department') {
            // Department = Project ID
            $employeeIds = Employee::where('project_id', $validated['department'])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
        } elseif ($validated['selection_mode'] === 'position') {
            // Position = Position ID
            $employeeIds = Employee::where('position_id', $validated['position'])
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();
        } else {
            // individual or multiple
            $employeeIds = $validated['employee_ids'];
        }

        if (empty($employeeIds)) {
            return response()->json([
                'message' => 'No employees found matching the criteria'
            ], 422);
        }

        // Auto-generate deduction_name from deduction_type if not provided
        $deductionName = $validated['deduction_name']
            ?? ucwords(str_replace('_', ' ', $validated['deduction_type'])) . ' Deduction';

        // Calculate installments if not provided
        $installments = $validated['installments'] ?? null;
        if (!$installments && isset($validated['end_date'])) {
            $start = Carbon::parse($validated['start_date']);
            $end = Carbon::parse($validated['end_date']);
            $months = $start->diffInMonths($end);
            $installments = $months * 2; // Semi-monthly (2 cutoffs per month)
        } elseif (!$installments) {
            $installments = ceil($validated['total_amount'] / $validated['amount_per_cutoff']);
        }

        // Calculate end date if not provided
        $endDate = $validated['end_date'] ?? null;
        if (!$endDate) {
            $installmentsInMonths = ceil($installments / 2);
            $endDate = Carbon::parse($validated['start_date'])
                ->addMonths($installmentsInMonths)
                ->toDateString();
        }

        DB::beginTransaction();
        try {
            $createdDeductions = [];
            $employeeNames = [];

            foreach ($employeeIds as $employeeId) {
                // Generate unique reference number for each deduction
                $referenceNumber = 'DED-' . date('Y') . '-' . strtoupper(uniqid());

                $deductionData = [
                    'employee_id' => $employeeId,
                    'deduction_type' => $validated['deduction_type'],
                    'deduction_name' => $deductionName,
                    'total_amount' => $validated['total_amount'],
                    'amount_per_cutoff' => $validated['amount_per_cutoff'],
                    'installments' => $installments,
                    'start_date' => $validated['start_date'],
                    'end_date' => $endDate,
                    'description' => $validated['description'] ?? null,
                    'reference_number' => $referenceNumber,
                    'notes' => $validated['notes'] ?? null,
                    'balance' => $validated['total_amount'],
                    'installments_paid' => 0,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ];

                $deduction = EmployeeDeduction::create($deductionData);
                $deduction->load('employee');
                $createdDeductions[] = $deduction;

                // Collect employee names for audit log
                $employeeName = $deduction->employee
                    ? ($deduction->employee->full_name ?? ($deduction->employee->first_name . ' ' . $deduction->employee->last_name))
                    : 'Unknown Employee';
                $employeeNames[] = $employeeName;
            }

            // Create audit log
            $description = "Bulk deduction created for " . count($employeeIds) . " employee(s)";
            if ($validated['selection_mode'] === 'department') {
                $description .= " in department: {$validated['department']}";
            } elseif ($validated['selection_mode'] === 'position') {
                $description .= " with position: {$validated['position']}";
            }
            $description .= " - {$deductionName}";

            AuditLog::create([
                'module' => 'deductions',
                'action' => 'bulk_create',
                'description' => $description,
                'user_id' => auth()->id(),
                'record_id' => null,
                'old_values' => null,
                'new_values' => json_encode([
                    'selection_mode' => $validated['selection_mode'],
                    'employee_count' => count($employeeIds),
                    'deduction_type' => $validated['deduction_type'],
                    'total_amount' => $validated['total_amount'],
                    'amount_per_cutoff' => $validated['amount_per_cutoff'],
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => "Successfully created deductions for " . count($createdDeductions) . " employee(s)",
                'data' => [
                    'count' => count($createdDeductions),
                    'deductions' => $createdDeductions,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk create deductions: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to create bulk deductions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of unique departments (projects) from active employees
     */
    public function getDepartments()
    {
        try {
            // Get projects that have active employees
            $departments = \App\Models\Project::whereHas('employees', function ($query) {
                $query->where('is_active', true);
            })
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(function ($project) {
                    return [
                        'value' => $project->id,
                        'title' => $project->name,
                    ];
                })
                ->values();

            return response()->json($departments);
        } catch (\Exception $e) {
            Log::error('Error fetching departments: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch departments'], 500);
        }
    }

    /**
     * Get list of unique positions from active employees
     */
    public function getPositions()
    {
        try {
            // Get position rates that have active employees
            $positions = \App\Models\PositionRate::whereHas('employees', function ($query) {
                $query->where('is_active', true);
            })
                ->where('is_active', true)
                ->orderBy('position_name')
                ->get(['id', 'position_name'])
                ->map(function ($position) {
                    return [
                        'value' => $position->id,
                        'title' => $position->position_name,
                    ];
                })
                ->values();

            return response()->json($positions);
        } catch (\Exception $e) {
            Log::error('Error fetching positions: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch positions'], 500);
        }
    }

    /**
     * Get employees by department or position
     */
    public function getEmployeesByFilter(Request $request)
    {
        $validated = $request->validate([
            'department' => 'nullable',
            'position' => 'nullable',
        ]);

        try {
            $query = Employee::where('is_active', true)
                ->with(['project', 'positionRate'])
                ->select('id', 'employee_number', 'first_name', 'last_name', 'middle_name', 'project_id', 'position_id');

            if (!empty($validated['department'])) {
                // Department = Project ID
                $query->where('project_id', $validated['department']);
            }

            if (!empty($validated['position'])) {
                // Position = Position ID
                $query->where('position_id', $validated['position']);
            }

            $employees = $query->orderBy('last_name')->orderBy('first_name')->get();

            // Add full_name and position/project names to each employee
            $employees->each(function ($employee) {
                $employee->full_name = trim($employee->first_name . ' ' . ($employee->middle_name ? $employee->middle_name . ' ' : '') . $employee->last_name);
                $employee->position = $employee->positionRate?->position_name ?? 'N/A';
                $employee->department = $employee->project?->name ?? 'N/A';
            });

            return response()->json([
                'count' => $employees->count(),
                'employees' => $employees
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching employees by filter: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch employees'], 500);
        }
    }
}
