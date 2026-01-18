<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class DailyTimeRecordController extends Controller
{
    /**
     * Generate Daily Time Record for a specific employee and date range
     */
    public function generate(Request $request)
    {
        // Increase memory limit for PDF generation
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');
        
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $employee = Employee::with(['positionRate', 'project'])->findOrFail($validated['employee_id']);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$validated['date_from'], $validated['date_to']])
            ->orderBy('attendance_date', 'asc')
            ->get();

        // Calculate totals
        $totalRegularHours = $attendance->sum('regular_hours');
        $totalOvertimeHours = $attendance->sum('overtime_hours');
        $totalLateHours = $attendance->sum('late_hours');
        $totalUndertimeHours = $attendance->sum('undertime_hours');
        $totalDays = $attendance->where('status', 'present')->count();

        // Calculate earnings for the period
        $rate = $employee->getBasicSalary();
        $basicPay = $rate * $totalDays;
        $hourlyRate = $rate / 8;
        $overtimePay = $totalOvertimeHours * $hourlyRate * 1.25;
        $grossPay = $basicPay + $overtimePay;

        // Calculate government deductions
        $sssContribution = $this->calculateSSS($grossPay);
        $philhealthContribution = $this->calculatePhilHealth($grossPay);
        $pagibigContribution = $this->calculatePagibig($grossPay);
        $totalDeductions = $sssContribution + $philhealthContribution + $pagibigContribution;
        $netPay = $grossPay - $totalDeductions;

        $data = [
            'employee' => $employee,
            'attendance' => $attendance,
            'date_from' => Carbon::parse($validated['date_from']),
            'date_to' => Carbon::parse($validated['date_to']),
            'totals' => [
                'regular_hours' => $totalRegularHours,
                'overtime_hours' => $totalOvertimeHours,
                'late_hours' => $totalLateHours,
                'undertime_hours' => $totalUndertimeHours,
                'days_present' => $totalDays,
            ],
            'earnings' => [
                'rate' => $rate,
                'basic_pay' => $basicPay,
                'overtime_pay' => $overtimePay,
                'gross_pay' => $grossPay,
            ],
            'deductions' => [
                'sss' => $sssContribution,
                'philhealth' => $philhealthContribution,
                'pagibig' => $pagibigContribution,
                'total' => $totalDeductions,
            ],
            'net_pay' => $netPay,
            'generated_at' => Carbon::now(),
            'company_name' => config('payroll.company.name'),
        ];

        $pdf = Pdf::loadView('dtr.daily-time-record', $data);
        $pdf->setPaper('legal', 'portrait');

        $filename = 'DTR_' . $employee->employee_number . '_' .
            Carbon::parse($validated['date_from'])->format('Ymd') . '-' .
            Carbon::parse($validated['date_to'])->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Generate DTR for single day (for signature)
     */
    public function generateDaily(Request $request)
    {
        // Increase memory limit for PDF generation
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '300');
        
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
        ]);

        $employee = Employee::with(['positionRate', 'project'])->findOrFail($validated['employee_id']);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('attendance_date', $validated['date'])
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'No attendance record found for this date'
            ], 404);
        }

        $data = [
            'employee' => $employee,
            'attendance' => $attendance,
            'date' => Carbon::parse($validated['date']),
            'generated_at' => Carbon::now(),
            'company_name' => config('payroll.company.name'),
        ];

        $pdf = Pdf::loadView('dtr.daily-attendance', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'DTR_' . $employee->employee_number . '_' .
            Carbon::parse($validated['date'])->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Get DTR data for preview
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $employee = Employee::with(['positionRate', 'project'])->findOrFail($validated['employee_id']);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$validated['date_from'], $validated['date_to']])
            ->orderBy('attendance_date', 'asc')
            ->get();

        // Calculate totals
        $totalRegularHours = $attendance->sum('regular_hours');
        $totalOvertimeHours = $attendance->sum('overtime_hours');
        $totalLateHours = $attendance->sum('late_hours');
        $totalUndertimeHours = $attendance->sum('undertime_hours');
        $totalDays = $attendance->where('status', 'present')->count();

        return response()->json([
            'employee' => $employee,
            'attendance' => $attendance,
            'totals' => [
                'regular_hours' => $totalRegularHours,
                'overtime_hours' => $totalOvertimeHours,
                'late_hours' => $totalLateHours,
                'undertime_hours' => $totalUndertimeHours,
                'days_present' => $totalDays,
            ],
        ]);
    }

    private function calculateSSS($grossPay)
    {
        // SSS calculation - 2024 rates (semi-monthly deduction)
        // Estimate monthly salary by doubling the gross pay for semi-monthly payroll
        $monthlySalary = $grossPay * 2;
        
        if ($monthlySalary < 4250) return 180;
        if ($monthlySalary < 4750) return 202.50;
        if ($monthlySalary < 5250) return 225;
        if ($monthlySalary < 5750) return 247.50;
        if ($monthlySalary < 6250) return 270;
        if ($monthlySalary < 6750) return 292.50;
        if ($monthlySalary < 7250) return 315;
        if ($monthlySalary < 7750) return 337.50;
        if ($monthlySalary < 8250) return 360;
        if ($monthlySalary < 8750) return 382.50;
        if ($monthlySalary < 9250) return 405;
        if ($monthlySalary < 9750) return 427.50;
        if ($monthlySalary < 10250) return 450;
        if ($monthlySalary < 10750) return 472.50;
        if ($monthlySalary < 11250) return 495;
        if ($monthlySalary < 11750) return 517.50;
        if ($monthlySalary < 12250) return 540;
        if ($monthlySalary < 12750) return 562.50;
        if ($monthlySalary < 13250) return 585;
        if ($monthlySalary < 13750) return 607.50;
        if ($monthlySalary < 14250) return 630;
        if ($monthlySalary < 14750) return 652.50;
        if ($monthlySalary < 15250) return 675;
        if ($monthlySalary < 15750) return 697.50;
        if ($monthlySalary < 16250) return 720;
        if ($monthlySalary < 16750) return 742.50;
        if ($monthlySalary < 17250) return 765;
        if ($monthlySalary < 17750) return 787.50;
        if ($monthlySalary < 18250) return 810;
        if ($monthlySalary < 18750) return 832.50;
        if ($monthlySalary < 19250) return 855;
        if ($monthlySalary < 19750) return 877.50;
        return 900; // Maximum
    }

    private function calculatePhilHealth($grossPay)
    {
        // PhilHealth 2024: 5% of basic salary (2.5% employee share)
        // Estimate monthly salary by doubling the gross pay for semi-monthly payroll
        $monthlySalary = $grossPay * 2;
        $contribution = $monthlySalary * 0.05;
        $employeeShare = $contribution / 2;
        
        // Minimum: PHP 450, Maximum: PHP 1,800 per month (semi-monthly: 225-900)
        $monthlyEmployeeShare = min(max($employeeShare, 450), 1800);
        
        // Return semi-monthly amount
        return $monthlyEmployeeShare / 2;
    }

    private function calculatePagibig($grossPay)
    {
        // Pag-IBIG: 2% of monthly salary
        // Estimate monthly salary by doubling the gross pay for semi-monthly payroll
        $monthlySalary = $grossPay * 2;
        $monthlyContribution = $monthlySalary * 0.02;
        
        // Maximum of PHP 100 per month
        $monthlyContribution = min($monthlyContribution, 100);
        
        // Return semi-monthly amount
        return $monthlyContribution / 2;
    }
}