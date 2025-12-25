<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeLoan;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function payrollSummary(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $payrolls = Payroll::whereBetween('pay_date', [$dateFrom, $dateTo])
            ->with('items.employee')
            ->get();

        $summary = [
            'total_payrolls' => $payrolls->count(),
            'total_gross_pay' => $payrolls->sum('total_gross'),
            'total_deductions' => $payrolls->sum('total_deductions'),
            'total_net_pay' => $payrolls->sum('total_net'),
            'payrolls' => $payrolls,
        ];

        return response()->json($summary);
    }

    public function employeeEarnings(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $year = $request->input('year', Carbon::now()->year);

        $query = PayrollItem::with(['payroll', 'employee']);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $query->whereHas('payroll', function ($q) use ($year) {
            $q->whereYear('pay_date', $year);
        });

        $earnings = $query->get();

        $summary = [
            'total_basic_pay' => $earnings->sum('basic_pay'),
            'total_overtime' => $earnings->sum('overtime_pay'),
            'total_allowances' => $earnings->sum('allowances'),
            'total_bonuses' => $earnings->sum('bonuses'),
            'total_gross' => $earnings->sum('gross_pay'),
            'total_deductions' => $earnings->sum('total_deductions'),
            'total_net' => $earnings->sum('net_pay'),
            'earnings' => $earnings,
        ];

        return response()->json($summary);
    }

    public function governmentRemittance(Request $request)
    {
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);

        $payrollItems = PayrollItem::whereHas('payroll', function ($query) use ($month, $year) {
            $query->whereMonth('pay_date', $month)
                ->whereYear('pay_date', $year);
        })
            ->with('employee')
            ->get();

        $remittance = [
            'period' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
            'total_sss' => $payrollItems->sum('sss_contribution'),
            'total_philhealth' => $payrollItems->sum('philhealth_contribution'),
            'total_pagibig' => $payrollItems->sum('pagibig_contribution'),
            'total_tax' => $payrollItems->sum('withholding_tax'),
            'employee_count' => $payrollItems->count(),
            'details' => $payrollItems,
        ];

        return response()->json($remittance);
    }

    public function attendanceSummary(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());
        $employeeId = $request->input('employee_id');

        $query = Attendance::with('employee')
            ->whereBetween('date', [$dateFrom, $dateTo]);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $attendance = $query->get();

        $summary = [
            'total_present' => $attendance->where('status', 'present')->count(),
            'total_absent' => $attendance->where('status', 'absent')->count(),
            'total_late' => $attendance->where('status', 'late')->count(),
            'total_hours' => $attendance->sum('hours_worked'),
            'attendance' => $attendance,
        ];

        return response()->json($summary);
    }

    public function loanLedger(Request $request)
    {
        $employeeId = $request->input('employee_id');

        $query = EmployeeLoan::with(['employee', 'payments']);

        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        $loans = $query->get();

        $ledger = [
            'total_loans' => $loans->sum('amount'),
            'total_paid' => $loans->sum(function ($loan) {
                return $loan->amount - $loan->remaining_balance;
            }),
            'total_balance' => $loans->sum('remaining_balance'),
            'loans' => $loans,
        ];

        return response()->json($ledger);
    }
}
