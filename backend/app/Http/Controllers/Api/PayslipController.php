<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use App\Models\Employee;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PayslipExport;

class PayslipController extends Controller
{
    public function employeePayslips($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $payslips = PayrollItem::with(['payroll', 'employee'])
            ->where('employee_id', $employeeId)
            ->whereHas('payroll', function ($query) {
                $query->where('status', 'paid');
            })
            ->latest()
            ->paginate(15);

        return response()->json($payslips);
    }

    public function view($payrollItemId)
    {
        $payslip = PayrollItem::with([
            'payroll',
            'employee.department',
            'employee.location',
            'details'
        ])->findOrFail($payrollItemId);

        return response()->json($payslip);
    }

    public function downloadPdf($payrollItemId)
    {
        $payslip = PayrollItem::with([
            'payroll',
            'employee.department',
            'employee.location',
            'details'
        ])->findOrFail($payrollItemId);

        $pdf = Pdf::loadView('payslip.pdf', ['payslip' => $payslip]);
        
        $filename = 'payslip_' . $payslip->employee->employee_number . '_' . $payslip->payroll->period_start . '.pdf';
        
        return $pdf->download($filename);
    }

    public function downloadExcel($payrollItemId)
    {
        $payslip = PayrollItem::with([
            'payroll',
            'employee.department',
            'employee.location',
            'details'
        ])->findOrFail($payrollItemId);

        $filename = 'payslip_' . $payslip->employee->employee_number . '_' . $payslip->payroll->period_start . '.xlsx';
        
        return Excel::download(new PayslipExport($payslip), $filename);
    }

    public function myPayslips(Request $request)
    {
        $user = $request->user();
        
        // Find employee by user email or username
        $employee = Employee::where('email', $user->email)
            ->orWhere('username', $user->username)
            ->first();

        if (!$employee) {
            return response()->json([
                'message' => 'Employee record not found',
                'data' => [],
            ], 404);
        }

        $payslips = PayrollItem::with(['payroll', 'employee'])
            ->where('employee_id', $employee->id)
            ->whereHas('payroll', function ($query) {
                $query->where('status', 'paid');
            })
            ->latest()
            ->paginate(15);

        return response()->json($payslips);
    }
}
