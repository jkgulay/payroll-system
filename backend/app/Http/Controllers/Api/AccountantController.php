<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Attendance;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountantController extends Controller
{
    public function getDashboardStats()
    {
        $totalEmployees = Employee::count();
        $activeEmployees = Employee::where('employment_status', 'active')->count();
        $pendingRequests = 0; // Will be implemented with approval system
        
        $currentPayroll = Payroll::latest()->first();
        $periodPayroll = $currentPayroll 
            ? $currentPayroll->payslips()->sum('net_pay')
            : 0;
            
        $presentToday = Attendance::whereDate('date', today())
            ->where('status', 'present')
            ->count();

        return response()->json([
            'totalEmployees' => $totalEmployees,
            'activeEmployees' => $activeEmployees,
            'pendingRequests' => $pendingRequests,
            'periodPayroll' => $periodPayroll,
            'presentToday' => $presentToday,
        ]);
    }

    public function submitPayslipModification(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'additional_allowance' => 'nullable|numeric|min:0',
            'additional_deduction' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        // Store the modification request
        // For now, we'll directly update the current payslip
        $currentPayroll = Payroll::latest()->first();
        
        if (!$currentPayroll) {
            return response()->json([
                'message' => 'No active payroll period found'
            ], 404);
        }

        $payslip = $currentPayroll->payslips()
            ->where('employee_id', $validated['employee_id'])
            ->first();

        if (!$payslip) {
            return response()->json([
                'message' => 'No payslip found for this employee in current period'
            ], 404);
        }

        // Add to earnings or deductions
        if ($validated['additional_allowance'] > 0) {
            DB::table('employee_allowances')->insert([
                'employee_id' => $validated['employee_id'],
                'allowance_type' => 'other',
                'amount' => $validated['additional_allowance'],
                'description' => $validated['notes'] ?? 'Additional allowance by accountant',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($validated['additional_deduction'] > 0) {
            DB::table('employee_deductions')->insert([
                'employee_id' => $validated['employee_id'],
                'deduction_type' => 'other',
                'amount' => $validated['additional_deduction'],
                'description' => $validated['notes'] ?? 'Additional deduction by accountant',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Recalculate payslip
        $this->recalculatePayslip($payslip);

        return response()->json([
            'message' => 'Payslip modification submitted successfully',
            'payslip' => $payslip->fresh()
        ]);
    }

    private function recalculatePayslip($payslip)
    {
        $employee = $payslip->employee;
        
        // Calculate total earnings
        $basicSalary = $employee->basic_salary;
        $allowances = DB::table('employee_allowances')
            ->where('employee_id', $employee->id)
            ->sum('amount');
        $bonuses = DB::table('employee_bonuses')
            ->where('employee_id', $employee->id)
            ->whereNull('paid_at')
            ->sum('amount');
            
        $grossPay = $basicSalary + $allowances + $bonuses;
        
        // Calculate total deductions
        $sss = $grossPay * 0.045; // 4.5% SSS
        $philhealth = $grossPay * 0.02; // 2% PhilHealth
        $pagibig = min($grossPay * 0.02, 100); // 2% HDMF, max 100
        
        $otherDeductions = DB::table('employee_deductions')
            ->where('employee_id', $employee->id)
            ->sum('amount');
        
        $totalDeductions = $sss + $philhealth + $pagibig + $otherDeductions;
        
        // Update payslip
        $payslip->update([
            'basic_salary' => $basicSalary,
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'net_pay' => $grossPay - $totalDeductions,
        ]);
    }

    public function updateAttendance(Request $request, $id)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:present,absent,late,half-day,on-leave',
            'notes' => 'nullable|string|max:500',
        ]);

        $attendance = Attendance::findOrFail($id);
        $attendance->update($validated);

        return response()->json([
            'message' => 'Attendance updated successfully',
            'attendance' => $attendance
        ]);
    }

    public function getEmployeeAttendance(Request $request, $employeeId)
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $attendance = Attendance::where('employee_id', $employeeId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json($attendance);
    }
}
