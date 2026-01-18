<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class PayrollExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, WithColumnFormatting
{
    protected $payroll;
    protected $items;

    public function __construct($payroll)
    {
        $this->payroll = $payroll;
        $this->items = $payroll->items()->with('employee')->get();
    }

    public function collection()
    {
        return $this->items;
    }

    public function headings(): array
    {
        return [
            'Employee Number',
            'Employee Name',
            'Position',
            'Rate',
            'Days Worked',
            'Basic Pay',
            'Overtime Hours',
            'Overtime Pay',
            'COLA',
            'Other Allowances',
            'Gross Pay',
            'SSS',
            'PhilHealth',
            'Pag-IBIG',
            'Loans',
            'Employee Deductions',
            'Other Deductions',
            'Total Deductions',
            'Net Pay',
        ];
    }

    public function map($item): array
    {
        return [
            $item->employee->employee_number ?? '',
            $item->employee->full_name ?? '',
            $item->employee->position->name ?? '',
            $item->basic_rate,
            $item->days_worked,
            $item->basic_pay,
            $item->overtime_hours ?? 0,
            $item->overtime_pay ?? 0,
            $item->cola ?? 0,
            $item->other_allowances ?? 0,
            $item->gross_pay,
            $item->sss_contribution ?? 0,
            $item->philhealth_contribution ?? 0,
            $item->pagibig_contribution ?? 0,
            $item->total_loan_deductions ?? 0,
            $item->employee_deductions ?? 0,
            $item->total_other_deductions ?? 0,
            $item->total_deductions,
            $item->net_pay,
        ];
    }

    public function title(): string
    {
        return 'Payroll - ' . $this->payroll->period_name;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold and with background color
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0']
                ]
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Rate
            'E' => NumberFormat::FORMAT_NUMBER, // Days
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Basic Pay
            'G' => NumberFormat::FORMAT_NUMBER_00, // OT Hours
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // OT Pay
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // COLA
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Other Allowances
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Gross Pay
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // SSS
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // PhilHealth
            'N' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Pag-IBIG
            'O' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Loans
            'P' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Employee Deductions
            'Q' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Other Deductions
            'R' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Total Deductions
            'S' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Net Pay
        ];
    }
}
