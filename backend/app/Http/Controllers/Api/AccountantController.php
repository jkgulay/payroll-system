<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountantController extends Controller
{
    public function getDashboardStats()
    {
        $totalEmployees = Employee::count();
        $activeEmployees = Employee::whereIn('employment_status', ['regular', 'probationary', 'contractual', 'active'])
            ->count();
        $pendingRequests = 0; // Will be implemented with approval system

        $currentPayroll = Payroll::latest()->first();
        $periodPayroll = $currentPayroll && $currentPayroll->payslips
            ? $currentPayroll->payslips->sum('net_pay')
            : 0;

        $presentToday = Attendance::whereDate('attendance_date', today())
            ->where('status', 'present')
            ->count();

        return response()->json([
            'totalEmployees' => $totalEmployees,
            'activeEmployees' => $activeEmployees,
            'pendingRequests' => $pendingRequests,
            'periodPayroll' => (float) $periodPayroll,
            'presentToday' => $presentToday,
        ]);
    }

    public function submitPayslipModification(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'additional_allowance' => 'nullable|numeric|min:0|max:50000',
            'additional_deduction' => 'nullable|numeric|min:0|max:50000',
            'notes' => 'nullable|string|max:500',
        ]);

        // Require at least one modification
        if (empty($validated['additional_allowance']) && empty($validated['additional_deduction'])) {
            return response()->json([
                'error' => 'Please specify at least one modification (allowance or deduction)'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $currentPayroll = Payroll::latest()->first();

            if (!$currentPayroll) {
                return response()->json([
                    'message' => 'No active payroll period found'
                ], 404);
            }

            // Check if payroll can be modified
            if (!$currentPayroll->canEdit()) {
                return response()->json([
                    'error' => 'Payroll cannot be modified in current status'
                ], 422);
            }

            $payslip = $currentPayroll->payrollItems()
                ->where('employee_id', $validated['employee_id'])
                ->first();

            if (!$payslip) {
                return response()->json([
                    'message' => 'No payslip found for this employee in current period'
                ], 404);
            }

            // Add allowance using Eloquent model
            if (!empty($validated['additional_allowance']) && $validated['additional_allowance'] > 0) {
                EmployeeAllowance::create([
                    'employee_id' => $validated['employee_id'],
                    'allowance_type' => 'other',
                    'amount' => $validated['additional_allowance'],
                    'frequency' => 'semi_monthly',
                    'description' => $validated['notes'] ?? 'Additional allowance by accountant',
                    'is_taxable' => true,
                    'is_active' => true,
                    'effective_date' => now(),
                    'created_by' => auth()->id(),
                ]);

                // Log the modification
                Log::info('Allowance added by accountant', [
                    'employee_id' => $validated['employee_id'],
                    'amount' => $validated['additional_allowance'],
                    'by_user' => auth()->id(),
                ]);
            }

            // Add deduction using Eloquent model
            if (!empty($validated['additional_deduction']) && $validated['additional_deduction'] > 0) {
                EmployeeDeduction::create([
                    'employee_id' => $validated['employee_id'],
                    'deduction_type' => 'other',
                    'amount' => $validated['additional_deduction'],
                    'amount_per_cutoff' => $validated['additional_deduction'],
                    'description' => $validated['notes'] ?? 'Additional deduction by accountant',
                    'status' => 'active',
                    'effective_date' => now(),
                    'created_by' => auth()->id(),
                ]);

                // Log the modification
                Log::info('Deduction added by accountant', [
                    'employee_id' => $validated['employee_id'],
                    'amount' => $validated['additional_deduction'],
                    'by_user' => auth()->id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Payslip modification submitted successfully. Payroll needs to be reprocessed.',
                'note' => 'Please reprocess the payroll to apply these changes.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payslip modification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to submit modification'], 500);
        }
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
            ->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month)
            ->orderBy('attendance_date', 'asc')
            ->get();

        return response()->json($attendance);
    }
}
