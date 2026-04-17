<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceModificationRequest;
use App\Models\DeductionPayment;
use App\Models\EmployeeDeduction;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class DeductionController extends Controller
{
    private const GOVERNMENT_RATE_ONLY_TYPES = ['sss', 'philhealth', 'pagibig', 'tax'];
    private const SINGLE_ACTIVE_PLAN_TYPES = ['cash_advance', 'cash_bond', 'cooperative'];
    private const MANUAL_DEDUCTION_TYPES = [
        'ppe',
        'tools',
        'uniform',
        'absence',
        'cash_advance',
        'cash_bond',
        'loan',
        'insurance',
        'cooperative',
        'damages',
        'other',
    ];

    public function __construct()
    {
        // Admin, HR, and Payrollist can manage deductions
        $this->middleware('role:admin,hr,payrollist')->only(['store', 'update', 'destroy']);
    }

    private function resolveModuleLabel(string $module): string
    {
        $moduleLabels = [
            'cash-bonds' => 'cash bonds',
            'employee-savings' => 'employee savings',
            'cash-advances' => 'cash advances',
        ];

        return $moduleLabels[$module] ?? 'this module';
    }

    private function resolveModuleByDeductionType(string $deductionType): ?string
    {
        return match ($deductionType) {
            'cash_bond' => 'cash-bonds',
            'cooperative' => 'employee-savings',
            'cash_advance' => 'cash-advances',
            default => null,
        };
    }

    private function assertSingleActivePlanAllowed(int $employeeId, string $deductionType, ?int $ignoreDeductionId = null): ?JsonResponse
    {
        if (!in_array($deductionType, self::SINGLE_ACTIVE_PLAN_TYPES, true)) {
            return null;
        }

        $query = EmployeeDeduction::query()
            ->where('employee_id', $employeeId)
            ->where('deduction_type', $deductionType)
            ->where('status', 'active')
            ->where('balance', '>', 0);

        if ($ignoreDeductionId) {
            $query->where('id', '!=', $ignoreDeductionId);
        }

        if (!$query->exists()) {
            return null;
        }

        $deductionLabels = [
            'cash_advance' => 'cash advance',
            'cash_bond' => 'cash bond',
            'cooperative' => 'employee savings',
        ];
        $label = $deductionLabels[$deductionType] ?? str_replace('_', ' ', $deductionType);

        return response()->json([
            'message' => "Employee already has an active {$label} account. Complete or cancel the existing account first."
        ], 422);
    }

    private function enforcePayrollistModuleAccess(string $module): ?JsonResponse
    {
        $user = auth()->user();

        if (!$user || in_array($user->role, ['admin', 'hr', 'employee'], true)) {
            return null;
        }

        if ($user->role !== 'payrollist') {
            return null;
        }

        $hasApprovedAccess = AttendanceModificationRequest::query()
            ->where('requested_by', $user->id)
            ->where('module', $module)
            ->where('status', 'approved')
            ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])
            ->exists();

        if ($hasApprovedAccess) {
            return null;
        }

        $moduleLabel = $this->resolveModuleLabel($module);

        return response()->json([
            'message' => "Access request required for {$moduleLabel} management today."
        ], 403);
    }

    private function buildBenefitAccountsSnapshot($records, ?int $employeeFilter = null)
    {
        $employeesQuery = Employee::query()
            ->where('is_active', true)
            ->select('id', 'employee_number', 'first_name', 'middle_name', 'last_name', 'suffix');

        if ($employeeFilter) {
            $employeesQuery->where('id', $employeeFilter);
        }

        $employees = $employeesQuery->orderBy('last_name')->orderBy('first_name')->get();
        $recordsByEmployee = $records->groupBy('employee_id');

        return $employees->map(function (Employee $employee) use ($recordsByEmployee) {
            $employeeRecords = $recordsByEmployee->get($employee->id, collect())->values();
            $activePlanCount = $employeeRecords
                ->where('status', 'active')
                ->where('balance', '>', 0)
                ->count();

            $selectedRecord = $employeeRecords->firstWhere('status', 'active')
                ?? $employeeRecords->firstWhere('status', 'completed')
                ?? $employeeRecords->first();

            if ($selectedRecord) {
                $payload = $selectedRecord->toArray();
                $payload['is_virtual'] = false;
                $payload['wallet_balance'] = max(0, (float) ($payload['total_amount'] ?? 0) - (float) ($payload['balance'] ?? 0));
                $payload['active_plan_count'] = (int) $activePlanCount;
                return $payload;
            }

            return [
                'id' => 'virtual-' . $employee->id,
                'employee_id' => $employee->id,
                'employee' => [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                    'first_name' => $employee->first_name,
                    'middle_name' => $employee->middle_name,
                    'last_name' => $employee->last_name,
                    'suffix' => $employee->suffix,
                ],
                'deduction_type' => null,
                'deduction_name' => null,
                'total_amount' => 0,
                'amount_per_cutoff' => 0,
                'installments' => 0,
                'installments_paid' => 0,
                'balance' => 0,
                'wallet_balance' => 0,
                'start_date' => null,
                'end_date' => null,
                'status' => 'no_plan',
                'description' => null,
                'reference_number' => null,
                'notes' => null,
                'is_virtual' => true,
                'active_plan_count' => 0,
                'created_at' => null,
                'updated_at' => null,
            ];
        })->values();
    }

    private function nextDeductionInstallmentNumber(int $deductionId): int
    {
        $lastInstallment = DeductionPayment::query()
            ->where('employee_deduction_id', $deductionId)
            ->max('installment_number');

        return $lastInstallment ? ((int) $lastInstallment + 1) : 1;
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
            'deduction_type' => ['required', 'string', 'max:50', Rule::in(self::MANUAL_DEDUCTION_TYPES)],
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

        if (in_array($validated['deduction_type'], self::GOVERNMENT_RATE_ONLY_TYPES, true)) {
            return response()->json([
                'message' => 'Government deductions (SSS, PhilHealth, Pag-IBIG, Tax) are computed from Government Rate settings and cannot be created as manual deductions.'
            ], 422);
        }

        $module = $this->resolveModuleByDeductionType((string) $validated['deduction_type']);
        if ($module && ($accessDenied = $this->enforcePayrollistModuleAccess($module))) {
            return $accessDenied;
        }

        if ($planConflict = $this->assertSingleActivePlanAllowed((int) $validated['employee_id'], (string) $validated['deduction_type'])) {
            return $planConflict;
        }

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
            'deduction_type' => ['sometimes', Rule::in(self::MANUAL_DEDUCTION_TYPES)],
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

        if (isset($validated['deduction_type']) && in_array($validated['deduction_type'], self::GOVERNMENT_RATE_ONLY_TYPES, true)) {
            return response()->json([
                'message' => 'Government deductions (SSS, PhilHealth, Pag-IBIG, Tax) are computed from Government Rate settings and cannot be set as manual deductions.'
            ], 422);
        }

        // Recalculate balance if total amount changed
        if (isset($validated['total_amount'])) {
            $amountPaid = $deduction->total_amount - $deduction->balance;
            $validated['balance'] = max(0, $validated['total_amount'] - $amountPaid);
        }

        $nextDeductionType = (string) ($validated['deduction_type'] ?? $deduction->deduction_type);
        $nextStatus = (string) ($validated['status'] ?? $deduction->status);
        $nextBalance = array_key_exists('balance', $validated)
            ? (float) $validated['balance']
            : (float) $deduction->balance;

        $modulesToCheck = array_filter(array_unique([
            $this->resolveModuleByDeductionType((string) $deduction->deduction_type),
            $this->resolveModuleByDeductionType($nextDeductionType),
        ]));

        foreach ($modulesToCheck as $module) {
            if ($accessDenied = $this->enforcePayrollistModuleAccess($module)) {
                return $accessDenied;
            }
        }

        if ($nextStatus === 'active' && $nextBalance > 0) {
            if ($planConflict = $this->assertSingleActivePlanAllowed((int) $deduction->employee_id, $nextDeductionType, (int) $deduction->id)) {
                return $planConflict;
            }
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
        $module = $this->resolveModuleByDeductionType((string) $deduction->deduction_type);
        if ($module && ($accessDenied = $this->enforcePayrollistModuleAccess($module))) {
            return $accessDenied;
        }

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

        $module = $this->resolveModuleByDeductionType((string) $deduction->deduction_type);
        if ($module && ($accessDenied = $this->enforcePayrollistModuleAccess($module))) {
            return $accessDenied;
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
            $amountPerCutoff = (float) ($deduction->amount_per_cutoff ?? 0);
            if ($amountPerCutoff <= 0 && (int) $deduction->installments > 0) {
                $amountPerCutoff = (float) $deduction->total_amount / max((int) $deduction->installments, 1);
            }

            $newBalance = max((float) $deduction->balance - (float) $validated['amount'], 0);
            $newInstallmentsPaid = (int) $deduction->installments_paid + 1;

            $currentInstallments = max((int) ($deduction->installments ?? 0), $newInstallmentsPaid, 1);
            $newInstallments = $currentInstallments;

            if ($newBalance > 0 && $amountPerCutoff > 0) {
                $remainingInstallmentsNeeded = (int) ceil($newBalance / $amountPerCutoff);
                $newInstallments = max($newInstallments, $newInstallmentsPaid + $remainingInstallmentsNeeded);
            }

            // Capture old values BEFORE updating
            $oldBalance = $deduction->balance;
            $oldInstallmentsPaid = $deduction->installments_paid;
            $oldInstallments = $deduction->installments;

            $updateData = [
                'installments_paid' => $newInstallmentsPaid,
                'balance' => $newBalance,
                'installments' => $newInstallments,
                'status' => $newBalance <= 0 ? 'completed' : 'active',
            ];

            if ($newBalance <= 0) {
                $updateData['balance'] = 0;
            }

            if (
                $newInstallments > (int) ($deduction->installments ?? 0)
                && !empty($deduction->start_date)
            ) {
                $extendedEndDate = Carbon::parse($deduction->start_date)
                    ->addMonths((int) ceil($newInstallments / 2))
                    ->toDateString();

                if (empty($deduction->end_date) || Carbon::parse($extendedEndDate)->gt(Carbon::parse($deduction->end_date))) {
                    $updateData['end_date'] = $extendedEndDate;
                }
            }

            $deduction->update($updateData);

            if (Schema::hasTable('deduction_payments')) {
                DeductionPayment::create([
                    'employee_deduction_id' => $deduction->id,
                    'payroll_id' => null,
                    'payroll_item_id' => null,
                    'payment_date' => $validated['payment_date'],
                    'amount' => (float) $validated['amount'],
                    'balance_after_payment' => (float) ($updateData['balance'] ?? $newBalance),
                    'installment_number' => $this->nextDeductionInstallmentNumber((int) $deduction->id),
                    'remarks' => 'Manual installment payment',
                ]);
            }

            // Create audit log
            AuditLog::create([
                'module' => 'deductions',
                'action' => 'installment',
                'description' => "Installment payment for {$deduction->deduction_name}: ₱" . number_format($validated['amount'], 2),
                'user_id' => auth()->id(),
                'record_id' => $deduction->id,
                'old_values' => json_encode([
                    'balance' => $oldBalance,
                    'installments_paid' => $oldInstallmentsPaid,
                    'installments' => $oldInstallments,
                ]),
                'new_values' => json_encode([
                    'balance' => $updateData['balance'],
                    'installments_paid' => $newInstallmentsPaid,
                    'installments' => $newInstallments,
                ]),
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
     * Get all cash advance deductions
     */
    public function getCashAdvances(Request $request)
    {
        if ($accessDenied = $this->enforcePayrollistModuleAccess('cash-advances')) {
            return $accessDenied;
        }

        $query = EmployeeDeduction::with(['employee', 'createdBy', 'approvedBy'])
            ->where('deduction_type', 'cash_advance');

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['active', 'completed']);
        }

        if (auth()->user()->role === 'employee') {
            $query->where('employee_id', auth()->user()->employee_id);
        }

        $shouldPaginate = $request->boolean('paginate', true);
        $perPage = $request->integer('per_page', 15);

        if ($shouldPaginate) {
            return response()->json($query->latest()->paginate($perPage));
        }

        $records = $query->latest()->get();
        $includeAllEmployees =
            $request->boolean('include_all_employees', false)
            && auth()->user()->role !== 'employee';

        if ($includeAllEmployees) {
            $employeeFilter = $request->filled('employee_id') ? (int) $request->employee_id : null;
            return response()->json($this->buildBenefitAccountsSnapshot($records, $employeeFilter));
        }

        return response()->json($records);
    }

    /**
     * Create a cash advance deduction
     */
    public function createCashAdvance(Request $request)
    {
        if ($accessDenied = $this->enforcePayrollistModuleAccess('cash-advances')) {
            return $accessDenied;
        }

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

        if ($planConflict = $this->assertSingleActivePlanAllowed((int) $validated['employee_id'], 'cash_advance')) {
            return $planConflict;
        }

        if (!isset($validated['installments'])) {
            $validated['installments'] = ceil($validated['total_amount'] / $validated['amount_per_cutoff']);
        }

        if (empty($validated['reference_number'])) {
            $validated['reference_number'] = 'CA-' . date('Y') . '-' . strtoupper(uniqid());
        }

        $installmentsInMonths = ceil($validated['installments'] / 2);
        $endDate = Carbon::parse($validated['start_date'])
            ->addMonths($installmentsInMonths)
            ->toDateString();

        $deductionData = array_merge($validated, [
            'deduction_type' => 'cash_advance',
            'deduction_name' => 'Cash Advance',
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
            $cashAdvance = EmployeeDeduction::create($deductionData);
            $cashAdvance->load('employee');

            AuditLog::create([
                'module' => 'deductions',
                'action' => 'create',
                'description' => "Cash advance created for employee: {$cashAdvance->employee->full_name} - ₱" . number_format($validated['total_amount'], 2),
                'user_id' => auth()->id(),
                'record_id' => $cashAdvance->id,
                'old_values' => null,
                'new_values' => json_encode($deductionData),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Cash advance created successfully',
                'data' => $cashAdvance->load(['employee', 'createdBy', 'approvedBy'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create cash advance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all cash bond deductions
     */
    public function getCashBonds(Request $request)
    {
        if ($accessDenied = $this->enforcePayrollistModuleAccess('cash-bonds')) {
            return $accessDenied;
        }

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

        $shouldPaginate = $request->boolean('paginate', true);
        $perPage = $request->integer('per_page', 15);

        if ($shouldPaginate) {
            return response()->json($query->latest()->paginate($perPage));
        }

        $records = $query->latest()->get();
        $includeAllEmployees =
            $request->boolean('include_all_employees', false)
            && auth()->user()->role !== 'employee';

        if ($includeAllEmployees) {
            $employeeFilter = $request->filled('employee_id') ? (int) $request->employee_id : null;
            return response()->json($this->buildBenefitAccountsSnapshot($records, $employeeFilter));
        }

        return response()->json($records);
    }

    /**
     * Create a cash bond deduction
     */
    public function createCashBond(Request $request)
    {
        if ($accessDenied = $this->enforcePayrollistModuleAccess('cash-bonds')) {
            return $accessDenied;
        }

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

        if ($planConflict = $this->assertSingleActivePlanAllowed((int) $validated['employee_id'], 'cash_bond')) {
            return $planConflict;
        }

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
        if ($accessDenied = $this->enforcePayrollistModuleAccess('cash-bonds')) {
            return $accessDenied;
        }

        $user = auth()->user();
        if ($user->role === 'employee' && (int) $deduction->employee_id !== (int) $user->employee_id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($deduction->deduction_type !== 'cash_bond') {
            return response()->json([
                'message' => 'This deduction is not a cash bond'
            ], 422);
        }

        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01',
            'refund_date' => 'required|date',
            'refund_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $oldBalance = $deduction->balance;
            $oldStatus = $deduction->status;
            $contributedAmount = max(0, (float) $deduction->total_amount - (float) $oldBalance);

            if ($contributedAmount <= 0) {
                return response()->json([
                    'message' => 'No contributed amount is available for refund yet'
                ], 422);
            }

            if ((float) $validated['refund_amount'] > $contributedAmount) {
                return response()->json([
                    'message' => 'Refund amount cannot exceed contributed cash bond amount'
                ], 422);
            }

            $newBalance = min((float) $deduction->total_amount, (float) $oldBalance + (float) $validated['refund_amount']);
            $newStatus = $newBalance <= 0 ? 'completed' : 'active';

            $deduction->update([
                'balance' => $newBalance,
                'status' => $newStatus,
                'notes' => ($deduction->notes ? $deduction->notes . "\n\n" : '') .
                    "Refunded on " . Carbon::parse($validated['refund_date'])->format('Y-m-d') .
                    ": ₱" . number_format($validated['refund_amount'], 2) .
                    ($validated['refund_reason'] ? " - {$validated['refund_reason']}" : ''),
            ]);

            if (Schema::hasTable('deduction_payments')) {
                DeductionPayment::create([
                    'employee_deduction_id' => $deduction->id,
                    'payroll_id' => null,
                    'payroll_item_id' => null,
                    'payment_date' => $validated['refund_date'],
                    'amount' => -(float) $validated['refund_amount'],
                    'balance_after_payment' => (float) $newBalance,
                    'installment_number' => $this->nextDeductionInstallmentNumber((int) $deduction->id),
                    'remarks' => 'Cash bond refund/return',
                ]);
            }

            // Create audit log
            AuditLog::create([
                'module' => 'deductions',
                'action' => 'refund',
                'description' => "Cash Bond refunded for {$deduction->employee->full_name}: ₱" . number_format($validated['refund_amount'], 2),
                'user_id' => auth()->id(),
                'record_id' => $deduction->id,
                'old_values' => json_encode(['balance' => $oldBalance, 'status' => $oldStatus]),
                'new_values' => json_encode(['balance' => $newBalance, 'status' => $newStatus]),
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
     * Get all employee savings deductions (cooperative type)
     */
    public function getEmployeeSavings(Request $request)
    {
        if ($accessDenied = $this->enforcePayrollistModuleAccess('employee-savings')) {
            return $accessDenied;
        }

        $query = EmployeeDeduction::with(['employee', 'createdBy', 'approvedBy'])
            ->where('deduction_type', 'cooperative');

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['active', 'completed']);
        }

        if (auth()->user()->role === 'employee') {
            $query->where('employee_id', auth()->user()->employee_id);
        }

        $shouldPaginate = $request->boolean('paginate', true);
        $perPage = $request->integer('per_page', 15);

        if ($shouldPaginate) {
            return response()->json($query->latest()->paginate($perPage));
        }

        $records = $query->latest()->get();
        $includeAllEmployees =
            $request->boolean('include_all_employees', false)
            && auth()->user()->role !== 'employee';

        if ($includeAllEmployees) {
            $employeeFilter = $request->filled('employee_id') ? (int) $request->employee_id : null;
            return response()->json($this->buildBenefitAccountsSnapshot($records, $employeeFilter));
        }

        return response()->json($records);
    }

    /**
     * Create an employee savings deduction (cooperative type)
     */
    public function createEmployeeSavings(Request $request)
    {
        if ($accessDenied = $this->enforcePayrollistModuleAccess('employee-savings')) {
            return $accessDenied;
        }

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

        if ($planConflict = $this->assertSingleActivePlanAllowed((int) $validated['employee_id'], 'cooperative')) {
            return $planConflict;
        }

        if (!isset($validated['installments'])) {
            $validated['installments'] = ceil($validated['total_amount'] / $validated['amount_per_cutoff']);
        }

        if (empty($validated['reference_number'])) {
            $validated['reference_number'] = 'ES-' . date('Y') . '-' . strtoupper(uniqid());
        }

        $installmentsInMonths = ceil($validated['installments'] / 2);
        $endDate = Carbon::parse($validated['start_date'])
            ->addMonths($installmentsInMonths)
            ->toDateString();

        $deductionData = array_merge($validated, [
            'deduction_type' => 'cooperative',
            'deduction_name' => 'Employee Savings',
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
            $employeeSavings = EmployeeDeduction::create($deductionData);
            $employeeSavings->load('employee');

            AuditLog::create([
                'module' => 'deductions',
                'action' => 'create',
                'description' => "Employee savings created for employee: {$employeeSavings->employee->full_name} - P" . number_format($validated['total_amount'], 2),
                'user_id' => auth()->id(),
                'record_id' => $employeeSavings->id,
                'old_values' => null,
                'new_values' => json_encode($deductionData),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Employee savings created successfully',
                'data' => $employeeSavings->load(['employee', 'createdBy', 'approvedBy'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create employee savings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Withdraw from employee savings balance
     */
    public function withdrawEmployeeSavings(Request $request, EmployeeDeduction $deduction)
    {
        if ($accessDenied = $this->enforcePayrollistModuleAccess('employee-savings')) {
            return $accessDenied;
        }

        $user = auth()->user();
        if ($user->role === 'employee' && (int) $deduction->employee_id !== (int) $user->employee_id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($deduction->deduction_type !== 'cooperative') {
            return response()->json([
                'message' => 'This deduction is not an employee savings record'
            ], 422);
        }

        $validated = $request->validate([
            'withdraw_amount' => 'required|numeric|min:0.01',
            'withdraw_date' => 'required|date',
            'withdraw_reason' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $oldBalance = $deduction->balance;
            $oldStatus = $deduction->status;
            $contributedAmount = max(0, (float) $deduction->total_amount - (float) $oldBalance);

            if ($contributedAmount <= 0) {
                return response()->json([
                    'message' => 'No contributed amount is available for withdrawal yet'
                ], 422);
            }

            if ((float) $validated['withdraw_amount'] > $contributedAmount) {
                return response()->json([
                    'message' => 'Withdraw amount cannot exceed contributed savings amount'
                ], 422);
            }

            $newBalance = min((float) $deduction->total_amount, (float) $oldBalance + (float) $validated['withdraw_amount']);
            $newStatus = $newBalance <= 0 ? 'completed' : 'active';

            $deduction->update([
                'balance' => $newBalance,
                'status' => $newStatus,
                'notes' => ($deduction->notes ? $deduction->notes . "\n\n" : '') .
                    "Withdrawn on " . Carbon::parse($validated['withdraw_date'])->format('Y-m-d') .
                    ": P" . number_format($validated['withdraw_amount'], 2) .
                    ($validated['withdraw_reason'] ? " - {$validated['withdraw_reason']}" : ''),
            ]);

            if (Schema::hasTable('deduction_payments')) {
                DeductionPayment::create([
                    'employee_deduction_id' => $deduction->id,
                    'payroll_id' => null,
                    'payroll_item_id' => null,
                    'payment_date' => $validated['withdraw_date'],
                    'amount' => -(float) $validated['withdraw_amount'],
                    'balance_after_payment' => (float) $newBalance,
                    'installment_number' => $this->nextDeductionInstallmentNumber((int) $deduction->id),
                    'remarks' => 'Employee savings withdrawal',
                ]);
            }

            $deduction->load('employee');

            AuditLog::create([
                'module' => 'deductions',
                'action' => 'withdraw',
                'description' => "Employee savings withdrawn for {$deduction->employee->full_name}: P" . number_format($validated['withdraw_amount'], 2),
                'user_id' => auth()->id(),
                'record_id' => $deduction->id,
                'old_values' => json_encode(['balance' => $oldBalance, 'status' => $oldStatus]),
                'new_values' => json_encode(['balance' => $newBalance, 'status' => $newStatus]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Employee savings withdrawal processed successfully',
                'data' => $deduction->fresh()->load('employee')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to process employee savings withdrawal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get deduction ledger transactions for a specific deduction account.
     */
    public function getDeductionLedger(EmployeeDeduction $deduction)
    {
        $user = auth()->user();

        if ($user->role === 'employee' && (int) $deduction->employee_id !== (int) $user->employee_id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        if ($deduction->deduction_type === 'cash_bond') {
            if ($accessDenied = $this->enforcePayrollistModuleAccess('cash-bonds')) {
                return $accessDenied;
            }
        }

        if ($deduction->deduction_type === 'cooperative') {
            if ($accessDenied = $this->enforcePayrollistModuleAccess('employee-savings')) {
                return $accessDenied;
            }
        }

        if ($deduction->deduction_type === 'cash_advance') {
            if ($accessDenied = $this->enforcePayrollistModuleAccess('cash-advances')) {
                return $accessDenied;
            }
        }

        $transactions = collect();

        if (Schema::hasTable('deduction_payments')) {
            $transactions = DeductionPayment::query()
                ->where('employee_deduction_id', $deduction->id)
                ->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->get()
                ->map(function (DeductionPayment $payment) {
                    return [
                        'id' => $payment->id,
                        'payment_date' => optional($payment->payment_date)->toDateString(),
                        'amount' => (float) $payment->amount,
                        'balance_after_payment' => (float) $payment->balance_after_payment,
                        'installment_number' => (int) $payment->installment_number,
                        'remarks' => $payment->remarks,
                        'payroll_id' => $payment->payroll_id,
                        'type' => (float) $payment->amount >= 0 ? 'contribution' : 'withdrawal',
                    ];
                })
                ->values();
        }

        $targetAmount = (float) ($deduction->total_amount ?? 0);
        $remainingTarget = max((float) ($deduction->balance ?? 0), 0);
        $walletBalance = max($targetAmount - $remainingTarget, 0);
        $progressPercent = $targetAmount > 0
            ? min(100, max(0, ($walletBalance / $targetAmount) * 100))
            : 0;

        return response()->json([
            'account' => [
                'id' => $deduction->id,
                'employee_id' => $deduction->employee_id,
                'deduction_type' => $deduction->deduction_type,
                'deduction_name' => $deduction->deduction_name,
                'target_amount' => $targetAmount,
                'remaining_target' => $remainingTarget,
                'wallet_balance' => $walletBalance,
                'progress_percent' => round($progressPercent, 2),
                'status' => $deduction->status,
            ],
            'transactions' => $transactions,
        ]);
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
            'deduction_type' => ['required', 'string', 'max:50', Rule::in(self::MANUAL_DEDUCTION_TYPES)],
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

        if (in_array($validated['deduction_type'], self::GOVERNMENT_RATE_ONLY_TYPES, true)) {
            return response()->json([
                'message' => 'Government deductions (SSS, PhilHealth, Pag-IBIG, Tax) are computed from Government Rate settings and cannot be bulk-created as manual deductions.'
            ], 422);
        }

        $module = $this->resolveModuleByDeductionType((string) $validated['deduction_type']);
        if ($module && ($accessDenied = $this->enforcePayrollistModuleAccess($module))) {
            return $accessDenied;
        }

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

        if (in_array($validated['deduction_type'], self::SINGLE_ACTIVE_PLAN_TYPES, true)) {
            $conflictingEmployeeIds = EmployeeDeduction::query()
                ->whereIn('employee_id', $employeeIds)
                ->where('deduction_type', $validated['deduction_type'])
                ->where('status', 'active')
                ->where('balance', '>', 0)
                ->pluck('employee_id')
                ->unique()
                ->values();

            if ($conflictingEmployeeIds->isNotEmpty()) {
                $conflictingNames = Employee::query()
                    ->whereIn('id', $conflictingEmployeeIds->all())
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->limit(5)
                    ->get(['first_name', 'last_name'])
                    ->map(fn(Employee $employee) => trim($employee->first_name . ' ' . $employee->last_name))
                    ->filter()
                    ->values();

                $deductionLabels = [
                    'cash_advance' => 'cash advance',
                    'cash_bond' => 'cash bond',
                    'cooperative' => 'employee savings',
                ];
                $label = $deductionLabels[$validated['deduction_type']] ?? str_replace('_', ' ', $validated['deduction_type']);
                $namePreview = $conflictingNames->isNotEmpty()
                    ? ' Conflicts include: ' . $conflictingNames->implode(', ') . '.'
                    : '';

                return response()->json([
                    'message' => 'One or more employees already have active ' . $label . ' accounts. Complete or cancel existing accounts first.' . $namePreview,
                    'conflict_count' => $conflictingEmployeeIds->count(),
                ], 422);
            }
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
                $description .= " in project: {$validated['department']}";
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
     * Get estimated cash advance availability for an employee.
     *
     * Availability is estimated from approved attendance earnings
     * between the day after the latest payroll period end and the as-of date.
     */
    public function getCashAdvanceAvailability(Request $request)
    {
        if ($accessDenied = $this->enforcePayrollistModuleAccess('cash-advances')) {
            return $accessDenied;
        }

        $validated = $request->validate([
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->whereNull('deleted_at'),
            ],
            'as_of_date' => 'nullable|date',
        ]);

        $employee = Employee::with('positionRate')->findOrFail($validated['employee_id']);
        $user = auth()->user();

        if ($user->role === 'employee' && (int) $user->employee_id !== (int) $employee->id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $asOfDate = Carbon::parse($validated['as_of_date'] ?? now())->toDateString();

        $latestPayroll = Payroll::query()
            ->join('payroll_items', 'payroll_items.payroll_id', '=', 'payrolls.id')
            ->where('payroll_items.employee_id', $employee->id)
            ->whereIn('payrolls.status', ['draft', 'finalized', 'paid'])
            ->whereDate('payrolls.period_end', '<=', $asOfDate)
            ->orderByDesc('payrolls.period_end')
            ->select([
                'payrolls.id',
                'payrolls.period_name',
                'payrolls.period_end',
                'payrolls.status',
            ])
            ->first();

        $startDate = $latestPayroll
            ? Carbon::parse($latestPayroll->period_end)->addDay()->toDateString()
            : ($employee->date_hired
                ? Carbon::parse($employee->date_hired)->toDateString()
                : $asOfDate);

        if (Carbon::parse($startDate)->gt(Carbon::parse($asOfDate))) {
            return response()->json([
                'employee_id' => $employee->id,
                'last_payroll' => $latestPayroll,
                'start_date' => $startDate,
                'as_of_date' => $asOfDate,
                'attendance_days_counted' => 0,
                'payable_days' => 0,
                'daily_rate' => 0,
                'estimated_basic_pay' => 0,
                'estimated_overtime_hours' => 0,
                'estimated_overtime_pay' => 0,
                'estimated_gross_pay' => 0,
                'active_cash_advance_balance' => 0,
                'recommended_limit' => 0,
                'available_balance' => 0,
            ]);
        }

        $standardHours = (float) config('payroll.standard_hours_per_day', 8);
        $workingDaysPerMonth = (float) config('payroll.working_days_per_month', 22);
        $baseRate = (float) $employee->getBasicSalary();

        if ($employee->salary_type === 'monthly') {
            $dailyRate = $workingDaysPerMonth > 0 ? ($baseRate / $workingDaysPerMonth) : 0;
        } elseif ($employee->salary_type === 'hourly') {
            $dailyRate = $baseRate * ($standardHours > 0 ? $standardHours : 8);
        } else {
            $dailyRate = $baseRate;
        }

        $hourlyRate = $standardHours > 0 ? ($dailyRate / $standardHours) : 0;

        $attendances = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startDate, $asOfDate])
            ->where('status', '!=', 'absent')
            ->where('approval_status', 'approved')
            ->whereNotNull('time_in')
            ->where(function ($query) {
                $query->whereNotNull('time_out')
                    ->orWhere('status', 'half_day');
            })
            ->get(['attendance_date', 'status', 'regular_hours', 'overtime_hours']);

        $payableDays = 0;
        $estimatedBasicPay = 0;
        $estimatedOvertimeHours = 0;
        $estimatedOvertimePay = 0;

        foreach ($attendances as $attendance) {
            $status = strtolower((string) ($attendance->status ?? ''));
            $regularHours = max((float) ($attendance->regular_hours ?? 0), 0);

            if ($status === 'half_day') {
                $dayFraction = 0.5;
            } elseif ($regularHours > 0 && $standardHours > 0) {
                $dayFraction = max(min($regularHours / $standardHours, 1), 0);
            } else {
                $dayFraction = 1.0;
            }

            $overtimeHours = max((float) ($attendance->overtime_hours ?? 0), 0);

            $payableDays += $dayFraction;
            $estimatedBasicPay += $dailyRate * $dayFraction;
            $estimatedOvertimeHours += $overtimeHours;
            $estimatedOvertimePay += $hourlyRate * $overtimeHours;
        }

        $estimatedGrossPay = $estimatedBasicPay + $estimatedOvertimePay;

        $activeCashAdvanceBalance = (float) EmployeeDeduction::query()
            ->where('employee_id', $employee->id)
            ->where('deduction_type', 'cash_advance')
            ->where('status', 'active')
            ->sum('balance');

        $recommendedLimit = max($estimatedGrossPay - $activeCashAdvanceBalance, 0);

        return response()->json([
            'employee_id' => $employee->id,
            'last_payroll' => $latestPayroll,
            'start_date' => $startDate,
            'as_of_date' => $asOfDate,
            'attendance_days_counted' => $attendances->count(),
            'payable_days' => round($payableDays, 2),
            'daily_rate' => round($dailyRate, 2),
            'estimated_basic_pay' => round($estimatedBasicPay, 2),
            'estimated_overtime_hours' => round($estimatedOvertimeHours, 2),
            'estimated_overtime_pay' => round($estimatedOvertimePay, 2),
            'estimated_gross_pay' => round($estimatedGrossPay, 2),
            'active_cash_advance_balance' => round($activeCashAdvanceBalance, 2),
            'recommended_limit' => round($recommendedLimit, 2),
            'available_balance' => round($estimatedGrossPay, 2),
        ]);
    }

    /**
     * Get list of unique projects from active employees
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
            Log::error('Error fetching projects: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch projects'], 500);
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
     * Get employees by project or position
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
