<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\PayrollItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Count all active employees (is_active = true)
        $totalEmployees = Employee::where('is_active', true)->count();

        // Count active employees with 'active' activity status
        $activeStatus = Employee::where('is_active', true)
            ->where('activity_status', 'active')
            ->count();

        $data = [
            'stats' => [
                'totalEmployees' => $totalEmployees,
                'activeEmployees' => $activeStatus,
                'periodPayroll' => 0,
                'presentToday' => 0,
                'pendingApprovals' => 0,
            ],
            'recent_attendance' => [],
            'recent_payrolls' => [],
        ];

        return response()->json($data);
    }

    public function employeeDashboard(Request $request)
    {
        $user = $request->user();

        // Find employee record by email or username
        $employee = Employee::where('email', $user->email)
            ->orWhere('username', $user->username)
            ->first();

        if (!$employee) {
            return response()->json([
                'message' => 'Employee record not found',
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

        // Get attendance for current month
        $currentMonth = Carbon::now()->format('Y-m');
        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereRaw("TO_CHAR(attendance_date, 'YYYY-MM') = ?", [$currentMonth])
            ->orderBy('attendance_date', 'desc')
            ->get();

        // Calculate attendance summary
        $attendanceSummary = [
            'total_days' => $attendance->count(),
            'present' => $attendance->where('status', 'present')->count(),
            'absent' => $attendance->where('status', 'absent')->count(),
            'late' => $attendance->where('status', 'late')->count(),
            'undertime' => $attendance->where('status', 'undertime')->count(),
            'total_hours' => $attendance->sum('regular_hours'),
            'overtime_hours' => $attendance->sum('overtime_hours'),
        ];

        // Get current/latest payslip (most recent paid)
        $currentPayslip = PayrollItem::with(['payroll', 'details'])
            ->where('employee_id', $employee->id)
            ->whereHas('payroll', function ($query) {
                $query->where('status', 'paid');
            })
            ->latest('id')
            ->first();

        // Get payslip history (excluding current)
        $payslipHistory = PayrollItem::with(['payroll'])
            ->where('employee_id', $employee->id)
            ->whereHas('payroll', function ($query) {
                $query->where('status', 'paid');
            })
            ->when($currentPayslip, function ($query) use ($currentPayslip) {
                $query->where('id', '!=', $currentPayslip->id);
            })
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json([
            'employee' => $employee,
            'attendance' => $attendance,
            'attendance_summary' => $attendanceSummary,
            'current_payslip' => $currentPayslip,
            'payslip_history' => $payslipHistory,
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
                SUM(sss_contribution) as total_sss,
                SUM(philhealth_contribution) as total_philhealth,
                SUM(pagibig_contribution) as total_pagibig,
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
