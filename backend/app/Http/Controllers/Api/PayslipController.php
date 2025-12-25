<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use App\Models\Employee;
use Illuminate\Http\Request;

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

        // TODO: Generate PDF using DomPDF or similar
        return response()->json([
            'message' => 'PDF generation will be implemented',
            'payslip' => $payslip,
        ]);
    }
}
