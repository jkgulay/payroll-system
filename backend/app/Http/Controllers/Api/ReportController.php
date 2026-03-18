<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\EmployeeLoan;
use App\Models\CompanyInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function payrollSummary(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());

        $payrolls = Payroll::whereBetween('payment_date', [$dateFrom, $dateTo])
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
            $q->whereYear('payment_date', $year);
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

        // Query payroll items where the payroll's period_start is within the selected month/year
        // This is more reliable than payment_date which can be in a different month
        $payrollItems = PayrollItem::whereHas('payroll', function ($query) use ($month, $year) {
            $query->where(function ($q) use ($month, $year) {
                $q->whereMonth('period_start', $month)
                  ->whereYear('period_start', $year);
            })
            ->orWhere(function ($q) use ($month, $year) {
                $q->whereMonth('period_end', $month)
                  ->whereYear('period_end', $year);
            });
        })
            ->with(['employee', 'payroll'])
            ->get();

        // Debug logging
        Log::info('Government Remittance Query', [
            'month' => $month,
            'year' => $year,
            'payroll_items_count' => $payrollItems->count(),
            'unique_employees' => $payrollItems->unique('employee_id')->count()
        ]);

        // If no payroll items found, return empty data
        if ($payrollItems->isEmpty()) {
            return response()->json([
                'period' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
                'month' => (int) $month,
                'year' => (int) $year,
                'employee_count' => 0,
                'sss_employee' => 0,
                'philhealth_employee' => 0,
                'pagibig_employee' => 0,
                'total_employee_contributions' => 0,
                'sss_employer' => 0,
                'philhealth_employer' => 0,
                'pagibig_employer' => 0,
                'total_employer_contributions' => 0,
                'sss_total' => 0,
                'philhealth_total' => 0,
                'pagibig_total' => 0,
                'grand_total' => 0,
                'withholding_tax' => 0,
                'employees' => [],
            ]);
        }

        // Calculate employer shares (typically employer matches employee contribution)
        $employeeSss = $payrollItems->sum('sss');
        $employeePhilhealth = $payrollItems->sum('philhealth');
        $employeePagibig = $payrollItems->sum('pagibig');

        // Employer shares (usually matching contributions)
        $employerSss = $employeeSss; // SSS employer typically matches
        $employerPhilhealth = $employeePhilhealth; // PhilHealth employer matches
        $employerPagibig = $employeePagibig; // Pag-IBIG employer matches

        // Group by employee for detailed breakdown
        $employeeBreakdown = $payrollItems->groupBy('employee_id')->map(function ($items, $employeeId) {
            $employee = $items->first()->employee;
            
            // Skip if employee doesn't exist
            if (!$employee) {
                return null;
            }
            
            return [
                'employee_id' => $employeeId,
                'employee_number' => $employee->employee_number ?? 'N/A',
                'full_name' => $employee->full_name,
                'department' => $employee->department ?? 'N/A',
                'position' => $employee->position ?? 'N/A',
                'sss_employee' => (float) $items->sum('sss'),
                'sss_employer' => (float) $items->sum('sss'), // Employer share
                'sss_total' => (float) $items->sum('sss') * 2,
                'philhealth_employee' => (float) $items->sum('philhealth'),
                'philhealth_employer' => (float) $items->sum('philhealth'),
                'philhealth_total' => (float) $items->sum('philhealth') * 2,
                'pagibig_employee' => (float) $items->sum('pagibig'),
                'pagibig_employer' => (float) $items->sum('pagibig'),
                'pagibig_total' => (float) $items->sum('pagibig') * 2,
                'total_employee' => (float) ($items->sum('sss') + $items->sum('philhealth') + $items->sum('pagibig')),
                'total_employer' => (float) ($items->sum('sss') + $items->sum('philhealth') + $items->sum('pagibig')),
                'grand_total' => (float) (($items->sum('sss') + $items->sum('philhealth') + $items->sum('pagibig')) * 2),
            ];
        })->filter()->values(); // Remove null values and reindex

        $remittance = [
            'period' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
            'month' => (int) $month,
            'year' => (int) $year,
            'employee_count' => $payrollItems->unique('employee_id')->count(),
            
            // Employee contributions (deducted from salary)
            'sss_employee' => $employeeSss,
            'philhealth_employee' => $employeePhilhealth,
            'pagibig_employee' => $employeePagibig,
            'total_employee_contributions' => $employeeSss + $employeePhilhealth + $employeePagibig,
            
            // Employer contributions
            'sss_employer' => $employerSss,
            'philhealth_employer' => $employerPhilhealth,
            'pagibig_employer' => $employerPagibig,
            'total_employer_contributions' => $employerSss + $employerPhilhealth + $employerPagibig,
            
            // Total remittance to government
            'sss_total' => $employeeSss + $employerSss,
            'philhealth_total' => $employeePhilhealth + $employerPhilhealth,
            'pagibig_total' => $employeePagibig + $employerPagibig,
            'grand_total' => ($employeeSss + $employerSss) + ($employeePhilhealth + $employerPhilhealth) + ($employeePagibig + $employerPagibig),
            
            // Tax withholding
            'withholding_tax' => $payrollItems->sum('withholding_tax'),
            
            // Detailed breakdown per employee
            'employees' => $employeeBreakdown,
        ];

        return response()->json($remittance);
    }

    public function attendanceSummary(Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth());
        $employeeId = $request->input('employee_id');

        $query = Attendance::with('employee')
            ->whereBetween('attendance_date', [$dateFrom, $dateTo]);

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

    /**
     * Export Government Contributions Report as PDF
     */
    public function exportGovernmentContributionsPdf(Request $request)
    {
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);
        $contributionTypes = $request->input('types', ['sss', 'philhealth', 'pagibig']); // Default all types
        $department = $request->input('department');

        // Validate contribution types
        $validTypes = ['sss', 'philhealth', 'pagibig'];
        $contributionTypes = array_intersect($contributionTypes, $validTypes);
        
        if (empty($contributionTypes)) {
            $contributionTypes = $validTypes; // If nothing valid, export all
        }

        // Query payroll items for the selected month/year
        $payrollItemsQuery = PayrollItem::whereHas('payroll', function ($query) use ($month, $year) {
            $query->where(function ($q) use ($month, $year) {
                $q->whereMonth('period_start', $month)
                  ->whereYear('period_start', $year);
            })
            ->orWhere(function ($q) use ($month, $year) {
                $q->whereMonth('period_end', $month)
                  ->whereYear('period_end', $year);
            });
        })->with(['employee', 'payroll']);

        // Apply department filter if specified
        if ($department) {
            $payrollItemsQuery->whereHas('employee', function ($q) use ($department) {
                $q->where('department', $department);
            });
        }

        $payrollItems = $payrollItemsQuery->get();

        // Group by employee and calculate contributions
        $employees = $payrollItems->groupBy('employee_id')->map(function ($items, $employeeId) use ($contributionTypes) {
            $employee = $items->first()->employee;
            
            // Skip if employee doesn't exist
            if (!$employee) {
                return null;
            }
            
            $data = [
                'employee_number' => $employee->employee_number ?? 'N/A',
                'full_name' => $employee->full_name,
                'position' => $employee->position ?? 'N/A',
                'department' => $employee->department ?? 'N/A',
            ];

            // Add contribution data based on selected types
            if (in_array('sss', $contributionTypes)) {
                $data['sss'] = (float) $items->sum('sss');
                $data['sss_employer'] = (float) $items->sum('sss');
                $data['sss_total'] = (float) $items->sum('sss') * 2;
            }
            
            if (in_array('philhealth', $contributionTypes)) {
                $data['philhealth'] = (float) $items->sum('philhealth');
                $data['philhealth_employer'] = (float) $items->sum('philhealth');
                $data['philhealth_total'] = (float) $items->sum('philhealth') * 2;
            }
            
            if (in_array('pagibig', $contributionTypes)) {
                $data['pagibig'] = (float) $items->sum('pagibig');
                $data['pagibig_employer'] = (float) $items->sum('pagibig');
                $data['pagibig_total'] = (float) $items->sum('pagibig') * 2;
            }

            return $data;
        })->filter()->values()->sortBy('full_name')->values();

        // Calculate totals for each contribution type
        $totals = [];
        if (in_array('sss', $contributionTypes)) {
            $totals['sss_employee'] = $employees->sum('sss');
            $totals['sss_employer'] = $employees->sum('sss_employer');
            $totals['sss_total'] = $employees->sum('sss_total');
        }
        if (in_array('philhealth', $contributionTypes)) {
            $totals['philhealth_employee'] = $employees->sum('philhealth');
            $totals['philhealth_employer'] = $employees->sum('philhealth_employer');
            $totals['philhealth_total'] = $employees->sum('philhealth_total');
        }
        if (in_array('pagibig', $contributionTypes)) {
            $totals['pagibig_employee'] = $employees->sum('pagibig');
            $totals['pagibig_employer'] = $employees->sum('pagibig_employer');
            $totals['pagibig_total'] = $employees->sum('pagibig_total');
        }

        // Get company info
        $companyInfo = CompanyInfo::first();

        // Ensure installed-fonts.json exists
        $fontCache = storage_path('fonts');
        $installedFonts = $fontCache . '/installed-fonts.json';
        if (!file_exists($installedFonts)) {
            $distFonts = base_path('vendor/dompdf/dompdf/lib/fonts/installed-fonts.dist.json');
            if (file_exists($distFonts)) {
                copy($distFonts, $installedFonts);
            }
        }

        try {
            $pdf = Pdf::loadView('reports.government-contributions', [
                'period' => Carbon::createFromDate($year, $month, 1)->format('F Y'),
                'month' => $month,
                'year' => $year,
                'employees' => $employees,
                'totals' => $totals,
                'contributionTypes' => $contributionTypes,
                'companyInfo' => $companyInfo,
                'department' => $department,
                'employeeCount' => $employees->count(),
            ]);
            $pdf->setOption('dpi', 72);

            $filename = 'government-contributions-' . Carbon::createFromDate($year, $month, 1)->format('Y-m') . '.pdf';
            return $pdf->download($filename);
        } catch (\Throwable $e) {
            Log::error('Government Contributions PDF Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }
}
