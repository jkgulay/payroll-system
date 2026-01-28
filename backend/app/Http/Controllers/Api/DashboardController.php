<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\EmployeeApplication;
use App\Models\EmployeeLeave;
use App\Models\AttendanceCorrection;
use App\Models\AuditLog;
use App\Models\Resignation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        return $this->index($request);
    }

    public function index(Request $request)
    {
        // Count all active employees (is_active = true)
        $totalEmployees = Employee::where('is_active', true)->count();

        // Count active employees with 'active' activity status
        $activeStatus = Employee::where('is_active', true)
            ->where('activity_status', 'active')
            ->count();

        // Get today's date
        $today = Carbon::now()->format('Y-m-d');

        // Count employees present today
        $presentToday = Attendance::whereDate('attendance_date', $today)
            ->whereIn('status', ['present', 'late'])
            ->distinct('employee_id')
            ->count('employee_id');

        // Get current period payroll total
        $currentMonth = Carbon::now()->format('Y-m');
        $periodPayroll = Payroll::whereRaw("TO_CHAR(period_start, 'YYYY-MM') = ?", [$currentMonth])
            ->sum('total_gross');

        // Pending Applications
        $pendingApplications = EmployeeApplication::where('application_status', 'pending')->count();

        // Pending Leaves
        $pendingLeaves = EmployeeLeave::where('status', 'pending')->count();

        // Pending Attendance Corrections
        $pendingAttendanceCorrections = AttendanceCorrection::where('status', 'pending')->count();

        // Draft Payrolls
        $draftPayrolls = Payroll::where('status', 'draft')->count();

        // Pending Resignations
        $pendingResignations = Resignation::where('status', 'pending')->count();

        // Employees with complete data (has government info)
        $employeesCompleteData = Employee::where('is_active', true)
            ->whereHas('governmentInfo')
            ->count();

        // Monthly Attendance Rate
        $workingDaysThisMonth = Carbon::now()->diffInDaysFiltered(function (Carbon $date) {
            return !$date->isWeekend();
        }, Carbon::now()->startOfMonth());

        $expectedAttendance = $totalEmployees * $workingDaysThisMonth;
        $actualAttendance = Attendance::whereRaw("TO_CHAR(attendance_date, 'YYYY-MM') = ?", [$currentMonth])
            ->whereIn('status', ['present', 'late'])
            ->count();

        $monthlyAttendanceRate = $expectedAttendance > 0
            ? round(($actualAttendance / $expectedAttendance) * 100, 1)
            : 0;

        // Last Biometric Import (from audit logs)
        $lastBiometricImport = AuditLog::where('action', 'biometric_import')
            ->orWhere('description', 'like', '%biometric%')
            ->orWhere('description', 'like', '%import%attendance%')
            ->latest()
            ->first();

        // Get recent payrolls
        $recentPayrolls = Payroll::orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($payroll) {
                return [
                    'id' => $payroll->id,
                    'payroll_number' => $payroll->payroll_number,
                    'period_start_date' => $payroll->period_start,
                    'period_end_date' => $payroll->period_end,
                    'total_gross' => $payroll->total_gross,
                    'status' => $payroll->status,
                ];
            });

        $data = [
            'stats' => [
                'totalEmployees' => $totalEmployees,
                'activeEmployees' => $activeStatus,
                'periodPayroll' => $periodPayroll ?? 0,
                'presentToday' => $presentToday,
                'pendingApprovals' => 0, // Legacy field, kept for compatibility
                'pendingApplications' => $pendingApplications,
                'pendingLeaves' => $pendingLeaves,
                'pendingAttendanceCorrections' => $pendingAttendanceCorrections,
                'draftPayrolls' => $draftPayrolls,
                'pendingResignations' => $pendingResignations,
                'employeesCompleteData' => $employeesCompleteData,
                'monthlyAttendanceRate' => $monthlyAttendanceRate,
                'lastBiometricImportDate' => $lastBiometricImport ? $lastBiometricImport->created_at : null,
            ],
            'recent_attendance' => [],
            'recent_payrolls' => $recentPayrolls,
        ];

        return response()->json($data);
    }

    public function employeeDashboard(Request $request)
    {
        $user = $request->user();

        // Check if user has an employee_id linked
        if (!$user->employee_id) {
            return response()->json([
                'message' => 'No employee record linked to your account. Please contact administrator.',
                'user_id' => $user->id,
                'username' => $user->username,
                'employee' => null,
                'attendance' => [],
                'attendance_summary' => [
                    'total_days' => 0,
                    'present' => 0,
                    'absent' => 0,
                    'late' => 0,
                    'undertime' => 0,
                    'total_hours' => 0,
                    'overtime_hours' => 0,
                ],
                'current_payslip' => null,
                'payslip_history' => [],
            ]);
        }

        // Get employee record for the logged-in user using employee_id from User
        $employee = Employee::with(['project'])
            ->where('id', $user->employee_id)
            ->first();

        if (!$employee) {
            return response()->json([
                'message' => 'Employee record not found. Please contact administrator.',
                'user_id' => $user->id,
                'username' => $user->username,
                'employee_id' => $user->employee_id,
                'employee' => null,
                'attendance' => [],
                'attendance_summary' => [
                    'total_days' => 0,
                    'present' => 0,
                    'absent' => 0,
                    'late' => 0,
                    'undertime' => 0,
                    'total_hours' => 0,
                    'overtime_hours' => 0,
                ],
                'current_payslip' => null,
                'payslip_history' => [],
            ]);
        }

        if (config('app.debug')) {
            Log::debug('Employee Dashboard Query', [
                'user_id' => $user->id,
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
            ]);
        }

        // Get attendance for last 3 months (including current month)
        $threeMonthsAgo = Carbon::now()->subMonths(2)->startOfMonth();
        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('attendance_date', '>=', $threeMonthsAgo)
            ->orderBy('attendance_date', 'desc')
            ->get();

        if (config('app.debug')) {
            Log::debug('Attendance Query', [
                'employee_id' => $employee->id,
                'date_from' => $threeMonthsAgo->toDateString(),
                'records_found' => $attendance->count(),
            ]);
        }

        // Get current month attendance for summary
        $currentMonth = Carbon::now()->format('Y-m');
        $currentMonthAttendance = $attendance->filter(function ($record) use ($currentMonth) {
            return Carbon::parse($record->attendance_date)->format('Y-m') === $currentMonth;
        });

        // Calculate total hours from time_in and time_out if regular_hours is 0
        $totalHours = 0;
        foreach ($currentMonthAttendance as $record) {
            if ($record->regular_hours > 0) {
                $totalHours += $record->regular_hours;
            } elseif ($record->time_in && $record->time_out) {
                // Calculate hours from time_in and time_out
                $date = Carbon::parse($record->attendance_date)->format('Y-m-d');
                $timeIn = Carbon::parse($date . ' ' . $record->time_in);
                $timeOut = Carbon::parse($date . ' ' . $record->time_out);
                $hoursWorked = $timeOut->diffInHours($timeIn, true);
                $totalHours += $hoursWorked;
            }
        }

        // Calculate attendance summary (current month only)
        $attendanceSummary = [
            'total_days' => $currentMonthAttendance->count(),
            'present' => $currentMonthAttendance->where('status', 'present')->count(),
            'absent' => $currentMonthAttendance->where('status', 'absent')->count(),
            'late' => $currentMonthAttendance->where('status', 'late')->count(),
            'undertime' => $currentMonthAttendance->where('status', 'undertime')->count(),
            'total_hours' => round($totalHours, 2),
            'overtime_hours' => $currentMonthAttendance->sum('overtime_hours'),
        ];

        // Get current/latest payslip (most recent paid or finalized)
        $currentPayslip = PayrollItem::with(['payroll'])
            ->where('employee_id', $employee->id)
            ->whereHas('payroll', function ($query) {
                $query->whereIn('status', ['paid', 'finalized']);
            })
            ->latest('id')
            ->first();

        // Get all payslip history (last 12 months)
        $oneYearAgo = Carbon::now()->subYear();
        $payslipHistory = PayrollItem::with(['payroll'])
            ->where('employee_id', $employee->id)
            ->whereHas('payroll', function ($query) use ($oneYearAgo) {
                $query->whereIn('status', ['paid', 'finalized'])
                    ->where('period_start', '>=', $oneYearAgo);
            })
            ->latest('id')
            ->get();

        if (config('app.debug')) {
            Log::debug('Payslip Query', [
                'employee_id' => $employee->id,
                'date_from' => $oneYearAgo->toDateString(),
                'records_found' => $payslipHistory->count(),
            ]);
        }

        return response()->json([
            'employee' => $employee,
            'attendance' => $attendance, // Last 3 months
            'attendance_summary' => $attendanceSummary, // Current month only
            'current_payslip' => $currentPayslip,
            'payslip_history' => $payslipHistory, // Last 12 months
            'debug' => [
                'employee_id' => $employee->id,
                'attendance_count' => $attendance->count(),
                'payslip_count' => $payslipHistory->count(),
                'date_range_from' => $threeMonthsAgo->toDateString(),
            ]
        ]);
    }

    // PAYROLL ANALYTICS
    public function payrollTrends(Request $request)
    {
        $months = $request->input('months', 12);

        $trends = [];
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $date = $startDate->copy()->addMonths($i);
            $monthYear = $date->format('Y-m');

            $payrollData = PayrollItem::whereHas('payroll', function ($query) use ($monthYear) {
                $query->whereRaw("TO_CHAR(period_start, 'YYYY-MM') = ?", [$monthYear]);
            })->selectRaw('
                SUM(basic_pay) as total_basic_pay,
                SUM(overtime_pay) as total_overtime,
                SUM(total_allowances) as total_allowances,
                SUM(total_deductions) as total_deductions,
                SUM(net_pay) as total_net_pay
            ')->first();

            $trends[] = [
                'month' => $date->format('M Y'),
                'basic_pay' => (float) ($payrollData->total_basic_pay ?? 0),
                'overtime' => (float) ($payrollData->total_overtime ?? 0),
                'allowances' => (float) ($payrollData->total_allowances ?? 0),
                'deductions' => (float) ($payrollData->total_deductions ?? 0),
                'net_pay' => (float) ($payrollData->total_net_pay ?? 0),
            ];
        }

        return response()->json($trends);
    }

    public function payrollBreakdown(Request $request)
    {
        $period = $request->input('period', 'current-month');

        [$startDate, $endDate] = $this->getPeriodDates($period);

        $breakdown = PayrollItem::whereHas('payroll', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('period_start', [$startDate, $endDate]);
        })->selectRaw('
            SUM(basic_pay) as total_basic_pay,
            SUM(overtime_pay) as total_overtime,
            SUM(total_allowances) as total_allowances,
            SUM(total_deductions) as total_deductions,
            SUM(net_pay) as total_net_pay
        ')->first();

        return response()->json([
            'basic_pay' => (float) ($breakdown->total_basic_pay ?? 0),
            'overtime' => (float) ($breakdown->total_overtime ?? 0),
            'allowances' => (float) ($breakdown->total_allowances ?? 0),
            'deductions' => (float) abs($breakdown->total_deductions ?? 0),
            'net_pay' => (float) ($breakdown->total_net_pay ?? 0),
        ]);
    }

    public function payrollComparison(Request $request)
    {
        $currentStart = Carbon::now()->startOfMonth();
        $currentEnd = Carbon::now()->endOfMonth();
        $previousStart = Carbon::now()->subMonth()->startOfMonth();
        $previousEnd = Carbon::now()->subMonth()->endOfMonth();

        $current = PayrollItem::whereHas('payroll', function ($query) use ($currentStart, $currentEnd) {
            $query->whereBetween('period_start', [$currentStart, $currentEnd]);
        })->sum('net_pay');

        $previous = PayrollItem::whereHas('payroll', function ($query) use ($previousStart, $previousEnd) {
            $query->whereBetween('period_start', [$previousStart, $previousEnd]);
        })->sum('net_pay');

        return response()->json([
            'current_period' => [
                'label' => $currentStart->format('M Y'),
                'amount' => (float) ($current ?? 0)
            ],
            'previous_period' => [
                'label' => $previousStart->format('M Y'),
                'amount' => (float) ($previous ?? 0)
            ],
            'change_percentage' => $previous > 0 ? (($current - $previous) / $previous * 100) : 0
        ]);
    }

    public function governmentContributionTrends(Request $request)
    {
        $months = $request->input('months', 12);

        $trends = [];
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $date = $startDate->copy()->addMonths($i);
            $monthYear = $date->format('Y-m');

            $contributions = PayrollItem::whereHas('payroll', function ($query) use ($monthYear) {
                $query->whereRaw("TO_CHAR(period_start, 'YYYY-MM') = ?", [$monthYear]);
            })->selectRaw('
                SUM(sss) as total_sss,
                SUM(philhealth) as total_philhealth,
                SUM(pagibig) as total_pagibig,
                SUM(tax_withheld) as total_tax
            ')->first();

            $trends[] = [
                'month' => $date->format('M Y'),
                'sss' => (float) ($contributions->total_sss ?? 0),
                'philhealth' => (float) ($contributions->total_philhealth ?? 0),
                'pagibig' => (float) ($contributions->total_pagibig ?? 0),
                'tax' => (float) ($contributions->total_tax ?? 0),
            ];
        }

        return response()->json($trends);
    }

    // EMPLOYEE ANALYTICS
    public function employeeDistribution(Request $request)
    {
        $type = $request->input('type', 'project');

        if ($type === 'project') {
            $distribution = Employee::where('is_active', true)
                ->select('project_id', DB::raw('COUNT(*) as count'))
                ->with('project:id,name')
                ->groupBy('project_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'label' => $item->project->name ?? 'Unassigned',
                        'value' => $item->count
                    ];
                });
        } else {
            $distribution = Employee::where('is_active', true)
                ->select('department', DB::raw('COUNT(*) as count'))
                ->groupBy('department')
                ->get()
                ->map(function ($item) {
                    return [
                        'label' => $item->department ?? 'Unassigned',
                        'value' => $item->count
                    ];
                });
        }

        return response()->json($distribution);
    }

    public function employmentStatusDistribution(Request $request)
    {
        $distribution = Employee::where('is_active', true)
            ->select('contract_type', DB::raw('COUNT(*) as count'))
            ->groupBy('contract_type')
            ->get()
            ->map(function ($item) {
                return [
                    'label' => ucfirst($item->contract_type ?? 'Unknown'),
                    'value' => $item->count
                ];
            });

        return response()->json($distribution);
    }

    public function employeeByLocation(Request $request)
    {
        $distribution = Employee::where('is_active', true)
            ->select('location_id', DB::raw('COUNT(*) as count'))
            ->with('location:id,name')
            ->groupBy('location_id')
            ->get()
            ->map(function ($item) {
                return [
                    'label' => $item->location->name ?? 'Unassigned',
                    'value' => $item->count
                ];
            });

        return response()->json($distribution);
    }

    public function employeeGrowthTrend(Request $request)
    {
        $months = $request->input('months', 12);

        $trends = [];
        $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $date = $startDate->copy()->addMonths($i);

            $hired = Employee::whereYear('date_hired', $date->year)
                ->whereMonth('date_hired', $date->month)
                ->count();

            $resigned = Employee::whereYear('date_separated', $date->year)
                ->whereMonth('date_separated', $date->month)
                ->count();

            $trends[] = [
                'month' => $date->format('M Y'),
                'hired' => $hired,
                'resigned' => $resigned,
                'net_change' => $hired - $resigned
            ];
        }

        return response()->json($trends);
    }

    // ATTENDANCE ANALYTICS
    public function attendanceRate(Request $request)
    {
        $days = $request->input('days', 30);

        $rates = [];
        $startDate = Carbon::now()->subDays($days - 1);

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateStr = $date->format('Y-m-d');

            $totalEmployees = Employee::where('is_active', true)
                ->where('date_hired', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->whereNull('date_separated')
                        ->orWhere('date_separated', '>=', $date);
                })
                ->count();

            $present = Attendance::whereDate('attendance_date', $dateStr)
                ->whereIn('status', ['present', 'late', 'undertime'])
                ->count();

            $attendanceRate = $totalEmployees > 0 ? ($present / $totalEmployees * 100) : 0;

            $rates[] = [
                'date' => $date->format('M d'),
                'rate' => round($attendanceRate, 2),
                'present' => $present,
                'total' => $totalEmployees
            ];
        }

        return response()->json($rates);
    }

    public function attendanceStatusDistribution(Request $request)
    {
        $period = $request->input('period', 'current-month');

        [$startDate, $endDate] = $this->getPeriodDates($period);

        $distribution = Attendance::whereBetween('attendance_date', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'label' => ucfirst($item->status ?? 'Unknown'),
                    'value' => $item->count
                ];
            });

        return response()->json($distribution);
    }

    public function overtimeTrend(Request $request)
    {
        $days = $request->input('days', 30);

        $trends = [];
        $startDate = Carbon::now()->subDays($days - 1);

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateStr = $date->format('Y-m-d');

            $overtimeHours = Attendance::whereDate('attendance_date', $dateStr)
                ->sum('overtime_hours');

            $trends[] = [
                'date' => $date->format('M d'),
                'hours' => (float) ($overtimeHours ?? 0)
            ];
        }

        return response()->json($trends);
    }

    public function leaveUtilization(Request $request)
    {
        $totalEmployees = Employee::where('is_active', true)->count();

        if ($totalEmployees === 0) {
            return response()->json([
                'on_leave' => 0,
                'available' => 0,
                'utilization_rate' => 0
            ]);
        }

        $onLeave = Employee::where('is_active', true)
            ->where('activity_status', 'on_leave')
            ->count();

        $utilizationRate = ($onLeave / $totalEmployees) * 100;

        return response()->json([
            'on_leave' => $onLeave,
            'available' => $totalEmployees - $onLeave,
            'total' => $totalEmployees,
            'utilization_rate' => round($utilizationRate, 2)
        ]);
    }

    public function todayStaffInfo(Request $request)
    {
        $today = Carbon::today();

        // Get attendance records for today
        $attendanceData = Attendance::whereDate('attendance_date', $today)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $distribution = [];

        // Map attendance status to labels
        foreach ($attendanceData as $item) {
            $status = $item->status ?? 'Unknown';
            $label = '';

            switch (strtolower($status)) {
                case 'present':
                    $label = 'Punched';
                    break;
                case 'absent':
                    $label = 'Unpunched';
                    break;
                case 'late':
                    $label = 'Late';
                    break;
                case 'on_leave':
                case 'on leave':
                    $label = 'Leave';
                    break;
                case 'business_trip':
                    $label = 'Business Trip';
                    break;
                case 'vacation':
                    $label = 'Vacation';
                    break;
                case 'normal_ot':
                    $label = 'Normal OT';
                    break;
                case 'weekend_ot':
                    $label = 'Weekend OT';
                    break;
                case 'holiday_ot':
                    $label = 'Holiday OT';
                    break;
                case 'leave_early':
                    $label = 'Leave Early';
                    break;
                default:
                    $label = ucfirst($status);
            }

            // Find if label already exists and combine counts
            $existingIndex = array_search($label, array_column($distribution, 'label'));
            if ($existingIndex !== false) {
                $distribution[$existingIndex]['value'] += $item->count;
            } else {
                $distribution[] = [
                    'label' => $label,
                    'value' => $item->count
                ];
            }
        }

        // If no data for today, return default structure
        if (empty($distribution)) {
            $distribution = [
                ['label' => 'Unpunched', 'value' => 0],
                ['label' => 'Punched', 'value' => 0]
            ];
        }

        return response()->json($distribution);
    }

    /**
     * Get recent system activities
     */
    public function recentActivities(Request $request)
    {
        $limit = $request->input('limit', 15);

        $activities = AuditLog::with('user')
            ->whereIn('action', [
                // Employee actions
                'employee_created',
                'employee_updated',
                // Payroll actions
                'create_payroll',
                'update_payroll',
                'finalize_payroll',
                'delete_payroll',
                'reprocess_payroll',
                'payroll_approved',
                'payroll_finalized',
                // Attendance actions
                'approve_attendance',
                'update_attendance',
                'reject_attendance',
                'attendance_corrected',
                'biometric_import',
                // Leave actions
                'leave_approved',
                'leave_rejected',
                // Application actions
                'application_approved',
                'application_rejected'
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'module' => $log->module,
                    'user' => $log->user ? [
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ] : null,
                    'created_at' => $log->created_at,
                ];
            });

        return response()->json($activities);
    }

    /**
     * Get upcoming events and deadlines
     */
    public function upcomingEvents(Request $request)
    {
        $events = [];
        $now = Carbon::now();
        $nextWeek = Carbon::now()->addWeek();
        $nextMonth = Carbon::now()->addMonth();

        // Upcoming approved leaves
        $upcomingLeaves = EmployeeLeave::with(['employee', 'leaveType'])
            ->where('status', 'approved')
            ->where('leave_date_from', '>=', $now)
            ->where('leave_date_from', '<=', $nextMonth)
            ->orderBy('leave_date_from')
            ->limit(5)
            ->get()
            ->map(function ($leave) {
                $leaveTypeName = $leave->leaveType ? $leave->leaveType->name : 'Leave';
                return [
                    'type' => 'leave',
                    'title' => $leave->employee->full_name . ' - ' . $leaveTypeName,
                    'date' => $leave->leave_date_from,
                    'description' => $leave->leave_date_from->format('M d') . ' - ' . $leave->leave_date_to->format('M d, Y'),
                    'icon' => 'mdi-calendar-remove',
                    'color' => 'info',
                ];
            });

        // Upcoming employee anniversaries (work anniversaries)
        $upcomingAnniversaries = Employee::where('is_active', true)
            ->whereNotNull('date_hired')
            ->get()
            ->filter(function ($employee) use ($now, $nextMonth) {
                $dateHired = Carbon::parse($employee->date_hired);
                $thisYearAnniversary = Carbon::create($now->year, $dateHired->month, $dateHired->day);
                return $thisYearAnniversary->between($now, $nextMonth);
            })
            ->sortBy(function ($employee) use ($now) {
                $dateHired = Carbon::parse($employee->date_hired);
                return Carbon::create($now->year, $dateHired->month, $dateHired->day);
            })
            ->take(5)
            ->map(function ($employee) use ($now) {
                $dateHired = Carbon::parse($employee->date_hired);
                $thisYearAnniversary = Carbon::create($now->year, $dateHired->month, $dateHired->day);
                $years = $now->year - $dateHired->year;
                return [
                    'type' => 'anniversary',
                    'title' => $employee->full_name . ' - ' . $years . ' year(s)',
                    'date' => $thisYearAnniversary,
                    'description' => 'Work anniversary on ' . $thisYearAnniversary->format('M d, Y'),
                    'icon' => 'mdi-cake-variant',
                    'color' => 'success',
                ];
            });

        // Upcoming payroll cutoffs (15th and end of month)
        $payrollCutoffs = [];
        $currentMonth = $now->month;
        $currentYear = $now->year;

        // Check 15th of current month
        $cutoff15 = Carbon::create($currentYear, $currentMonth, 15);
        if ($cutoff15->isFuture() && $cutoff15->lte($nextWeek)) {
            $payrollCutoffs[] = [
                'type' => 'payroll_cutoff',
                'title' => 'Payroll Cutoff - Mid-Month',
                'date' => $cutoff15,
                'description' => 'Attendance cutoff for 1st-15th',
                'icon' => 'mdi-calendar-clock',
                'color' => 'warning',
            ];
        }

        // Check end of current month
        $cutoffEOM = Carbon::create($currentYear, $currentMonth, 1)->endOfMonth();
        if ($cutoffEOM->isFuture() && $cutoffEOM->lte($nextWeek)) {
            $payrollCutoffs[] = [
                'type' => 'payroll_cutoff',
                'title' => 'Payroll Cutoff - End of Month',
                'date' => $cutoffEOM,
                'description' => 'Attendance cutoff for 16th-' . $cutoffEOM->day,
                'icon' => 'mdi-calendar-clock',
                'color' => 'warning',
            ];
        }

        // Check 15th of next month
        $nextMonthDate = $now->copy()->addMonth();
        $cutoff15Next = Carbon::create($nextMonthDate->year, $nextMonthDate->month, 15);
        if ($cutoff15Next->lte($nextWeek)) {
            $payrollCutoffs[] = [
                'type' => 'payroll_cutoff',
                'title' => 'Payroll Cutoff - Mid-Month',
                'date' => $cutoff15Next,
                'description' => 'Attendance cutoff for 1st-15th',
                'icon' => 'mdi-calendar-clock',
                'color' => 'warning',
            ];
        }

        // Merge all events
        $events = collect($upcomingLeaves)
            ->concat($upcomingAnniversaries)
            ->concat($payrollCutoffs)
            ->sortBy('date')
            ->values()
            ->take(10);

        return response()->json($events);
    }

    // HELPER METHODS
    private function getPeriodDates($period)
    {
        switch ($period) {
            case 'current-month':
                return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
            case 'last-month':
                return [
                    Carbon::now()->subMonth()->startOfMonth(),
                    Carbon::now()->subMonth()->endOfMonth()
                ];
            case 'current-quarter':
                return [Carbon::now()->startOfQuarter(), Carbon::now()->endOfQuarter()];
            case 'current-year':
                return [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()];
            default:
                return [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()];
        }
    }
}
