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
}
