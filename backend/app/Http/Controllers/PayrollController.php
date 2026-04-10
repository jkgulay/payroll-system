<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeLoan;
use App\Models\LoanPayment;
use App\Models\DeductionPayment;
use App\Models\AuditLog;
use App\Models\EmployeeDeduction;
use App\Models\SalaryAdjustment;
use App\Models\CompanyInfo;
use App\Models\DeviceProfile;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }
    public function index()
    {
        $payrolls = Payroll::with([
            'creator:id,username,name',
            'finalizer:id,username,name',
        ])
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payrolls);
    }

    /**
     * Validate payroll creation - check for incomplete attendance records
     */
    public function validatePayrollCreation(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'payroll_scope' => 'nullable|in:all,individual',
            'individual_target' => 'nullable|in:position,employee',
            'included_position' => 'nullable|string',
            'included_employee_id' => 'nullable|integer|exists:employees,id',
            'has_attendance' => 'nullable|boolean',
            'excluded_positions' => 'nullable|array',
            'excluded_positions.*' => 'string',
        ]);

        $periodStart = $validated['period_start'];
        $periodEnd = $validated['period_end'];
        $payrollScope = $validated['payroll_scope'] ?? 'all';
        $individualTarget = $validated['individual_target'] ?? null;
        $includedPosition = $validated['included_position'] ?? null;
        $includedEmployeeId = $validated['included_employee_id'] ?? null;
        $hasAttendanceFilter = $validated['has_attendance'] ?? false;
        $excludedPositions = $validated['excluded_positions'] ?? [];

        if ($payrollScope === 'individual') {
            if (empty($individualTarget)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Please select whether to target a position or a specific employee.',
                ], 422);
            }

            if ($individualTarget === 'position' && empty($includedPosition)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Please select a position for individual payroll.',
                ], 422);
            }

            if ($individualTarget === 'employee' && empty($includedEmployeeId)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Please select an employee for individual payroll.',
                ], 422);
            }
        }

        // Step 1: Find employees that WILL be included in payroll
        $employeeQuery = Employee::query();

        if ($hasAttendanceFilter) {
            // Only check employees who have at least one attendance record
            $employeeQuery->whereHas('attendances', function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('attendance_date', [$periodStart, $periodEnd])
                    ->where('status', '!=', 'absent')
                    ->where('approval_status', 'approved')
                    ->whereNotNull('time_in')
                    ->where(function ($sub) {
                        $sub->whereNotNull('time_out')
                            ->orWhere('status', 'half_day');
                    });
            });
        } else {
            // Check all active/on_leave employees OR anyone with attendance
            $employeeQuery->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereIn('activity_status', ['active', 'on_leave'])
                    ->orWhereHas('attendances', function ($subQ) use ($periodStart, $periodEnd) {
                        $subQ->whereBetween('attendance_date', [$periodStart, $periodEnd])
                            ->where('status', '!=', 'absent');
                    });
            });
        }

        if ($payrollScope === 'individual') {
            if ($individualTarget === 'position' && !empty($includedPosition)) {
                $employeeQuery->whereHas('positionRate', function ($q) use ($includedPosition) {
                    $q->where('position_name', $includedPosition);
                });
            }

            if ($individualTarget === 'employee' && !empty($includedEmployeeId)) {
                $employeeQuery->where('id', $includedEmployeeId);
            }
        }

        if (!empty($excludedPositions)) {
            $employeeQuery->where(function ($q) use ($excludedPositions) {
                $q->whereDoesntHave('positionRate')
                    ->orWhereHas('positionRate', function ($positionQuery) use ($excludedPositions) {
                        $positionQuery->whereNotIn('position_name', $excludedPositions);
                    });
            });
        }

        // Candidate employees that this payroll scope/filter can include.
        // Do not require complete attendance here; we need to detect incomplete
        // punches for this full candidate set.
        $employeeIds = $employeeQuery->pluck('id');

        // Step 2: Find incomplete/missing attendance records for these employees (for Missing Attendance tab)
        // Explicitly include 'incomplete' status records so they appear in Missing Attendance tab.
        // The status='incomplete' means no time_out was recorded (already diagnosed by punch import).
        $incompleteRecords = Attendance::whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->whereIn('employee_id', $employeeIds)
            ->where(function ($q) {
                // Records already marked incomplete (no time_out) — show regardless of other fields
                $q->where('status', 'incomplete')
                    // OR missing time_in for payable statuses
                    ->orWhere(function ($sq) {
                        $sq->whereIn('status', ['present', 'late', 'half_day'])
                            ->whereNull('time_in');
                    })
                    // OR missing time_out (records with time_in but no time_out, excluding incomplete)
                    ->orWhere(function ($sq) {
                        $sq->whereNotNull('time_in')
                            ->whereNull('time_out')
                            ->where('status', '!=', 'incomplete');
                    })
                    // OR missing break_end when break_start exists
                    ->orWhere(function ($sq) {
                        $sq->whereNotNull('break_start')
                            ->whereNull('break_end');
                    })
                    // OR missing ot_time_out when ot_time_in exists
                    ->orWhere(function ($sq) {
                        $sq->whereNotNull('ot_time_in')
                            ->whereNull('ot_time_out');
                    })
                    // OR missing ot_time_out_2 when ot_time_in_2 exists
                    ->orWhere(function ($sq) {
                        $sq->whereNotNull('ot_time_in_2')
                            ->whereNull('ot_time_out_2');
                    });
            })
            ->with(['employee:id,employee_number,first_name,last_name'])
            ->get([
                'id',
                'employee_id',
                'attendance_date',
                'status',
                'time_in',
                'time_out',
                'break_start',
                'break_end',
                'ot_time_in',
                'ot_time_out',
                'ot_time_in_2',
                'ot_time_out_2'
            ]);

        // Format the incomplete records
        $issues = $incompleteRecords->map(function ($record) {
            $issuesList = [];

            // Missing time_in: check payable statuses (incomplete records may have null time_in too)
            if (!$record->time_in && in_array($record->status, ['present', 'late', 'half_day', 'incomplete'], true)) {
                $issuesList[] = 'Missing time in';
            }
            // Missing time_out: applies to any record with time_in but no time_out
            if ($record->time_in && !$record->time_out) {
                $issuesList[] = 'Missing time out';
            }
            if ($record->break_start && !$record->break_end) {
                $issuesList[] = 'Missing break end';
            }
            if ($record->ot_time_in && !$record->ot_time_out) {
                $issuesList[] = 'Missing OT time out';
            }
            if ($record->ot_time_in_2 && !$record->ot_time_out_2) {
                $issuesList[] = 'Missing OT2 time out';
            }

            return [
                'employee_number' => $record->employee->employee_number,
                'employee_name' => $record->employee->first_name . ' ' . $record->employee->last_name,
                'attendance_date' => Carbon::parse($record->attendance_date)->format('M d, Y'),
                'issues' => $issuesList,
                'attendance_id' => $record->id,
            ];
        });

        // Check if there are any incomplete records
        if ($issues->count() > 0) {
            return response()->json([
                'valid' => false,
                'message' => 'Some employees have incomplete attendance records',
                'incomplete_records' => $issues,
                'total_issues' => $issues->count(),
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => 'All attendance records are complete',
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'period_name' => 'required|string|max:255',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
            'payroll_scope' => 'nullable|in:all,individual',
            'individual_target' => 'nullable|in:position,employee',
            'included_position' => 'nullable|string',
            'included_employee_id' => 'nullable|integer|exists:employees,id',
            // Government deduction flags
            'deduct_sss' => 'nullable|boolean',
            'deduct_philhealth' => 'nullable|boolean',
            'deduct_pagibig' => 'nullable|boolean',
            // Only employees with attendance
            'has_attendance' => 'nullable|boolean',
            'excluded_positions' => 'nullable|array',
            'excluded_positions.*' => 'string',
            'overtime_employee_ids' => 'nullable|array',
            'overtime_employee_ids.*' => 'integer|exists:employees,id',
        ]);

        $overtimeEmployeeIds = $this->normalizeEmployeeIds($validated['overtime_employee_ids'] ?? []);

        // Always run attendance completeness validation before payroll creation.
        $validationRequest = new Request([
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'payroll_scope' => $validated['payroll_scope'] ?? 'all',
            'individual_target' => $validated['individual_target'] ?? null,
            'included_position' => $validated['included_position'] ?? null,
            'included_employee_id' => $validated['included_employee_id'] ?? null,
            'has_attendance' => $validated['has_attendance'] ?? false,
            'excluded_positions' => $validated['excluded_positions'] ?? [],
        ]);

        $validationResponse = $this->validatePayrollCreation($validationRequest);

        if ($validationResponse->status() === 422) {
            return $validationResponse;
        }

        DB::beginTransaction();
        try {
            $scopePayload = $this->normalizePayrollScopePayload($validated);

            $payroll = new Payroll([
                'period_name' => $validated['period_name'],
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'payment_date' => $validated['payment_date'],
                'status' => 'draft',
                'created_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null,
                'payroll_scope' => $scopePayload['payroll_scope'],
                'individual_target' => $scopePayload['individual_target'],
                'included_position' => $scopePayload['included_position'],
                'included_employee_id' => $scopePayload['included_employee_id'],
                'has_attendance' => $scopePayload['has_attendance'],
                'excluded_positions' => $scopePayload['excluded_positions'],
                'deduct_sss' => $validated['deduct_sss'] ?? true,
                'deduct_philhealth' => $validated['deduct_philhealth'] ?? true,
                'deduct_pagibig' => $validated['deduct_pagibig'] ?? true,
                'overtime_employee_ids' => !empty($overtimeEmployeeIds) ? $overtimeEmployeeIds : null,
            ]);

            // Explicitly save and ensure the ID is generated before proceeding
            $payroll->save();

            // Verify the payroll was saved and has an ID
            if (!$payroll->id) {
                throw new \Exception('Failed to generate payroll ID');
            }

            // Generate payroll items for all active employees
            $filters = array_merge($scopePayload, [
                'overtime_employee_ids' => $overtimeEmployeeIds,
            ]);
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
                    'overtime_employee_ids' => $payroll->overtime_employee_ids,
                    'items_count' => $payroll->items_count,
                ],
            ]);

            if (!empty($overtimeEmployeeIds)) {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'payroll',
                    'action' => 'payroll_overtime_employees_set',
                    'description' => "Payroll '{$payroll->period_name}' configured with " . count($overtimeEmployeeIds) . " overtime-eligible employee(s)",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'new_values' => [
                        'payroll_id' => $payroll->id,
                        'overtime_employee_ids' => $overtimeEmployeeIds,
                        'overtime_employee_count' => count($overtimeEmployeeIds),
                    ],
                ]);
            }

            return response()->json([
                'message' => 'Payroll created successfully',
                'payroll' => $payroll->load(['items.employee', 'creator']),
                'items_count' => $payroll->items_count,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating payroll: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to create payroll',
                'error' => $e->getMessage(),
                'details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    public function show($id)
    {
        $payroll = Payroll::with([
            'creator:id,username,name',
            'finalizer:id,username,name',
            'items' => function ($query) {
                $query->select([
                    'id',
                    'payroll_id',
                    'employee_id',
                    'rate',
                    'days_worked',
                    'regular_days',
                    'holiday_days',
                    'holiday_pay',
                    'basic_pay',
                    'regular_ot_hours',
                    'regular_ot_pay',
                    'special_ot_hours',
                    'special_ot_pay',
                    'sunday_hours',
                    'sunday_pay',
                    'salary_adjustment',
                    'allowances_breakdown',
                    'gross_pay',
                    'undertime_hours',
                    'undertime_deduction',
                    'sss',
                    'philhealth',
                    'pagibig',
                    'withholding_tax',
                    'employee_savings',
                    'cash_advance',
                    'loans',
                    'employee_deductions',
                    'other_deductions',
                    'total_deductions',
                    'net_pay',
                ])->orderBy('id');
            },
            'items.employee' => function ($query) {
                $query->select([
                    'id',
                    'employee_number',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'position_id',
                    'basic_salary',
                    'custom_pay_rate',
                    'has_sss',
                    'has_philhealth',
                    'has_pagibig',
                ]);
            },
            'items.employee.positionRate:id,position_name,daily_rate',
        ])
            ->findOrFail($id);

        return response()->json($payroll);
    }

    public function overtimeCandidates(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'payroll_scope' => 'nullable|in:all,individual',
            'individual_target' => 'nullable|in:position,employee',
            'included_position' => 'nullable|string',
            'included_employee_id' => 'nullable|integer|exists:employees,id',
            'has_attendance' => 'nullable|boolean',
            'excluded_positions' => 'nullable|array',
            'excluded_positions.*' => 'string',
        ]);

        $periodStart = $validated['period_start'];
        $periodEnd = $validated['period_end'];

        $employeeQuery = Employee::query()->where(function ($q) use ($periodStart, $periodEnd) {
            $q->whereIn('activity_status', ['active', 'on_leave'])
                ->orWhereHas('attendances', function ($subQ) use ($periodStart, $periodEnd) {
                    $subQ->whereBetween('attendance_date', [$periodStart, $periodEnd])
                        ->where('status', '!=', 'absent');
                });
        });

        if (($validated['has_attendance'] ?? false) === true) {
            $employeeQuery->whereHas('attendances', function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('attendance_date', [$periodStart, $periodEnd])
                    ->where('status', '!=', 'absent')
                    ->where('approval_status', 'approved')
                    ->whereNotNull('time_in')
                    ->where(function ($sub) {
                        $sub->whereNotNull('time_out')
                            ->orWhere('status', 'half_day');
                    });
            });
        }

        if (($validated['payroll_scope'] ?? 'all') === 'individual') {
            if (($validated['individual_target'] ?? null) === 'position' && !empty($validated['included_position'])) {
                $employeeQuery->whereHas('positionRate', function ($q) use ($validated) {
                    $q->where('position_name', $validated['included_position']);
                });
            }

            if (($validated['individual_target'] ?? null) === 'employee' && !empty($validated['included_employee_id'])) {
                $employeeQuery->where('id', $validated['included_employee_id']);
            }
        }

        if (!empty($validated['excluded_positions'])) {
            $employeeQuery->where(function ($q) use ($validated) {
                $q->whereDoesntHave('positionRate')
                    ->orWhereHas('positionRate', function ($positionQuery) use ($validated) {
                        $positionQuery->whereNotIn('position_name', $validated['excluded_positions']);
                    });
            });
        }

        $employees = $employeeQuery
            ->with('positionRate:id,position_name')
            ->orderBy('employee_number')
            ->get(['id', 'employee_number', 'first_name', 'last_name', 'activity_status']);

        $employeeIds = $employees->pluck('id');

        $attendanceByEmployee = Attendance::whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->where('status', '!=', 'absent')
            ->where('approval_status', 'approved')
            ->whereNotNull('time_in')
            ->where(function ($query) {
                $query->whereNotNull('time_out')
                    ->orWhere('status', 'half_day');
            })
            ->get([
                'id',
                'employee_id',
                'attendance_date',
                'time_in',
                'time_out',
                'ot_time_in',
                'ot_time_out',
                'ot_time_in_2',
                'ot_time_out_2',
                'overtime_hours',
                'regular_hours',
            ])
            ->groupBy('employee_id');

        $rows = $employees->map(function ($employee) use ($attendanceByEmployee) {
            $records = $attendanceByEmployee->get($employee->id, collect());

            $hasOvertimeRequest = $records->contains(function ($record) {
                return (float) ($record->overtime_hours ?? 0) > 0
                    || !empty($record->ot_time_in)
                    || !empty($record->ot_time_in_2);
            });

            return [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                'position' => $employee->positionRate->position_name ?? null,
                'activity_status' => $employee->activity_status,
                'attendance_days' => $records->count(),
                'total_regular_hours' => round((float) $records->sum('regular_hours'), 2),
                'total_overtime_hours' => round((float) $records->sum('overtime_hours'), 2),
                'has_overtime_request' => $hasOvertimeRequest,
            ];
        })->sortByDesc('has_overtime_request')->values();

        return response()->json([
            'employees' => $rows,
            'total' => $rows->count(),
        ]);
    }

    public function payrollPunchReview(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'payroll_scope' => 'nullable|in:all,individual',
            'individual_target' => 'nullable|in:position,employee',
            'included_position' => 'nullable|string',
            'included_employee_id' => 'nullable|integer|exists:employees,id',
            'has_attendance' => 'nullable|boolean',
            'excluded_positions' => 'nullable|array',
            'excluded_positions.*' => 'string',
            'date' => 'nullable|date',
            'employee_id' => 'nullable|integer|exists:employees,id',
        ]);

        $periodStart = $validated['period_start'];
        $periodEnd = $validated['period_end'];

        $employeeQuery = Employee::query()->where(function ($q) use ($periodStart, $periodEnd) {
            $q->whereIn('activity_status', ['active', 'on_leave'])
                ->orWhereHas('attendances', function ($subQ) use ($periodStart, $periodEnd) {
                    $subQ->whereBetween('attendance_date', [$periodStart, $periodEnd])
                        ->where('status', '!=', 'absent');
                });
        });

        if (($validated['has_attendance'] ?? false) === true) {
            $employeeQuery->whereHas('attendances', function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('attendance_date', [$periodStart, $periodEnd])
                    ->where('status', '!=', 'absent')
                    ->where('approval_status', 'approved')
                    ->whereNotNull('time_in')
                    ->where(function ($sub) {
                        $sub->whereNotNull('time_out')
                            ->orWhere('status', 'half_day');
                    });
            });
        }

        if (($validated['payroll_scope'] ?? 'all') === 'individual') {
            if (($validated['individual_target'] ?? null) === 'position' && !empty($validated['included_position'])) {
                $employeeQuery->whereHas('positionRate', function ($q) use ($validated) {
                    $q->where('position_name', $validated['included_position']);
                });
            }

            if (($validated['individual_target'] ?? null) === 'employee' && !empty($validated['included_employee_id'])) {
                $employeeQuery->where('id', $validated['included_employee_id']);
            }
        }

        if (!empty($validated['excluded_positions'])) {
            $employeeQuery->where(function ($q) use ($validated) {
                $q->whereDoesntHave('positionRate')
                    ->orWhereHas('positionRate', function ($positionQuery) use ($validated) {
                        $positionQuery->whereNotIn('position_name', $validated['excluded_positions']);
                    });
            });
        }

        $employeeIds = $employeeQuery->pluck('id');

        if ($employeeIds->isEmpty()) {
            return response()->json([
                'attendance' => [],
                'employees' => [],
                'total' => 0,
            ]);
        }

        $attendanceQuery = Attendance::query()
            ->with(['employee:id,employee_number,first_name,last_name'])
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->where('status', '!=', 'absent');

        if (!empty($validated['date'])) {
            $attendanceQuery->whereDate('attendance_date', $validated['date']);
        }

        if (!empty($validated['employee_id'])) {
            $attendanceQuery->where('employee_id', $validated['employee_id']);
        }

        $attendance = $attendanceQuery
            ->orderBy('attendance_date')
            ->orderBy('employee_id')
            ->get([
                'id',
                'employee_id',
                'attendance_date',
                'status',
                'approval_status',
                'time_in',
                'break_start',
                'break_end',
                'time_out',
                'ot_time_in',
                'ot_time_out',
                'ot_time_in_2',
                'ot_time_out_2',
                'device_name',
                'regular_hours',
                'overtime_hours',
                'undertime_hours',
                'late_hours',
                'updated_at',
            ]);

        $employees = $attendance
            ->pluck('employee')
            ->filter()
            ->unique('id')
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                ];
            })
            ->sortBy('employee_number', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'payroll',
                'action' => 'payroll_punch_review',
                'description' => "Reviewed payroll punch records for {$periodStart} to {$periodEnd}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'new_values' => [
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'payroll_scope' => $validated['payroll_scope'] ?? 'all',
                    'individual_target' => $validated['individual_target'] ?? null,
                    'included_position' => $validated['included_position'] ?? null,
                    'included_employee_id' => $validated['included_employee_id'] ?? null,
                    'has_attendance' => (bool) ($validated['has_attendance'] ?? false),
                    'excluded_positions' => $validated['excluded_positions'] ?? [],
                    'date_filter' => $validated['date'] ?? null,
                    'employee_filter' => $validated['employee_id'] ?? null,
                    'result_count' => $attendance->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Payroll Punch Review] Failed to create audit log: ' . $e->getMessage());
        }

        return response()->json([
            'attendance' => $attendance,
            'employees' => $employees,
            'total' => $attendance->count(),
        ]);
    }

    public function overtimeEmployeeAttendance(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        $rows = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$validated['period_start'], $validated['period_end']])
            ->orderBy('attendance_date')
            ->get([
                'id',
                'attendance_date',
                'status',
                'approval_status',
                'time_in',
                'time_out',
                'break_start',
                'break_end',
                'ot_time_in',
                'ot_time_out',
                'ot_time_in_2',
                'ot_time_out_2',
                'regular_hours',
                'overtime_hours',
                'undertime_hours',
                'late_hours',
            ]);

        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'payroll',
                'action' => 'payroll_overtime_attendance_view',
                'description' => "Viewed overtime attendance for {$employee->employee_number} ({$employee->full_name})",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'new_values' => [
                    'employee_id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'period_start' => $validated['period_start'],
                    'period_end' => $validated['period_end'],
                    'result_count' => $rows->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Payroll Overtime Attendance] Failed to create audit log: ' . $e->getMessage());
        }

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
            ],
            'attendance' => $rows,
        ]);
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
            'period_end' => 'sometimes|date|after_or_equal:' . (
                $request->has('period_start')
                ? 'period_start'
                : Carbon::parse($payroll->period_start)->toDateString()
            ),
            'payment_date' => 'sometimes|date',
            'notes' => 'nullable|string',
            'payroll_scope' => 'sometimes|in:all,individual',
            'individual_target' => 'nullable|in:position,employee',
            'included_position' => 'nullable|string',
            'included_employee_id' => 'nullable|integer|exists:employees,id',
            'has_attendance' => 'sometimes|boolean',
            'excluded_positions' => 'nullable|array',
            'excluded_positions.*' => 'string',
            'deduct_sss' => 'sometimes|boolean',
            'deduct_philhealth' => 'sometimes|boolean',
            'deduct_pagibig' => 'sometimes|boolean',
            'overtime_employee_ids' => 'nullable|array',
            'overtime_employee_ids.*' => 'integer|exists:employees,id',
        ]);

        $scopeFieldKeys = [
            'payroll_scope',
            'individual_target',
            'included_position',
            'included_employee_id',
            'has_attendance',
            'excluded_positions',
        ];
        $incomingScopeChanges = array_intersect_key($validated, array_flip($scopeFieldKeys));
        if (!empty($incomingScopeChanges)) {
            $normalizedScope = $this->normalizePayrollScopePayload(array_merge(
                $this->extractScopePayloadFromPayroll($payroll),
                $incomingScopeChanges
            ));

            if ($normalizedScope['payroll_scope'] === 'individual') {
                if (empty($normalizedScope['individual_target'])) {
                    return response()->json([
                        'message' => 'Please select whether to target a position or a specific employee.',
                    ], 422);
                }

                if ($normalizedScope['individual_target'] === 'position' && empty($normalizedScope['included_position'])) {
                    return response()->json([
                        'message' => 'Please select a position for individual payroll.',
                    ], 422);
                }

                if ($normalizedScope['individual_target'] === 'employee' && empty($normalizedScope['included_employee_id'])) {
                    return response()->json([
                        'message' => 'Please select an employee for individual payroll.',
                    ], 422);
                }
            }

            $validated = array_merge($validated, $normalizedScope);
        }

        if (array_key_exists('overtime_employee_ids', $validated)) {
            $validated['overtime_employee_ids'] = $this->normalizeEmployeeIds($validated['overtime_employee_ids'] ?? []);
            if (empty($validated['overtime_employee_ids'])) {
                $validated['overtime_employee_ids'] = null;
            }
        }

        $normalizeOvertimeIds = function ($value): array {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : [];
            }

            return collect(is_array($value) ? $value : [])
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->sort()
                ->values()
                ->all();
        };

        $oldOvertimeEmployeeIds = $normalizeOvertimeIds($payroll->overtime_employee_ids);

        $oldValues = $payroll->toArray();
        $recalculationFields = [
            'period_start',
            'period_end',
            'payment_date',
            'deduct_sss',
            'deduct_philhealth',
            'deduct_pagibig',
            'payroll_scope',
            'individual_target',
            'included_position',
            'included_employee_id',
            'has_attendance',
            'excluded_positions',
            'overtime_employee_ids',
        ];
        $shouldRecalculate = $this->hasRecalculationChanges($payroll, $validated, $recalculationFields);

        DB::beginTransaction();
        try {
            if ($shouldRecalculate) {
                // Reverse previously recorded payroll side effects before regenerating items.
                $this->reverseLoanPaymentsForPayroll($payroll);
                $this->reverseDeductionPaymentsForPayroll($payroll);
                $this->reverseSalaryAdjustmentsForPayroll($payroll);
                $this->reverseBonusesForPayroll($payroll);
            }

            $payroll->update($validated);

            if ($shouldRecalculate) {
                $payroll->refresh();
                $employeeIdsForRecalculation = $this->resolveEmployeeIdsForRecalculation($payroll);
                $payrollService = app(\App\Services\PayrollService::class);
                $payrollService->reprocessPayroll($payroll, $employeeIdsForRecalculation);

                // Re-apply loan/deduction progress after item regeneration.
                $this->recordPaymentsForPayroll($payroll);
            }

            $freshPayroll = $payroll->fresh()->loadCount('items');
            $newOvertimeEmployeeIds = $normalizeOvertimeIds($freshPayroll->overtime_employee_ids);
            $overtimeSelectionChanged = $oldOvertimeEmployeeIds !== $newOvertimeEmployeeIds;

            // Log payroll update
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'payroll',
                'action' => 'update_payroll',
                'description' => $shouldRecalculate
                    ? "Payroll '{$payroll->period_name}' updated and recalculated"
                    : "Payroll '{$payroll->period_name}' updated",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_values' => $oldValues,
                'new_values' => $freshPayroll->toArray(),
            ]);

            if ($overtimeSelectionChanged) {
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'payroll',
                    'action' => 'payroll_overtime_employees_updated',
                    'description' => "Payroll '{$freshPayroll->period_name}' overtime-eligible employees updated (" . count($oldOvertimeEmployeeIds) . ' -> ' . count($newOvertimeEmployeeIds) . ')',
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'old_values' => [
                        'payroll_id' => $freshPayroll->id,
                        'overtime_employee_ids' => $oldOvertimeEmployeeIds,
                        'overtime_employee_count' => count($oldOvertimeEmployeeIds),
                    ],
                    'new_values' => [
                        'payroll_id' => $freshPayroll->id,
                        'overtime_employee_ids' => $newOvertimeEmployeeIds,
                        'overtime_employee_count' => count($newOvertimeEmployeeIds),
                    ],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $shouldRecalculate
                    ? 'Payroll updated and recalculated successfully'
                    : 'Payroll updated successfully',
                'data' => $payroll->load(['items.employee', 'creator'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating payroll: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Payroll $payroll)
    {
        if ($payroll->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft payrolls can be deleted'
            ], 422);
        }

        $payrollData = $payroll->toArray();

        DB::beginTransaction();
        try {
            // Reverse loan, deduction, salary adjustment, and bonus payments before deleting
            $this->reverseLoanPaymentsForPayroll($payroll);
            $this->reverseDeductionPaymentsForPayroll($payroll);
            $this->reverseSalaryAdjustmentsForPayroll($payroll);
            $this->reverseBonusesForPayroll($payroll);

            // Permanently delete payroll items and payroll (no soft delete)
            $payroll->items()->delete();
            $payroll->forceDelete();

            DB::commit();

            // Log payroll deletion
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'payroll',
                'action' => 'delete_payroll',
                'description' => "Payroll '{$payrollData['period_name']}' (ID: {$payrollData['id']}) permanently deleted - all payments reversed",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'old_values' => $payrollData,
            ]);

            return response()->json([
                'message' => 'Payroll deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payroll deletion error: ' . $e->getMessage(), [
                'payroll_id' => $payroll->id,
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Error deleting payroll: ' . $e->getMessage()
            ], 500);
        }
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

        if ($payroll->status !== 'draft') {
            return response()->json([
                'message' => 'Cannot reprocess finalized or paid payrolls'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $employeeIdsForRecalculation = $this->resolveEmployeeIdsForRecalculation($payroll);

            // First, reverse any loan/deduction/salary adjustment/bonus payments that were recorded for this payroll
            $this->reverseLoanPaymentsForPayroll($payroll);
            $this->reverseDeductionPaymentsForPayroll($payroll);
            $this->reverseSalaryAdjustmentsForPayroll($payroll);
            $this->reverseBonusesForPayroll($payroll);

            $payrollService = app(\App\Services\PayrollService::class);
            $payrollService->reprocessPayroll($payroll, $employeeIdsForRecalculation);

            // Re-record loan and deduction payments for the reprocessed payroll items
            $this->recordPaymentsForPayroll($payroll);

            DB::commit();

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
            DB::rollBack();
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

        $user = auth()->user();

        // If user is an employee, they can only download their own payslip
        if ($user->role === 'employee') {
            if (!$user->employee_id) {
                return response()->json(['message' => 'No employee record linked to your account'], 403);
            }

            if ($user->employee_id !== $employeeId) {
                return response()->json(['message' => 'You can only download your own payslip'], 403);
            }
        }

        $payroll = Payroll::findOrFail($payrollId);

        $item = PayrollItem::where('payroll_id', $payrollId)
            ->where('employee_id', $employeeId)
            ->with('employee.positionRate')
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Payroll item not found'], 404);
        }

        // Get company info from database
        $companyInfo = CompanyInfo::first();

        // Long bond is default; A4 uses 97% scale to keep output close to long bond layout.
        $paperSize = strtolower((string) request()->query('paper_size', 'long_bond'));
        $isA4 = $paperSize === 'a4';
        $pageSizeCss = $isA4 ? 'A4 portrait' : '8.5in 13in';
        $pageScale = $isA4 ? 0.97 : 1;
        $pageMarginCss = $isA4 ? '6mm 8mm 6mm 8mm' : '6mm 10mm 6mm 8mm';
        $pageWidthPercent = $isA4 ? round(100 / $pageScale, 4) : 100;

        // Ensure installed-fonts.json exists in font cache directory
        $fontCache = storage_path('fonts');
        $installedFonts = $fontCache . '/installed-fonts.json';
        if (!file_exists($installedFonts)) {
            $distFonts = base_path('vendor/dompdf/dompdf/lib/fonts/installed-fonts.dist.json');
            if (file_exists($distFonts)) {
                copy($distFonts, $installedFonts);
            }
        }

        try {
            $pdf = Pdf::loadView('payroll.payslip', [
                'payroll' => $payroll,
                'item' => $item,
                'employee' => $item->employee,
                'companyInfo' => $companyInfo,
                'pageSizeCss' => $pageSizeCss,
                'pageScale' => $pageScale,
                'pageMarginCss' => $pageMarginCss,
                'pageWidthPercent' => $pageWidthPercent,
            ])->setPaper($isA4 ? 'A4' : [0, 0, 612, 936], 'portrait');

            return $pdf->download("payslip_{$item->employee->employee_number}_{$payroll->period_name}.pdf");
        } catch (\Throwable $e) {
            Log::error('Payslip PDF Error: ' . $e->getMessage(), [
                'payroll_id' => $payrollId,
                'employee_id' => $employeeId,
            ]);
            return response()->json([
                'message' => 'Failed to generate payslip PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download all payslips for a payroll in compact format (4 per page)
     */
    public function downloadPayslips(Request $request, Payroll $payroll)
    {
        // Increase memory limit for PDF generation
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', '300');

        // Validate filter parameters
        $validated = $request->validate([
            'filter_type' => 'nullable|in:all,department,position,both,employee',
            'departments' => 'nullable|array',
            'departments.*' => 'string',
            'positions' => 'nullable|array',
            'positions.*' => 'string',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:employees,id',
            'paper_size' => 'nullable|in:long_bond,a4',
        ]);

        $paperSize = $validated['paper_size'] ?? 'long_bond';
        $isA4 = $paperSize === 'a4';
        $pageSizeCss = $isA4 ? 'A4 portrait' : '8.5in 13in';
        $pageScale = $isA4 ? 0.97 : 1;
        $pageMarginCss = $isA4 ? '6mm 8mm 6mm 8mm' : '6mm 10mm 6mm 8mm';
        $pageWidthPercent = $isA4 ? round(100 / $pageScale, 4) : 100;

        // Load payroll items with employee relationship
        $itemsQuery = $payroll->items()->with(['employee.positionRate', 'employee.project']);

        // Apply filters if provided
        if (!empty($validated['filter_type']) && $validated['filter_type'] !== 'all') {
            if ($validated['filter_type'] === 'employee' && !empty($validated['employee_ids'])) {
                $itemsQuery->whereIn('employee_id', $validated['employee_ids']);
            } elseif ($validated['filter_type'] === 'both' && !empty($validated['departments']) && !empty($validated['positions'])) {
                // Filter by both department (project) AND position
                $itemsQuery->whereHas('employee', function ($q) use ($validated) {
                    $q->where(function ($q2) use ($validated) {
                        $q2->whereHas('project', function ($q3) use ($validated) {
                            $q3->whereIn('name', $validated['departments']);
                        })->orWhereIn('department', $validated['departments']);
                    })->whereHas('positionRate', function ($q2) use ($validated) {
                        $q2->whereIn('position_name', $validated['positions']);
                    });
                });
            } elseif ($validated['filter_type'] === 'department' && !empty($validated['departments'])) {
                $itemsQuery->whereHas('employee', function ($q) use ($validated) {
                    $q->where(function ($q2) use ($validated) {
                        $q2->whereHas('project', function ($q3) use ($validated) {
                            $q3->whereIn('name', $validated['departments']);
                        })->orWhereIn('department', $validated['departments']);
                    });
                });
            } elseif ($validated['filter_type'] === 'position' && !empty($validated['positions'])) {
                $itemsQuery->whereHas('employee.positionRate', function ($q) use ($validated) {
                    $q->whereIn('position_name', $validated['positions']);
                });
            }
        }

        // Get filtered items
        $payroll->setRelation('items', $itemsQuery->get());

        // Build filename
        $filenameBase = "payslips_{$payroll->payroll_number}";
        if (!empty($validated['filter_type']) && $validated['filter_type'] !== 'all') {
            $filenameBase .= '_filtered';
        }

        // Get company info from database
        $companyInfo = CompanyInfo::first();

        // Ensure installed-fonts.json exists in font cache directory
        $fontCache = storage_path('fonts');
        $installedFonts = $fontCache . '/installed-fonts.json';
        if (!file_exists($installedFonts)) {
            $distFonts = base_path('vendor/dompdf/dompdf/lib/fonts/installed-fonts.dist.json');
            if (file_exists($distFonts)) {
                copy($distFonts, $installedFonts);
            }
        }

        try {
            $pdf = Pdf::loadView('payroll.payslips-compact', [
                'payroll' => $payroll,
                'companyInfo' => $companyInfo,
                'pageSizeCss' => $pageSizeCss,
                'pageScale' => $pageScale,
                'pageMarginCss' => $pageMarginCss,
                'pageWidthPercent' => $pageWidthPercent,
            ])->setOptions([
                'isHtml5ParserEnabled'    => false,
                'isRemoteEnabled'         => false,
                'isFontSubsettingEnabled' => false,
            ])->setPaper($isA4 ? 'A4' : [0, 0, 612, 936], 'portrait');

            return $pdf->download($filenameBase . '.pdf');
        } catch (\Throwable $e) {
            Log::error('Payslips PDF Export Error: ' . $e->getMessage(), [
                'payroll_id' => $payroll->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to generate payslips PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadRegister(Request $request, Payroll $payroll)
    {
        // Increase memory limit for PDF generation
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', '300');

        // Validate filter parameters
        $validated = $request->validate([
            'filter_type' => 'nullable|in:all,department,position,both,employee',
            'departments' => 'nullable|array',
            'departments.*' => 'string',
            'positions' => 'nullable|array',
            'positions.*' => 'string',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:employees,id',
            'format' => 'nullable|in:pdf,by_device_pdf',
        ]);

        // Default to PDF if format not specified
        $format = $validated['format'] ?? 'pdf';

        // Load payroll items with employee relationship
        $itemsQuery = $payroll->items()->with(['employee.positionRate', 'employee.project']);

        // Apply filters if provided
        if (!empty($validated['filter_type']) && $validated['filter_type'] !== 'all') {
            if ($validated['filter_type'] === 'employee' && !empty($validated['employee_ids'])) {
                // Filter by specific employee IDs
                $itemsQuery->whereIn('employee_id', $validated['employee_ids']);
            } elseif ($validated['filter_type'] === 'both' && !empty($validated['departments']) && !empty($validated['positions'])) {
                // Filter by both department (project) AND position
                $itemsQuery->whereHas('employee', function ($q) use ($validated) {
                    $q->where(function ($q2) use ($validated) {
                        // Check project name first, then fallback to department text field
                        $q2->whereHas('project', function ($q3) use ($validated) {
                            $q3->whereIn('name', $validated['departments']);
                        })->orWhereIn('department', $validated['departments']);
                    })->whereHas('positionRate', function ($q2) use ($validated) {
                        $q2->whereIn('position_name', $validated['positions']);
                    });
                });
            } elseif ($validated['filter_type'] === 'department' && !empty($validated['departments'])) {
                $itemsQuery->whereHas('employee', function ($q) use ($validated) {
                    // Check project name first, then fallback to department text field
                    $q->where(function ($q2) use ($validated) {
                        $q2->whereHas('project', function ($q3) use ($validated) {
                            $q3->whereIn('name', $validated['departments']);
                        })->orWhereIn('department', $validated['departments']);
                    });
                });
            } elseif ($validated['filter_type'] === 'position' && !empty($validated['positions'])) {
                $itemsQuery->whereHas('employee.positionRate', function ($q) use ($validated) {
                    $q->whereIn('position_name', $validated['positions']);
                });
            }
        }

        // Get filtered items
        $payroll->setRelation('items', $itemsQuery->get());

        // Build filename base
        $filenameBase = "payroll_register_{$payroll->payroll_number}";
        $filterInfo = null;

        if (!empty($validated['filter_type']) && $validated['filter_type'] !== 'all') {
            if ($validated['filter_type'] === 'employee' && !empty($validated['employee_ids'])) {
                if (count($validated['employee_ids']) == 1) {
                    // Get employee name for single employee
                    $employee = Employee::find($validated['employee_ids'][0]);
                    if ($employee) {
                        $filterInfo = 'Employee: ' . $employee->first_name . ' ' . $employee->last_name;
                        $filenameBase .= '_' . str_replace(' ', '_', $employee->last_name);
                    }
                } else {
                    $filterInfo = 'Multiple Employees (' . count($validated['employee_ids']) . ')';
                    $filenameBase .= '_filtered';
                }
            } elseif ($validated['filter_type'] === 'department' && !empty($validated['departments'])) {
                if (count($validated['departments']) == 1) {
                    $filterInfo = 'Project: ' . implode(', ', $validated['departments']);
                }
            } elseif ($validated['filter_type'] === 'position' && !empty($validated['positions'])) {
                if (count($validated['positions']) == 1) {
                    $filterInfo = 'Position: ' . implode(', ', $validated['positions']);
                }
            } elseif ($validated['filter_type'] === 'both' && !empty($validated['departments']) && !empty($validated['positions'])) {
                $filterInfo = 'Filtered by Project & Position';
            }
            if ($filterInfo && !str_contains($filenameBase, '_filtered') && $validated['filter_type'] !== 'employee') {
                $filenameBase .= '_filtered';
            }
        }

        // Handle different export formats
        if ($format === 'by_device_pdf') {
            return $this->exportRegisterByDevicePdf($payroll, $filenameBase);
        } else {
            // Default PDF export
            return $this->exportRegisterToPdf($payroll, $validated, $filenameBase);
        }
    }

    private function exportRegisterToPdf(Payroll $payroll, array $validated, string $filenameBase)
    {
        try {
            // Group items by department or staff type if multiple filters selected
            $groupedItems = null;
            $filterInfo = null;
            $filterType = $validated['filter_type'] ?? 'all';
            $isIndividualPayroll = false;
            $individualEmployeeName = null;

            if (!empty($validated['filter_type']) && $validated['filter_type'] !== 'all') {
                if ($validated['filter_type'] === 'employee' && !empty($validated['employee_ids'])) {
                    if (count($validated['employee_ids']) == 1) {
                        // Get employee name for single employee
                        $employee = Employee::find($validated['employee_ids'][0]);
                        if ($employee) {
                            $filterInfo = 'Employee: ' . $employee->first_name . ' ' . $employee->last_name;
                            $isIndividualPayroll = true;
                            $individualEmployeeName = trim($employee->first_name . ' ' . $employee->last_name);
                        }
                    } else {
                        $filterInfo = 'Multiple Employees (' . count($validated['employee_ids']) . ')';
                    }
                } elseif ($validated['filter_type'] === 'department' && !empty($validated['departments'])) {
                    // Group by department if multiple departments selected
                    if (count($validated['departments']) > 1) {
                        $groupedItems = $payroll->items->groupBy(function ($item) {
                            return $item->employee->department ?? 'N/A';
                        });
                    } else {
                        $filterInfo = 'Project: ' . implode(', ', $validated['departments']);
                    }
                } elseif ($validated['filter_type'] === 'position' && !empty($validated['positions'])) {
                    // Group by position if multiple positions selected
                    if (count($validated['positions']) > 1) {
                        $groupedItems = $payroll->items->groupBy(function ($item) {
                            return $item->employee->positionRate->position_name ?? 'N/A';
                        });
                    } else {
                        $filterInfo = 'Position: ' . implode(', ', $validated['positions']);
                    }
                } elseif ($validated['filter_type'] === 'both' && !empty($validated['departments']) && !empty($validated['positions'])) {
                    $filterInfo = 'Project: ' . implode(', ', $validated['departments']) . ' | Position: ' . implode(', ', $validated['positions']);
                }
            }

            if (!$isIndividualPayroll && $payroll->items->count() === 1) {
                $isIndividualPayroll = true;
                $employee = $payroll->items->first()?->employee;
                if ($employee) {
                    $individualEmployeeName = $employee->full_name
                        ?? trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
                }
            }

            // Get company info from database
            $companyInfo = CompanyInfo::first();

            // Ensure installed-fonts.json exists in font cache directory
            $fontCache = storage_path('fonts');
            $installedFonts = $fontCache . '/installed-fonts.json';
            if (!file_exists($installedFonts)) {
                $distFonts = base_path('vendor/dompdf/dompdf/lib/fonts/installed-fonts.dist.json');
                if (file_exists($distFonts)) {
                    copy($distFonts, $installedFonts);
                }
            }

            $pdf = Pdf::loadView('payroll.register', compact('payroll', 'filterInfo', 'groupedItems', 'filterType', 'companyInfo', 'isIndividualPayroll', 'individualEmployeeName'))
                ->setOptions([
                    'isHtml5ParserEnabled'    => false,
                    'isRemoteEnabled'         => false,
                    'isFontSubsettingEnabled' => false,
                ])
                ->setPaper([0, 0, 612, 936], 'landscape'); // Long bond paper 8.5x13in landscape
            return $pdf->download($filenameBase . '.pdf');
        } catch (\Throwable $e) {
            Log::error('PDF Export Error: ' . $e->getMessage(), [
                'payroll_id' => $payroll->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function exportRegisterByDevicePdf(Payroll $payroll, string $filenameBase)
    {
        // Allow more memory for large multi-device PDFs (overrides the 1024M set upstream)
        ini_set('memory_limit', '2048M');

        try {
            // $groupedItems is keyed by device name — the register blade reuses the
            // per-group rendering logic (same as department grouping) to print a
            // section header per device.
            $groupedItems = $this->buildDeviceGroups($payroll);

            $companyInfo = CompanyInfo::first();
            $filterInfo  = null;
            $filterType  = 'device';
            $deviceMetaMap = DeviceProfile::query()
                ->get(['device_name', 'designation', 'location'])
                ->mapWithKeys(function ($profile) {
                    $key = strtolower(trim((string) $profile->device_name));
                    return [
                        $key => [
                            'designation' => $profile->designation,
                            'location' => $profile->location,
                        ],
                    ];
                })
                ->all();

            $fontCache      = storage_path('fonts');
            $installedFonts = $fontCache . '/installed-fonts.json';
            if (!file_exists($installedFonts)) {
                $distFonts = base_path('vendor/dompdf/dompdf/lib/fonts/installed-fonts.dist.json');
                if (file_exists($distFonts)) {
                    copy($distFonts, $installedFonts);
                }
            }

            $pdf = Pdf::loadView('payroll.register', compact('payroll', 'filterInfo', 'groupedItems', 'filterType', 'companyInfo', 'deviceMetaMap'))
                ->setOptions([
                    'isHtml5ParserEnabled'    => false,
                    'isRemoteEnabled'         => false,
                    'isFontSubsettingEnabled' => false,
                ])
                ->setPaper([0, 0, 612, 936], 'landscape');

            return $pdf->download($filenameBase . '_by_device.pdf');
        } catch (\Throwable $e) {
            Log::error('By-Device PDF Export Error: ' . $e->getMessage(), [
                'payroll_id' => $payroll->id,
                'trace'      => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to generate by-device PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build per-device groups with proportionally split payroll figures.
     *
     * Each employee appears under EVERY device they timed in at during the
     * payroll period. Their monetary / hour / day values are scaled by the
     * fraction of attendance days recorded on that device:
     *
     *   ratio = device_days / total_attendance_days_for_employee
     *
     * Examples
     *   4 days on Site A, 5 days on Site B → appears on both sheets.
     *   Site A gets  4/9 of gross_pay, days_worked, net_pay, etc.
     *   Site B gets  5/9 of those same totals.
     *
     * The daily rate is left unchanged (it is per-day, not a period total).
     * Employees with no attendance device record fall into "Unassigned".
     *
     * @return \Illuminate\Support\Collection  keyed by device name, Unassigned last
     */
    private function buildDeviceGroups(Payroll $payroll): \Illuminate\Support\Collection
    {
        $employeeIds = $payroll->items->pluck('employee_id')->unique();

        // Fetch attendance with device_hours JSON for accurate per-device splitting
        $attendances = Attendance::whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
            ->whereIn('employee_id', $employeeIds)
            ->where('status', '!=', 'absent')
            ->where('approval_status', 'approved')
            ->whereNotNull('time_in')
            ->where(function ($sub) {
                $sub->whereNotNull('time_out')
                    ->orWhere('status', 'half_day');
            })
            ->select(['employee_id', 'device_name', 'device_hours', 'regular_hours'])
            ->get();

        // Build: employee_id => [ device_name => total_hours ]
        $employeeDeviceHours = [];
        foreach ($attendances as $att) {
            $dh = $att->device_hours;
            if (!empty($dh) && is_array($dh)) {
                // Use granular per-device hours from the JSON column
                foreach ($dh as $dev => $hrs) {
                    $devName = ($dev !== '' && $dev !== null) ? $dev : 'Unassigned';
                    $employeeDeviceHours[$att->employee_id][$devName] =
                        ($employeeDeviceHours[$att->employee_id][$devName] ?? 0) + (float) $hrs;
                }
            } else {
                // Legacy records without device_hours — fall back to device_name
                $device = (isset($att->device_name) && $att->device_name !== '')
                    ? $att->device_name
                    : 'Unassigned';
                $hours = (float) ($att->regular_hours ?: 8);
                $employeeDeviceHours[$att->employee_id][$device] =
                    ($employeeDeviceHours[$att->employee_id][$device] ?? 0) + $hours;
            }
        }

        // These fields are period totals → scale proportionally by device ratio.
        // `effective_rate` / `rate` are daily rates and stay unchanged.
        $scalableFields = [
            'days_worked',
            'regular_days',
            'holiday_days',
            'basic_pay',
            'holiday_pay',
            'gross_pay',
            'net_pay',
            'regular_ot_hours',
            'regular_ot_pay',
            'special_ot_hours',
            'special_ot_pay',
            'salary_adjustment',
            'other_allowances',
            'undertime_hours',
            'undertime_deduction',
            'sss',
            'philhealth',
            'pagibig',
            'withholding_tax',
            'employee_savings',
            'cash_advance',
            'loans',
            'employee_deductions',
            'other_deductions',
            'total_deductions',
        ];

        $deviceGroups = [];

        foreach ($payroll->items as $item) {
            $empId     = $item->employee_id;
            $deviceMap = $employeeDeviceHours[$empId] ?? ['Unassigned' => 1];
            $totalHours = array_sum($deviceMap);

            foreach ($deviceMap as $device => $hours) {
                $ratio = $totalHours > 0 ? ($hours / $totalHours) : 1;

                // Clone the Eloquent model and scale each aggregate field
                $split = clone $item;
                $dayFields = ['days_worked', 'regular_days', 'holiday_days'];
                foreach ($scalableFields as $field) {
                    $raw = ($item->getAttribute($field) ?? 0) * $ratio;
                    // Day fields must stay in 0.5 increments (full day or half day)
                    $value = in_array($field, $dayFields) ? round($raw * 2) / 2 : round($raw, 4);
                    $split->setAttribute($field, $value);
                }

                $deviceGroups[$device][] = $split;
            }
        }

        // Sort alphabetically; keep "Unassigned" last
        uksort($deviceGroups, function ($a, $b) {
            if ($a === 'Unassigned') return 1;
            if ($b === 'Unassigned') return -1;
            return strnatcasecmp($a, $b);
        });

        return collect($deviceGroups)->map(fn($items) => collect($items));
    }

    private function generatePayrollItems(Payroll $payroll, array $filters = [])
    {
        // Safety check: prevent duplicate payroll items
        $existingItemsCount = PayrollItem::where('payroll_id', $payroll->id)->count();
        if ($existingItemsCount > 0) {
            Log::warning('Attempted to generate payroll items for payroll that already has items', [
                'payroll_id' => $payroll->id,
                'existing_items' => $existingItemsCount
            ]);
            throw new \Exception("Payroll already has {$existingItemsCount} items. Use reprocess to recalculate.");
        }

        if (config('app.debug')) {
            Log::debug('Payroll Generation', [
                'payroll_id' => $payroll->id,
                'period_start' => $payroll->period_start,
                'period_end' => $payroll->period_end,
                'filters' => $filters,
                'has_attendance' => $filters['has_attendance'] ?? 'not set'
            ]);
        }

        $query = $this->buildEmployeeSelectionQuery($payroll, $filters);

        // CRITICAL: Eager load attendances filtered by payroll period
        // Without this, calculatePayrollItem() would process ALL attendance records
        $query->with([
            'attendances' => function ($q) use ($payroll) {
                $q->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
                    ->where('status', '!=', 'absent')
                    ->where('approval_status', 'approved')
                    ->whereNotNull('time_in')
                    ->where(function ($sub) {
                        // Include records with time_out OR half-day records
                        // (half-day entries may lack time_out but have valid regular_hours)
                        $sub->whereNotNull('time_out')
                            ->orWhere('status', 'half_day');
                    });
            },
            'allowances' => function ($q) use ($payroll) {
                $q->where('is_active', true)
                    ->where('effective_date', '<=', $payroll->period_end)
                    ->where(function ($query) use ($payroll) {
                        $query->whereNull('end_date')
                            ->orWhere('end_date', '>=', $payroll->period_start);
                    });
            },
            'salaryAdjustments' => function ($q) use ($payroll) {
                $q->where('status', 'pending')
                    ->where(function ($query) use ($payroll) {
                        $query->whereNull('effective_date')
                            ->orWhere('effective_date', '<=', $payroll->period_end);
                    });
            },
            'loans' => function ($q) use ($payroll) {
                $q->where('status', 'active')
                    ->where('balance', '>', 0)
                    ->where(function ($query) use ($payroll) {
                        $query->whereNull('first_payment_date')
                            ->orWhere('first_payment_date', '<=', $payroll->period_end);
                    })
                    ->where(function ($query) use ($payroll) {
                        $query->whereNull('maturity_date')
                            ->orWhere('maturity_date', '>=', $payroll->period_start);
                    });
            },
            'deductions' => function ($q) use ($payroll) {
                $q->where('status', 'active')
                    ->where('balance', '>', 0)
                    ->where('start_date', '<=', $payroll->period_end)
                    ->where(function ($query) use ($payroll) {
                        $query->whereNull('end_date')
                            ->orWhere('end_date', '>=', $payroll->period_start);
                    });
            },
            // Pending bonuses with payment_date within payroll period
            'bonuses' => function ($q) use ($payroll) {
                $q->where('payment_status', 'pending')
                    ->where('payment_date', '>=', $payroll->period_start)
                    ->where('payment_date', '<=', $payroll->period_end);
            },
            // Meal allowance items (via approved MealAllowance within period)
            'mealAllowanceItems' => function ($q) use ($payroll) {
                $q->whereHas('mealAllowance', function ($mq) use ($payroll) {
                    $mq->where('status', 'approved')
                        ->where('period_start', '<=', $payroll->period_end)
                        ->where('period_end', '>=', $payroll->period_start);
                });
            },
            'positionRate',
        ]);

        // Order by employee number for consistent selection
        $query->orderBy('employee_number');

        $employees = $query->get();

        if (config('app.debug')) {
            Log::debug('Employees found: ' . $employees->count());
        }

        if ($employees->isEmpty()) {
            $errorMsg = 'No employees found for this payroll period';

            // Provide more specific error message
            if (!empty($filters['has_attendance'])) {
                $errorMsg .= '. Note: The "Only include employees with attendance" option is enabled, but no active employees have attendance records for this period.';
            } else {
                $errorMsg .= '. No active or on-leave employees found with activity during this period.';
            }

            // Throw a regular exception with detailed message
            throw new \Exception($errorMsg);
        }

        // Load holidays for the payroll period (including recurring holidays)
        // Uses PayrollService method which handles recurring holidays by month/day matching
        $holidays = $this->payrollService->getHolidaysForPeriod($payroll->period_start, $payroll->period_end);

        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;
        $allAdjustmentIds = []; // Track salary adjustment IDs to mark as applied
        $allBonusIds = []; // Track bonus IDs to mark as paid
        $selectedOvertimeEmployeeIds = $this->normalizeEmployeeIds($filters['overtime_employee_ids'] ?? []);

        foreach ($employees as $employee) {
            try {
                // Use PayrollService for holiday-aware calculation
                $includeOvertime = empty($selectedOvertimeEmployeeIds)
                    ? true
                    : in_array((int) $employee->id, $selectedOvertimeEmployeeIds, true);

                $item = $this->payrollService->calculatePayrollItem($payroll, $employee, $holidays, [
                    'include_overtime' => $includeOvertime,
                ]);

                // Keep create flow consistent with PayrollService::processPayroll()
                // so reprocessing does not change employee inclusion unexpectedly.
                if ($item['gross_pay'] <= 0) {
                    continue;
                }

                // Validate calculated values before creating record
                if (!is_numeric($item['gross_pay']) || !is_finite($item['gross_pay'])) {
                    throw new \Exception("Invalid gross_pay calculated for employee {$employee->employee_number}: {$item['gross_pay']}");
                }
                if (!is_numeric($item['net_pay']) || !is_finite($item['net_pay'])) {
                    throw new \Exception("Invalid net_pay calculated for employee {$employee->employee_number}: {$item['net_pay']}");
                }

                // Collect salary adjustment IDs before removing internal field
                if (!empty($item['_adjustment_ids'])) {
                    $allAdjustmentIds = array_merge($allAdjustmentIds, $item['_adjustment_ids']);
                }
                // Collect bonus IDs before removing internal field
                if (!empty($item['_bonus_ids'])) {
                    $allBonusIds = array_merge($allBonusIds, $item['_bonus_ids']);
                }
                unset($item['_adjustment_ids']);
                unset($item['_bonus_ids']);

                $payrollItem = PayrollItem::create($item);

                // Record deduction installments for this employee
                $this->recordDeductionInstallments($payroll, $employee, $payrollItem);

                // Record loan payments for this employee
                $this->recordLoanPayments($payroll, $employee, $payrollItem);

                $totalGross += $item['gross_pay'];
                $totalDeductions += $item['total_deductions'];
                $totalNet += $item['net_pay'];
            } catch (\Exception $e) {
                Log::error('Error calculating payroll for employee: ' . $employee->employee_number, [
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                    'item_data' => $item ?? null
                ]);
                throw new \Exception("Failed to calculate payroll for employee {$employee->employee_number}: " . $e->getMessage());
            }
        }

        // Mark salary adjustments as applied AFTER all items are created successfully
        if (!empty($allAdjustmentIds)) {
            SalaryAdjustment::whereIn('id', $allAdjustmentIds)
                ->update([
                    'status' => 'applied',
                    'applied_payroll_id' => $payroll->id,
                ]);
        }

        // Mark bonuses as paid AFTER all items are created successfully
        if (!empty($allBonusIds)) {
            \App\Models\EmployeeBonus::whereIn('id', $allBonusIds)
                ->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
        }

        $payroll->update([
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet,
        ]);
    }

    private function normalizeEmployeeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizePayrollScopePayload(array $payload): array
    {
        $scope = ($payload['payroll_scope'] ?? 'all') === 'individual' ? 'individual' : 'all';
        $target = $payload['individual_target'] ?? null;

        $includedPosition = isset($payload['included_position'])
            ? trim((string) $payload['included_position'])
            : null;
        if ($includedPosition === '') {
            $includedPosition = null;
        }

        $includedEmployeeId = isset($payload['included_employee_id']) && $payload['included_employee_id'] !== ''
            ? (int) $payload['included_employee_id']
            : null;

        $excludedPositions = collect($payload['excluded_positions'] ?? [])
            ->filter(fn($position) => is_string($position) && trim($position) !== '')
            ->map(fn($position) => trim((string) $position))
            ->unique()
            ->values()
            ->all();

        $normalized = [
            'payroll_scope' => $scope,
            'individual_target' => $target,
            'included_position' => $includedPosition,
            'included_employee_id' => $includedEmployeeId,
            'has_attendance' => (bool) ($payload['has_attendance'] ?? false),
            'excluded_positions' => $excludedPositions,
        ];

        if ($scope !== 'individual') {
            $normalized['individual_target'] = null;
            $normalized['included_position'] = null;
            $normalized['included_employee_id'] = null;
            return $normalized;
        }

        if ($target === 'position') {
            $normalized['included_employee_id'] = null;
            return $normalized;
        }

        if ($target === 'employee') {
            $normalized['included_position'] = null;
            return $normalized;
        }

        $normalized['individual_target'] = null;
        $normalized['included_position'] = null;
        $normalized['included_employee_id'] = null;

        return $normalized;
    }

    private function extractScopePayloadFromPayroll(Payroll $payroll): array
    {
        $excludedPositions = $payroll->excluded_positions;
        if (is_string($excludedPositions)) {
            $decoded = json_decode($excludedPositions, true);
            $excludedPositions = is_array($decoded) ? $decoded : [];
        }

        return $this->normalizePayrollScopePayload([
            'payroll_scope' => $payroll->payroll_scope,
            'individual_target' => $payroll->individual_target,
            'included_position' => $payroll->included_position,
            'included_employee_id' => $payroll->included_employee_id,
            'has_attendance' => (bool) ($payroll->has_attendance ?? false),
            'excluded_positions' => is_array($excludedPositions) ? $excludedPositions : [],
        ]);
    }

    private function buildEmployeeSelectionQuery(Payroll $payroll, array $filters)
    {
        $normalizedFilters = $this->normalizePayrollScopePayload($filters);

        // Include employees who were working during the payroll period
        // This includes: active employees, on_leave employees, or anyone with attendance during the period
        $query = Employee::where(function ($q) use ($payroll) {
            $q->whereIn('activity_status', ['active', 'on_leave'])
                ->orWhereHas('attendances', function ($subQ) use ($payroll) {
                    $subQ->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
                        ->where('status', '!=', 'absent');
                });
        });

        if (!empty($normalizedFilters['has_attendance'])) {
            $query->whereHas('attendances', function ($q) use ($payroll) {
                $q->whereBetween('attendance_date', [$payroll->period_start, $payroll->period_end])
                    ->where('status', '!=', 'absent')
                    ->where('approval_status', 'approved')
                    ->whereNotNull('time_in')
                    ->where(function ($sub) {
                        $sub->whereNotNull('time_out')
                            ->orWhere('status', 'half_day');
                    });
            });
        }

        if ($normalizedFilters['payroll_scope'] === 'individual') {
            if ($normalizedFilters['individual_target'] === 'position' && !empty($normalizedFilters['included_position'])) {
                $query->whereHas('positionRate', function ($positionQuery) use ($normalizedFilters) {
                    $positionQuery->where('position_name', $normalizedFilters['included_position']);
                });
            }

            if ($normalizedFilters['individual_target'] === 'employee' && !empty($normalizedFilters['included_employee_id'])) {
                $query->where('id', $normalizedFilters['included_employee_id']);
            }
        }

        if (!empty($normalizedFilters['excluded_positions'])) {
            $query->where(function ($q) use ($normalizedFilters) {
                $q->whereDoesntHave('positionRate')
                    ->orWhereHas('positionRate', function ($positionQuery) use ($normalizedFilters) {
                        $positionQuery->whereNotIn('position_name', $normalizedFilters['excluded_positions']);
                    });
            });
        }

        return $query;
    }

    private function resolveEmployeeIdsForRecalculation(Payroll $payroll): array
    {
        if (!$this->hasPersistedScopeMetadata($payroll)) {
            $legacyEmployeeIds = $payroll->items()
                ->pluck('employee_id')
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (!empty($legacyEmployeeIds)) {
                return $legacyEmployeeIds;
            }

            return $this->buildEmployeeSelectionQuery($payroll, [
                'payroll_scope' => 'all',
                'has_attendance' => false,
                'excluded_positions' => [],
            ])
                ->orderBy('employee_number')
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        return $this->buildEmployeeSelectionQuery($payroll, $this->extractScopePayloadFromPayroll($payroll))
            ->orderBy('employee_number')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function hasPersistedScopeMetadata(Payroll $payroll): bool
    {
        $metadataFields = [
            'payroll_scope',
            'individual_target',
            'included_position',
            'included_employee_id',
            'has_attendance',
            'excluded_positions',
        ];

        foreach ($metadataFields as $field) {
            if ($payroll->getRawOriginal($field) !== null) {
                return true;
            }
        }

        return false;
    }

    private function hasRecalculationChanges(Payroll $payroll, array $validated, array $fields): bool
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $validated)) {
                continue;
            }

            $currentValue = $payroll->{$field};
            $newValue = $validated[$field];

            if (in_array($field, ['excluded_positions', 'overtime_employee_ids'], true)) {
                $normalizeArray = function ($value): array {
                    if (is_string($value)) {
                        $decoded = json_decode($value, true);
                        $value = is_array($decoded) ? $decoded : [];
                    }

                    return collect(is_array($value) ? $value : [])
                        ->map(fn($item) => is_scalar($item) ? trim((string) $item) : null)
                        ->filter(fn($item) => $item !== null && $item !== '')
                        ->values()
                        ->all();
                };

                if ($normalizeArray($currentValue) !== $normalizeArray($newValue)) {
                    return true;
                }

                continue;
            }

            if (in_array($field, ['deduct_sss', 'deduct_philhealth', 'deduct_pagibig', 'has_attendance'], true)) {
                if ((bool) $currentValue !== (bool) $newValue) {
                    return true;
                }

                continue;
            }

            if ($field === 'included_employee_id') {
                if ((int) ($currentValue ?? 0) !== (int) ($newValue ?? 0)) {
                    return true;
                }

                continue;
            }

            if (in_array($field, ['period_start', 'period_end', 'payment_date'], true)) {
                $normalizedCurrentDate = $currentValue ? Carbon::parse($currentValue)->toDateString() : null;
                $normalizedNewDate = $newValue ? Carbon::parse($newValue)->toDateString() : null;

                if ($normalizedCurrentDate !== $normalizedNewDate) {
                    return true;
                }

                continue;
            }

            if ((string) ($currentValue ?? '') !== (string) ($newValue ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record all loan and deduction payments for a payroll
     * This is used after payroll processing to update loan/deduction balances
     */
    private function recordPaymentsForPayroll(Payroll $payroll)
    {
        // Load payroll items with employees
        $payrollItems = PayrollItem::where('payroll_id', $payroll->id)
            ->with('employee')
            ->get();

        foreach ($payrollItems as $payrollItem) {
            if ($payrollItem->employee) {
                // Record deduction installments for this employee
                $this->recordDeductionInstallments($payroll, $payrollItem->employee, $payrollItem);

                // Record loan payments for this employee
                $this->recordLoanPayments($payroll, $payrollItem->employee, $payrollItem);
            }
        }
    }

    /**
     * Record deduction installment payments when payroll is generated
     */
    private function recordDeductionInstallments(Payroll $payroll, Employee $employee, ?PayrollItem $payrollItem = null)
    {
        // Government deductions are computed from GovernmentRate settings, not manual deduction rows.
        $governmentManagedDeductionTypes = ['sss', 'philhealth', 'pagibig', 'tax'];

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
            if (in_array($deduction->deduction_type, $governmentManagedDeductionTypes, true)) {
                continue;
            }

            // Calculate payment amount (use amount_per_cutoff, but not more than balance)
            $amountPerCutoff = $deduction->amount_per_cutoff;
            if (!$amountPerCutoff && $deduction->installments > 0) {
                $amountPerCutoff = $deduction->total_amount / $deduction->installments;
            }
            $paymentAmount = min($amountPerCutoff, $deduction->balance);

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

                // Track exact deduction payments tied to this payroll for precise reversal.
                if (Schema::hasTable('deduction_payments')) {
                    $nextInstallmentNumber = DeductionPayment::where('employee_deduction_id', $deduction->id)
                        ->max('installment_number');
                    $nextInstallmentNumber = $nextInstallmentNumber ? ((int) $nextInstallmentNumber + 1) : 1;

                    DeductionPayment::create([
                        'employee_deduction_id' => $deduction->id,
                        'payroll_id' => $payroll->id,
                        'payroll_item_id' => $payrollItem?->id,
                        'payment_date' => $payroll->payment_date,
                        'amount' => $paymentAmount,
                        'balance_after_payment' => (float) ($updateData['balance'] ?? $newBalance),
                        'installment_number' => $nextInstallmentNumber,
                        'remarks' => "Payroll deduction for {$payroll->period_name}",
                    ]);
                }
            }
        }
    }

    /**
     * Record loan payments when payroll is generated
     */
    private function recordLoanPayments(Payroll $payroll, Employee $employee, PayrollItem $payrollItem)
    {
        // Determine if this is semi-monthly payroll (typically 15 days or less)
        $periodStart = \Carbon\Carbon::parse($payroll->period_start);
        $periodEnd = \Carbon\Carbon::parse($payroll->period_end);
        $periodDays = $periodStart->diffInDays($periodEnd) + 1;
        $isSemiMonthly = $periodDays <= 16;

        // For monthly loans on semi-monthly payrolls: only deduct on 2nd cutoff
        // 2nd cutoff = period ending on 16th or later of the month
        $isSecondCutoff = !$isSemiMonthly || $periodEnd->day >= 16;

        // Get active loans for this employee with balance > 0 and payment already due
        $activeLoans = EmployeeLoan::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where('balance', '>', 0)
            ->where(function ($query) use ($payroll) {
                $query->whereNull('first_payment_date')
                    ->orWhere('first_payment_date', '<=', $payroll->period_end);
            })
            ->where(function ($query) use ($payroll) {
                // Loan hasn't matured yet (maturity date is null or after payroll period starts)
                $query->whereNull('maturity_date')
                    ->orWhere('maturity_date', '>=', $payroll->period_start);
            })
            ->get();

        foreach ($activeLoans as $loan) {
            // Determine payment amount based on payroll period and loan payment frequency
            $paymentAmount = 0;
            if ($loan->payment_frequency === 'semi_monthly') {
                // Semi-monthly loans: use semi_monthly_amortization for semi-monthly payrolls
                // For monthly payrolls, use monthly_amortization (2 payments combined)
                $paymentAmount = $isSemiMonthly
                    ? ($loan->semi_monthly_amortization ?? 0)
                    : ($loan->monthly_amortization ?? 0);
            } else {
                // Monthly loans: deduct on 2nd cutoff only (period end day >= 16)
                // This ensures monthly loans are only deducted once per month
                if ($isSecondCutoff) {
                    $paymentAmount = $loan->monthly_amortization ?? 0;
                }
            }

            // Don't pay more than the remaining balance
            $paymentAmount = min($paymentAmount, $loan->balance);

            if ($paymentAmount > 0) {
                $newAmountPaid = $loan->amount_paid + $paymentAmount;
                $newBalance = $loan->balance - $paymentAmount;

                $updateData = [
                    'amount_paid' => $newAmountPaid,
                    'balance' => $newBalance,
                ];

                // Mark as paid if balance is zero or reaches total amount
                if ($newBalance <= 0.01 || $newAmountPaid >= $loan->total_amount) {
                    $updateData['status'] = 'paid';
                    $updateData['balance'] = 0; // Ensure balance is exactly zero
                    $updateData['amount_paid'] = $loan->total_amount; // Ensure exact total
                }

                $loan->update($updateData);

                // Record loan payment linked to this payroll
                $nextPaymentNumber = LoanPayment::where('employee_loan_id', $loan->id)->max('payment_number');
                $nextPaymentNumber = $nextPaymentNumber ? $nextPaymentNumber + 1 : 1;

                LoanPayment::create([
                    'employee_loan_id' => $loan->id,
                    'payroll_id' => $payroll->id,
                    'payroll_item_id' => $payrollItem->id,
                    'payment_date' => $payroll->payment_date,
                    'amount' => $paymentAmount,
                    'principal_payment' => $paymentAmount,
                    'interest_payment' => 0,
                    'balance_after_payment' => $updateData['balance'] ?? $newBalance,
                    'payment_number' => $nextPaymentNumber,
                    'payment_method' => 'payroll_deduction',
                    'remarks' => "Payroll deduction for {$payroll->period_name}",
                ]);

                // Log loan payment
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'loans',
                    'action' => 'loan_payment',
                    'description' => "Loan payment of ₱" . number_format($paymentAmount, 2) . " deducted from payroll for {$employee->first_name} {$employee->last_name} (Loan: {$loan->loan_number})",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'old_values' => [
                        'amount_paid' => $loan->amount_paid - $paymentAmount,
                        'balance' => $loan->balance + $paymentAmount,
                        'status' => $loan->status === 'paid' ? 'active' : $loan->status,
                    ],
                    'new_values' => [
                        'amount_paid' => $newAmountPaid,
                        'balance' => $newBalance,
                        'status' => $updateData['status'] ?? 'active',
                        'payment_amount' => $paymentAmount,
                        'payroll_id' => $payroll->id,
                        'payroll_period' => $payroll->period_name,
                    ],
                ]);
            }
        }
    }

    /**
     * Reverse loan payments for a payroll (used when reprocessing)
     */
    private function reverseLoanPaymentsForPayroll(Payroll $payroll)
    {
        $loanPayments = LoanPayment::where('payroll_id', $payroll->id)->get();

        if ($loanPayments->isNotEmpty()) {
            foreach ($loanPayments as $payment) {
                $loan = EmployeeLoan::find($payment->employee_loan_id);
                if (!$loan) {
                    continue;
                }

                $newAmountPaid = max(0, $loan->amount_paid - $payment->amount);
                $newBalance = min($loan->total_amount, $loan->balance + $payment->amount);

                $updateData = [
                    'amount_paid' => $newAmountPaid,
                    'balance' => $newBalance,
                ];

                if ($loan->status === 'paid' && $newBalance > 0) {
                    $updateData['status'] = 'active';
                }

                $loan->update($updateData);
            }

            // Remove payment records for this payroll after reversal
            LoanPayment::where('payroll_id', $payroll->id)->delete();
            return;
        }

        // Determine if this is semi-monthly payroll
        $periodStart = \Carbon\Carbon::parse($payroll->period_start);
        $periodEnd = \Carbon\Carbon::parse($payroll->period_end);
        $periodDays = $periodStart->diffInDays($periodEnd) + 1;
        $isSemiMonthly = $periodDays <= 16;

        // For monthly loans on semi-monthly payrolls: only deduct on 2nd cutoff
        $isSecondCutoff = !$isSemiMonthly || $periodEnd->day >= 16;

        // Get all employees in this payroll
        $employeeIds = PayrollItem::where('payroll_id', $payroll->id)->pluck('employee_id');

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::find($employeeId);
            if (!$employee) continue;

            // Get active or paid loans that might have been updated by this payroll
            $loans = EmployeeLoan::where('employee_id', $employee->id)
                ->whereIn('status', ['active', 'paid'])
                ->where(function ($query) use ($payroll) {
                    $query->whereNull('first_payment_date')
                        ->orWhere('first_payment_date', '<=', $payroll->period_end);
                })
                ->where(function ($query) use ($payroll) {
                    $query->whereNull('maturity_date')
                        ->orWhere('maturity_date', '>=', $payroll->period_start);
                })
                ->get();

            foreach ($loans as $loan) {
                // Calculate what payment amount would have been recorded
                $paymentAmount = 0;
                if ($loan->payment_frequency === 'semi_monthly') {
                    // Semi-monthly loans: use semi_monthly_amortization for semi-monthly payrolls
                    // For monthly payrolls, use monthly_amortization (2 payments combined)
                    $paymentAmount = $isSemiMonthly
                        ? ($loan->semi_monthly_amortization ?? 0)
                        : ($loan->monthly_amortization ?? 0);
                } else {
                    // Monthly loans: deduct on 2nd cutoff only
                    if ($isSecondCutoff) {
                        $paymentAmount = $loan->monthly_amortization ?? 0;
                    }
                }

                if ($paymentAmount > 0 && $loan->amount_paid >= $paymentAmount) {
                    // Reverse the payment
                    $newAmountPaid = $loan->amount_paid - $paymentAmount;
                    $newBalance = $loan->balance + $paymentAmount;

                    $updateData = [
                        'amount_paid' => $newAmountPaid,
                        'balance' => min($newBalance, $loan->total_amount), // Don't exceed total
                    ];

                    // If loan was marked as paid, revert to active
                    if ($loan->status === 'paid' && $newBalance > 0) {
                        $updateData['status'] = 'active';
                    }

                    $loan->update($updateData);
                }
            }
        }
    }

    /**
     * Reverse salary adjustments for a payroll (used when reprocessing/deleting)
     */
    private function reverseSalaryAdjustmentsForPayroll(Payroll $payroll)
    {
        if (!Schema::hasTable('salary_adjustments') || !Schema::hasColumn('salary_adjustments', 'applied_payroll_id')) {
            Log::warning('Skipping salary adjustment reversal: applied_payroll_id column missing', [
                'payroll_id' => $payroll->id,
            ]);
            return;
        }

        SalaryAdjustment::where('applied_payroll_id', $payroll->id)
            ->where('status', 'applied')
            ->update([
                'status' => 'pending',
                'applied_payroll_id' => null,
            ]);
    }

    /**
     * Reverse bonus payments for a payroll (used when reprocessing/deleting)
     * Bonuses that were marked as 'paid' by this payroll are reset to 'pending'
     */
    private function reverseBonusesForPayroll(Payroll $payroll)
    {
        // Find bonuses that were paid during this payroll period
        // Match by employee + payment_date within the payroll period + status = paid
        $employeeIds = PayrollItem::where('payroll_id', $payroll->id)->pluck('employee_id');

        if ($employeeIds->isEmpty()) {
            return;
        }

        // Also check allowances_breakdown in payroll items for specific bonus IDs
        $bonusIds = [];
        $payrollItems = PayrollItem::where('payroll_id', $payroll->id)->get();
        foreach ($payrollItems as $item) {
            $breakdown = $item->allowances_breakdown;
            if (is_array($breakdown)) {
                foreach ($breakdown as $entry) {
                    if (isset($entry['type']) && $entry['type'] === 'bonus' && isset($entry['id'])) {
                        $bonusIds[] = $entry['id'];
                    }
                }
            }
        }

        if (!empty($bonusIds)) {
            // Precise reversal: use the exact bonus IDs stored in the payroll item breakdown
            \App\Models\EmployeeBonus::whereIn('id', $bonusIds)
                ->where('payment_status', 'paid')
                ->update([
                    'payment_status' => 'pending',
                    'paid_at' => null,
                ]);
        } else {
            // Fallback: reverse bonuses by employee + period matching
            \App\Models\EmployeeBonus::whereIn('employee_id', $employeeIds)
                ->where('payment_status', 'paid')
                ->where('payment_date', '>=', $payroll->period_start)
                ->where('payment_date', '<=', $payroll->period_end)
                ->update([
                    'payment_status' => 'pending',
                    'paid_at' => null,
                ]);
        }
    }

    /**
     * Reverse deduction payments for a payroll (used when reprocessing)
     */
    private function reverseDeductionPaymentsForPayroll(Payroll $payroll)
    {
        if (Schema::hasTable('deduction_payments')) {
            $deductionPayments = DeductionPayment::where('payroll_id', $payroll->id)
                ->orderByDesc('id')
                ->get();

            if ($deductionPayments->isNotEmpty()) {
                foreach ($deductionPayments as $payment) {
                    $deduction = EmployeeDeduction::find($payment->employee_deduction_id);
                    if (!$deduction) {
                        continue;
                    }

                    $newInstallmentsPaid = max(0, (int) $deduction->installments_paid - 1);
                    $newBalance = min(
                        (float) $deduction->total_amount,
                        (float) $deduction->balance + (float) $payment->amount
                    );

                    $updateData = [
                        'installments_paid' => $newInstallmentsPaid,
                        'balance' => $newBalance,
                    ];

                    if ($deduction->status === 'completed' && $newBalance > 0) {
                        $updateData['status'] = 'active';
                    }

                    $deduction->update($updateData);
                }

                DeductionPayment::where('payroll_id', $payroll->id)->delete();
                return;
            }
        }

        // Legacy fallback path (for payrolls created before deduction_payments existed):
        // reconstruct exact applied amounts from payroll item deductions_breakdown.
        $deductionAdjustments = [];
        $payrollItems = PayrollItem::where('payroll_id', $payroll->id)
            ->get(['deductions_breakdown']);

        foreach ($payrollItems as $item) {
            $breakdown = $item->deductions_breakdown;
            if (!is_array($breakdown)) {
                continue;
            }

            foreach ($breakdown as $entry) {
                $deductionId = (int) ($entry['id'] ?? 0);
                $amount = (float) ($entry['amount'] ?? 0);

                if ($deductionId <= 0 || $amount <= 0) {
                    continue;
                }

                if (!isset($deductionAdjustments[$deductionId])) {
                    $deductionAdjustments[$deductionId] = [
                        'amount' => 0,
                        'installments' => 0,
                    ];
                }

                $deductionAdjustments[$deductionId]['amount'] += $amount;
                $deductionAdjustments[$deductionId]['installments'] += 1;
            }
        }

        if (!empty($deductionAdjustments)) {
            foreach ($deductionAdjustments as $deductionId => $adjustment) {
                $deduction = EmployeeDeduction::find($deductionId);
                if (!$deduction) {
                    continue;
                }

                $newInstallmentsPaid = max(0, (int) $deduction->installments_paid - (int) $adjustment['installments']);
                $newBalance = min(
                    (float) $deduction->total_amount,
                    (float) $deduction->balance + (float) $adjustment['amount']
                );

                $updateData = [
                    'installments_paid' => $newInstallmentsPaid,
                    'balance' => $newBalance,
                ];

                if ($deduction->status === 'completed' && $newBalance > 0) {
                    $updateData['status'] = 'active';
                }

                $deduction->update($updateData);
            }

            return;
        }

        // Get all employees in this payroll
        $employeeIds = PayrollItem::where('payroll_id', $payroll->id)->pluck('employee_id');

        foreach ($employeeIds as $employeeId) {
            // Get active or completed deductions
            $deductions = \App\Models\EmployeeDeduction::where('employee_id', $employeeId)
                ->whereIn('status', ['active', 'completed'])
                ->where('start_date', '<=', $payroll->period_end)
                ->where(function ($query) use ($payroll) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $payroll->period_start);
                })
                ->get();

            foreach ($deductions as $deduction) {
                // Calculate what payment amount would have been recorded
                $amountPerCutoff = $deduction->amount_per_cutoff;
                if (!$amountPerCutoff && $deduction->installments > 0) {
                    $amountPerCutoff = $deduction->total_amount / $deduction->installments;
                }
                $paymentAmount = min($amountPerCutoff, $deduction->total_amount - $deduction->balance);

                if ($paymentAmount > 0 && $deduction->installments_paid > 0) {
                    $newInstallmentsPaid = max(0, $deduction->installments_paid - 1);
                    $newBalance = $deduction->balance + $paymentAmount;

                    $updateData = [
                        'installments_paid' => $newInstallmentsPaid,
                        'balance' => min($newBalance, $deduction->total_amount),
                    ];

                    // If deduction was marked as completed, revert to active
                    if ($deduction->status === 'completed' && $newBalance > 0) {
                        $updateData['status'] = 'active';
                    }

                    $deduction->update($updateData);
                }
            }
        }
    }
}
