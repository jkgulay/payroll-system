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

        // Count active employees with 'active' employment status
        $activeStatus = Employee::where('is_active', true)
            ->where('employment_status', 'active')
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
            ->whereRaw("TO_CHAR(date, 'YYYY-MM') = ?", [$currentMonth])
            ->orderBy('date', 'desc')
            ->get();

        // Calculate attendance summary
        $attendanceSummary = [
            'total_days' => $attendance->count(),
            'present' => $attendance->where('status', 'present')->count(),
            'absent' => $attendance->where('status', 'absent')->count(),
            'late' => $attendance->where('status', 'late')->count(),
            'undertime' => $attendance->where('status', 'undertime')->count(),
            'total_hours' => $attendance->sum('total_hours_worked'),
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
}
