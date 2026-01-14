<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceSummaryExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles
{
    protected $attendances;
    protected $dateFrom;
    protected $dateTo;

    public function __construct($attendances, $dateFrom, $dateTo)
    {
        $this->attendances = $attendances;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function collection()
    {
        return $this->attendances;
    }

    public function headings(): array
    {
        return [
            'Employee Number',
            'Employee Name',
            'Date',
            'Time In',
            'Break Start',
            'Break End',
            'Time Out',
            'Regular Hours',
            'Overtime Hours',
            'Night Diff Hours',
            'Late Hours',
            'Status',
            'Approved',
            'Manual Entry',
        ];
    }

    public function map($attendance): array
    {
        return [
            $attendance->employee->employee_number ?? '',
            $attendance->employee->full_name ?? '',
            $attendance->attendance_date,
            $attendance->time_in ?? '',
            $attendance->break_start ?? '',
            $attendance->break_end ?? '',
            $attendance->time_out ?? '',
            $attendance->regular_hours ?? 0,
            $attendance->overtime_hours ?? 0,
            $attendance->night_differential_hours ?? 0,
            $attendance->late_hours ?? 0,
            strtoupper($attendance->status),
            $attendance->is_approved ? 'Yes' : 'No',
            $attendance->is_manual_entry ? 'Yes' : 'No',
        ];
    }

    public function title(): string
    {
        return 'Attendance ' . $this->dateFrom . ' to ' . $this->dateTo;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
