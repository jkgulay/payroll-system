<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resignation;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\Attendance;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeLoan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ResignationController extends Controller
{
    private function isResignationManager($user): bool
    {
        return in_array($user->role, ['admin', 'hr'], true);
    }

    private function canUseMyResignationPortal($user): bool
    {
        return in_array($user->role, ['employee', 'payrollist'], true);
    }

    private function resolveUserEmployeeId($user): ?int
    {
        if (!empty($user->employee_id)) {
            return (int) $user->employee_id;
        }

        return Employee::where('user_id', $user->id)->value('id');
    }

    private function ensureCanAccessResignation($user, Resignation $resignation)
    {
        if ($this->isResignationManager($user)) {
            return null;
        }

        $employeeId = $this->resolveUserEmployeeId($user);

        if (!$employeeId || (int) $resignation->employee_id !== (int) $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return null;
    }

    private function applyListFilters($query, Request $request, bool $includeStatus = true)
    {
        if ($includeStatus && $request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date')) {
            $query->where('resignation_date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('resignation_date', '<=', $request->input('end_date'));
        }

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('reason', 'like', "%{$search}%")
                    ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                        $employeeQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    /**
     * Display a listing of resignations
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$this->isResignationManager($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $query = Resignation::withRelations();

        $this->applyListFilters($query, $request, true);

        $resignations = $query->latest('created_at')->paginate($perPage);

        return response()->json($resignations);
    }

    /**
     * Get resignation stats for dashboard cards.
     */
    public function stats(Request $request)
    {
        $user = Auth::user();

        if (!$this->isResignationManager($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $baseQuery = Resignation::query();
        $this->applyListFilters($baseQuery, $request, false);

        return response()->json([
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'total' => (clone $baseQuery)->count(),
        ]);
    }

    /**
     * Get current user's most recent resignation.
     */
    public function myResignation(Request $request)
    {
        $user = Auth::user();

        if (!$this->canUseMyResignationPortal($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $employeeId = $this->resolveUserEmployeeId($user);

        if (!$employeeId) {
            return response()->json([
                'message' => 'Employee profile not found for the current user.',
            ], 404);
        }

        $resignation = Resignation::where('employee_id', $employeeId)
            ->withRelations()
            ->latest('created_at')
            ->first();

        if (!$resignation) {
            return response()->json([
                'message' => 'No resignation found for this employee.',
            ], 404);
        }

        return response()->json($resignation);
    }

    /**
     * Store a new resignation (Employee submits)
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $isManager = $this->isResignationManager($user);

        if (!$isManager && !$this->canUseMyResignationPortal($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rules = [
            'last_working_day' => 'required|date|after:today',
            'reason' => 'nullable|string|max:1000',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240', // Max 10MB per file
        ];

        if ($isManager) {
            $rules['employee_id'] = 'required|exists:employees,id';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employeeId = $isManager
            ? (int) $request->input('employee_id')
            : $this->resolveUserEmployeeId($user);

        if (!$employeeId) {
            return response()->json([
                'message' => 'Employee profile not found for the current user.',
            ], 404);
        }

        // Check if employee already has a pending or approved resignation
        $existingResignation = Resignation::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingResignation) {
            return response()->json([
                'message' => 'Employee already has a pending or approved resignation.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Handle file uploads
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $fileName = time() . '_' . uniqid() . '_' . $originalName;
                    $filePath = $file->storeAs('resignations', $fileName, 'public');

                    $attachments[] = [
                        'original_name' => $originalName,
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'uploaded_at' => now()->toDateTimeString(),
                    ];
                }
            }

            $resignation = Resignation::create([
                'employee_id' => $employeeId,
                'resignation_date' => now(),
                'last_working_day' => $request->input('last_working_day'),
                'effective_date' => $request->input('last_working_day'), // Default to requested date
                'reason' => $request->input('reason'),
                'attachments' => !empty($attachments) ? $attachments : null,
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Resignation submitted successfully.',
                'resignation' => $resignation->load('employee'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to submit resignation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resignation
     */
    public function show($id)
    {
        $user = Auth::user();
        $resignation = Resignation::withRelations()->findOrFail($id);

        $authError = $this->ensureCanAccessResignation($user, $resignation);
        if ($authError) {
            return $authError;
        }

        return response()->json($resignation);
    }

    /**
     * Update resignation (HR can modify)
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!$this->isResignationManager($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $resignation = Resignation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'effective_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:1000',
            'status' => 'nullable|in:pending,approved,rejected,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $resignation->update([
                'effective_date' => $request->effective_date ?? $resignation->effective_date,
                'remarks' => $request->remarks ?? $resignation->remarks,
                'status' => $request->status ?? $resignation->status,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Resignation updated successfully.',
                'resignation' => $resignation->load('employee'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update resignation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve resignation (HR)
     */
    public function approve(Request $request, $id)
    {
        $user = Auth::user();

        if (!$this->isResignationManager($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $resignation = Resignation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'effective_date' => 'nullable|date',
            'remarks' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($resignation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending resignations can be approved.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $effectiveDate = $request->effective_date ?? $resignation->last_working_day;

            $resignation->update([
                'status' => 'approved',
                'effective_date' => $effectiveDate,
                'remarks' => $request->remarks,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            // Update employee status
            $employee = $resignation->employee;
            $employee->update([
                'resignation_id' => $resignation->id,
            ]);

            $this->closeActiveEmployeeSavingsPlans($employee, $effectiveDate, (int) Auth::id());

            DB::commit();

            return response()->json([
                'message' => 'Resignation approved successfully.',
                'resignation' => $resignation->load('employee', 'processedBy'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to approve resignation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject resignation (HR)
     */
    public function reject(Request $request, $id)
    {
        $user = Auth::user();

        if (!$this->isResignationManager($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $resignation = Resignation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'remarks' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($resignation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending resignations can be rejected.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $resignation->update([
                'status' => 'rejected',
                'remarks' => $request->remarks,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Resignation rejected.',
                'resignation' => $resignation->load('employee', 'processedBy'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to reject resignation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process final pay and complete resignation
     */
    public function processFinalPay(Request $request, $id)
    {
        $user = Auth::user();

        if (!$this->isResignationManager($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $resignation = Resignation::findOrFail($id);

        if ($resignation->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved resignations can be processed for final pay.',
            ], 422);
        }

        try {
            $employee = $resignation->employee;
            $previewOnly = $request->boolean('preview_only');

            $effectiveDate = Carbon::parse($resignation->effective_date ?? now())->startOfDay();
            $finalPayAsOfDate = $effectiveDate->isFuture()
                ? Carbon::now()->startOfDay()
                : $effectiveDate;

            $attendanceBackPay = $this->calculateAttendanceBackPay($employee, $finalPayAsOfDate);

            // Calculate 13th month pay
            $thirteenthMonthAmount = $this->calculate13thMonthPay($employee, $resignation->effective_date);

            // Calculate unused leave credits (if applicable)
            $unusedLeaveAmount = $this->calculateUnusedLeaveAmount($employee);

            // Calculate employee savings (contributed amount less remaining target)
            $employeeSavingsAmount = $this->calculateEmployeeSavingsPayout($employee);

            // Compute liabilities to deduct from final pay
            $liabilities = $this->calculateOutstandingLiabilities($employee);

            // Get any remaining salary/adjustments
            $remainingSalary = (float) $request->input('remaining_salary', 0);

            $grossFinalPay =
                $attendanceBackPay['amount'] +
                $thirteenthMonthAmount +
                $unusedLeaveAmount +
                $employeeSavingsAmount +
                $remainingSalary;

            // Calculate total final pay
            $finalPayAmount = $grossFinalPay - $liabilities['total'];

            $breakdown = [
                'unpaid_attendance_salary' => $attendanceBackPay['amount'],
                'unpaid_attendance_days' => $attendanceBackPay['payable_days'],
                'unpaid_attendance_start' => $attendanceBackPay['start_date'],
                'unpaid_attendance_end' => $attendanceBackPay['end_date'],
                'thirteenth_month_pay' => $thirteenthMonthAmount,
                'unused_leave' => $unusedLeaveAmount,
                'employee_savings' => $employeeSavingsAmount,
                'remaining_salary' => $remainingSalary,
                'gross_total' => $grossFinalPay,
                'outstanding_loans' => $liabilities['loans'],
                'outstanding_deductions' => $liabilities['deductions'],
                'total_deductions' => $liabilities['total'],
                'total' => $finalPayAmount,
            ];

            if ($previewOnly) {
                return response()->json([
                    'message' => 'Final pay preview generated successfully.',
                    'preview_only' => true,
                    'resignation' => $resignation->load('employee'),
                    'breakdown' => $breakdown,
                ]);
            }

            DB::beginTransaction();

            $resignation->update([
                'thirteenth_month_amount' => $thirteenthMonthAmount,
                'final_pay_amount' => $finalPayAmount,
                'final_pay_released' => false,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Final pay calculated successfully.',
                'resignation' => $resignation->load('employee'),
                'breakdown' => $breakdown,
            ]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'message' => 'Failed to process final pay.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Release final pay
     */
    public function releaseFinalPay($id)
    {
        $user = Auth::user();

        if (!$this->isResignationManager($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $resignation = Resignation::findOrFail($id);

        if ($resignation->status !== 'approved') {
            return response()->json([
                'message' => 'Only approved resignations can have final pay released.',
            ], 422);
        }

        if (!$resignation->final_pay_amount) {
            return response()->json([
                'message' => 'Final pay must be calculated before releasing.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $resignation->update([
                'status' => 'completed',
                'final_pay_released' => true,
                'final_pay_release_date' => now(),
            ]);

            // Update employee status to resigned
            $employee = $resignation->employee;
            $employee->update([
                'activity_status' => 'resigned',
                'date_separated' => $resignation->effective_date,
                'separation_reason' => 'resignation',
                'is_active' => false,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Final pay released successfully. Employee status updated to resigned.',
                'resignation' => $resignation->load('employee'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to release final pay.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employee's resignation status
     */
    public function getEmployeeResignation($employeeId)
    {
        $user = Auth::user();
        $isManager = $this->isResignationManager($user);

        if (!$isManager) {
            if (!$this->canUseMyResignationPortal($user)) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $currentEmployeeId = $this->resolveUserEmployeeId($user);

            if (!$currentEmployeeId || (int) $currentEmployeeId !== (int) $employeeId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $resignation = Resignation::where('employee_id', $employeeId)
            ->withRelations()
            ->latest('created_at')
            ->first();

        if (!$resignation) {
            return response()->json([
                'message' => 'No resignation found for this employee.',
            ], 404);
        }

        return response()->json($resignation);
    }

    /**
     * Calculate pro-rated 13th month pay
     */
    private function calculate13thMonthPay(Employee $employee, $effectiveDate)
    {
        $currentYear = Carbon::parse($effectiveDate)->year;
        $startDate = $currentYear . '-01-01';
        $endDate = Carbon::parse($effectiveDate)->format('Y-m-d');

        // Get total basic salary for the period
        $totalBasicPay = PayrollItem::whereHas('payroll', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate])
                ->whereIn('status', ['paid', 'approved', 'finalized']);
        })
            ->where('employee_id', $employee->id)
            ->sum('basic_pay');

        // Calculate 13th month pay (basic salary / 12)
        return $totalBasicPay / 12;
    }

    /**
     * Calculate unused leave credits amount
     */
    private function calculateUnusedLeaveAmount(Employee $employee)
    {
        // Get unused leave credits
        $unusedLeaves = $employee->leaveCredits()
            ->where('balance', '>', 0)
            ->sum('balance');

        if ($unusedLeaves <= 0) {
            return 0;
        }

        // Calculate daily rate
        $dailyRate = $this->resolveDailyRate($employee);

        // Convert unused leaves to cash
        return $unusedLeaves * $dailyRate;
    }

    /**
     * Resolve employee salary to daily-equivalent rate.
     */
    private function resolveDailyRate(Employee $employee): float
    {
        $basicSalary = (float) $employee->getBasicSalary();
        $salaryType = (string) ($employee->salary_type ?? 'daily');
        $workingDaysPerMonth = max((float) config('payroll.working_days_per_month', 22), 1);
        $standardHours = max((float) config('payroll.standard_hours_per_day', 8), 1);

        if ($salaryType === 'monthly') {
            return $basicSalary / $workingDaysPerMonth;
        }

        if ($salaryType === 'hourly') {
            return $basicSalary * $standardHours;
        }

        return $basicSalary;
    }

    /**
     * Resolve start date for unpaid attendance back pay window.
     */
    private function resolveAttendanceBackPayStartDate(Employee $employee): Carbon
    {
        $lastCoveredPeriodEnd = PayrollItem::query()
            ->join('payrolls', 'payrolls.id', '=', 'payroll_items.payroll_id')
            ->where('payroll_items.employee_id', $employee->id)
            ->whereNull('payrolls.deleted_at')
            ->whereNotNull('payrolls.period_end')
            ->whereIn('payrolls.status', ['paid', 'approved', 'finalized'])
            ->orderByDesc('payrolls.period_end')
            ->value('payrolls.period_end');

        if ($lastCoveredPeriodEnd) {
            return Carbon::parse($lastCoveredPeriodEnd)->addDay()->startOfDay();
        }

        if (!empty($employee->date_hired)) {
            return Carbon::parse($employee->date_hired)->startOfDay();
        }

        return Carbon::parse($employee->created_at ?? now())->startOfDay();
    }

    /**
     * Calculate unpaid attendance salary after the most recent paid payroll period.
     */
    private function calculateAttendanceBackPay(Employee $employee, Carbon $asOfDate): array
    {
        $startDate = $this->resolveAttendanceBackPayStartDate($employee);

        if ($startDate->gt($asOfDate)) {
            return [
                'amount' => 0,
                'payable_days' => 0,
                'start_date' => $startDate->toDateString(),
                'end_date' => $asOfDate->toDateString(),
            ];
        }

        $standardHours = max((float) config('payroll.standard_hours_per_day', 8), 1);
        $dayUnitsByDate = [];

        $attendanceRows = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$startDate->toDateString(), $asOfDate->toDateString()])
            ->where(function ($query) {
                $query->where('regular_hours', '>', 0)
                    ->orWhere('overtime_hours', '>', 0)
                    ->orWhereIn('status', ['present', 'late', 'half_day']);
            })
            ->orderBy('attendance_date')
            ->get(['attendance_date', 'status', 'regular_hours', 'overtime_hours']);

        foreach ($attendanceRows as $attendance) {
            $dateKey = $attendance->attendance_date instanceof Carbon
                ? $attendance->attendance_date->toDateString()
                : Carbon::parse($attendance->attendance_date)->toDateString();

            $regularHours = max((float) ($attendance->regular_hours ?? 0), 0);
            $dayUnits = 0.0;

            if ($regularHours > 0) {
                $dayUnits = min(1, $regularHours / $standardHours);
            } elseif ((string) $attendance->status === 'half_day') {
                $dayUnits = 0.5;
            } elseif (in_array((string) $attendance->status, ['present', 'late'], true)) {
                $dayUnits = 1.0;
            }

            if (!isset($dayUnitsByDate[$dateKey]) || $dayUnits > $dayUnitsByDate[$dateKey]) {
                $dayUnitsByDate[$dateKey] = $dayUnits;
            }
        }

        $payableDays = round(array_sum($dayUnitsByDate), 2);
        $amount = round($payableDays * $this->resolveDailyRate($employee), 2);

        return [
            'amount' => $amount,
            'payable_days' => $payableDays,
            'start_date' => $startDate->toDateString(),
            'end_date' => $asOfDate->toDateString(),
        ];
    }

    /**
     * Get totals for outstanding employee liabilities to deduct in final pay.
     */
    private function calculateOutstandingLiabilities(Employee $employee): array
    {
        $loanTotal = (float) EmployeeLoan::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['active', 'approved'])
            ->where('balance', '>', 0)
            ->sum('balance');

        $deductionTotal = (float) EmployeeDeduction::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->whereNotIn('deduction_type', ['cooperative', 'sss', 'philhealth', 'pagibig', 'tax', 'cash_bond'])
            ->sum('balance');

        return [
            'loans' => round($loanTotal, 2),
            'deductions' => round($deductionTotal, 2),
            'total' => round($loanTotal + $deductionTotal, 2),
        ];
    }

    /**
     * Get total contributed employee savings amount.
     */
    private function calculateEmployeeSavingsPayout(Employee $employee): float
    {
        $totalContributed = EmployeeDeduction::query()
            ->where('employee_id', $employee->id)
            ->where('deduction_type', 'cooperative')
            ->whereIn('status', ['active', 'completed'])
            ->get(['total_amount', 'balance'])
            ->reduce(function (float $carry, EmployeeDeduction $plan) {
                $contributed = max(0, (float) $plan->total_amount - (float) $plan->balance);
                return $carry + $contributed;
            }, 0.0);

        return round($totalContributed, 2);
    }

    /**
     * Stop active employee savings deductions once resignation is approved.
     */
    private function closeActiveEmployeeSavingsPlans(Employee $employee, $effectiveDate, ?int $processedBy = null): void
    {
        $normalizedEffectiveDate = Carbon::parse($effectiveDate)->toDateString();

        $activeSavingsPlans = EmployeeDeduction::query()
            ->where('employee_id', $employee->id)
            ->where('deduction_type', 'cooperative')
            ->where('status', 'active')
            ->get();

        foreach ($activeSavingsPlans as $plan) {
            $autoNote = "Auto-closed due to approved resignation (effective {$normalizedEffectiveDate}).";
            $existingNotes = trim((string) ($plan->notes ?? ''));

            if ($existingNotes === '') {
                $updatedNotes = $autoNote;
            } elseif (stripos($existingNotes, 'Auto-closed due to approved resignation') !== false) {
                $updatedNotes = $existingNotes;
            } else {
                $updatedNotes = $existingNotes . PHP_EOL . $autoNote;
            }

            $plan->update([
                'status' => 'completed',
                'end_date' => $normalizedEffectiveDate,
                'notes' => $updatedNotes,
                'approved_by' => $plan->approved_by ?: $processedBy,
                'approved_at' => $plan->approved_at ?: now(),
            ]);
        }
    }

    /**
     * Resolve attachment path and support legacy metadata formats.
     */
    private function resolveStoredAttachmentPath(array $attachment): ?string
    {
        $candidates = [];

        if (!empty($attachment['file_path']) && is_string($attachment['file_path'])) {
            $normalizedPath = ltrim(str_replace('\\', '/', $attachment['file_path']), '/');
            $candidates[] = $normalizedPath;

            if (str_starts_with($normalizedPath, 'public/')) {
                $candidates[] = substr($normalizedPath, 7);
            }
        }

        if (!empty($attachment['file_name']) && is_string($attachment['file_name'])) {
            $candidates[] = 'resignations/' . ltrim(str_replace('\\', '/', $attachment['file_name']), '/');
        }

        if (!empty($attachment['original_name']) && is_string($attachment['original_name'])) {
            $candidates[] = 'resignations/' . ltrim(str_replace('\\', '/', $attachment['original_name']), '/');
        }

        foreach (array_unique(array_filter($candidates)) as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Delete resignation (only if pending and before approval)
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $resignation = Resignation::findOrFail($id);

        $authError = $this->ensureCanAccessResignation($user, $resignation);
        if ($authError) {
            return $authError;
        }

        if ($resignation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending resignations can be deleted.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Delete attachments from storage
            if ($resignation->attachments) {
                foreach ($resignation->attachments as $attachment) {
                    $storedPath = $this->resolveStoredAttachmentPath((array) $attachment);
                    if ($storedPath) {
                        Storage::disk('public')->delete($storedPath);
                    }
                }
            }

            $resignation->delete();

            DB::commit();

            return response()->json([
                'message' => 'Resignation withdrawn successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to withdraw resignation.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download resignation attachment
     */
    public function downloadAttachment($id, $attachmentIndex)
    {
        $user = Auth::user();
        $resignation = Resignation::findOrFail($id);

        $authError = $this->ensureCanAccessResignation($user, $resignation);
        if ($authError) {
            return $authError;
        }

        if (!$resignation->attachments || !isset($resignation->attachments[$attachmentIndex])) {
            return response()->json([
                'message' => 'Attachment not found.',
            ], 404);
        }

        $attachment = (array) $resignation->attachments[$attachmentIndex];
        $storedPath = $this->resolveStoredAttachmentPath($attachment);

        if (!$storedPath) {
            return response()->json([
                'message' => 'File not found on server.',
            ], 404);
        }

        $absolutePath = storage_path('app/public/' . $storedPath);
        $downloadName = isset($attachment['original_name'])
            ? (string) $attachment['original_name']
            : basename($storedPath);
        $safeDownloadName = str_replace(["\r", "\n", '"'], '', $downloadName);
        $mimeType = isset($attachment['mime_type'])
            ? (string) $attachment['mime_type']
            : ((is_file($absolutePath) ? mime_content_type($absolutePath) : null) ?: 'application/octet-stream');

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $safeDownloadName . '"',
        ]);
    }

    /**
     * Delete a specific attachment from resignation
     */
    public function deleteAttachment($id, $attachmentIndex)
    {
        $user = Auth::user();
        $resignation = Resignation::findOrFail($id);

        $authError = $this->ensureCanAccessResignation($user, $resignation);
        if ($authError) {
            return $authError;
        }

        // Only allow deletion if resignation is still pending
        if ($resignation->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete attachments from non-pending resignations.',
            ], 422);
        }

        if (!$resignation->attachments || !isset($resignation->attachments[$attachmentIndex])) {
            return response()->json([
                'message' => 'Attachment not found.',
            ], 404);
        }

        DB::beginTransaction();

        try {
            $attachments = $resignation->attachments;
            $deletedAttachment = (array) $attachments[$attachmentIndex];

            // Delete file from storage
            $storedPath = $this->resolveStoredAttachmentPath($deletedAttachment);
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            // Remove from array
            array_splice($attachments, $attachmentIndex, 1);

            // Update resignation
            $resignation->update([
                'attachments' => !empty($attachments) ? $attachments : null,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Attachment deleted successfully.',
                'resignation' => $resignation->load('employee'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete attachment.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
