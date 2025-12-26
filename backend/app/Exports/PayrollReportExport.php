<?php

namespace App\Exports;

use App\Models\Payroll;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PayrollReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $payroll;
    protected $includeSignatures;

    public function __construct(Payroll $payroll, $includeSignatures = true)
    {
        $this->payroll = $payroll;
        $this->includeSignatures = $includeSignatures;
    }

    public function collection()
    {
        return $this->payroll->payslips()->with('employee')->get();
    }

    public function headings(): array
    {
        return [
            'Employee No.',
            'Employee Name',
            'Position',
            'Department',
            'Basic Salary',
            'Allowances',
            'Gross Pay',
            'SSS',
            'PhilHealth',
            'Pag-IBIG',
            'Other Deductions',
            'Total Deductions',
            'Net Pay',
            $this->includeSignatures ? 'Signature' : null,
        ];
    }

    public function map($payslip): array
    {
        return [
            $payslip->employee->employee_number ?? 'N/A',
            $payslip->employee->first_name . ' ' . $payslip->employee->last_name,
            $payslip->employee->position ?? 'N/A',
            $payslip->employee->department->name ?? 'N/A',
            number_format($payslip->basic_salary, 2),
            number_format($payslip->allowances, 2),
            number_format($payslip->gross_pay, 2),
            number_format($payslip->sss_contribution, 2),
            number_format($payslip->philhealth_contribution, 2),
            number_format($payslip->pagibig_contribution, 2),
            number_format($payslip->other_deductions, 2),
            number_format($payslip->total_deductions, 2),
            number_format($payslip->net_pay, 2),
            $this->includeSignatures ? '_____________________' : null,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Add title rows
        $sheet->insertNewRowBefore(1, 3);
        $sheet->setCellValue('A1', 'PAYROLL REPORT');
        $sheet->setCellValue('A2', 'Period: ' . $this->payroll->period_start . ' to ' . $this->payroll->period_end);
        $sheet->setCellValue('A3', 'Payment Date: ' . $this->payroll->payment_date);

        // Merge title cells
        $sheet->mergeCells('A1:M1');
        $sheet->mergeCells('A2:M2');
        $sheet->mergeCells('A3:M3');

        // Style title rows
        $sheet->getStyle('A1:A3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Style header row
        $headerRow = 4;
        $sheet->getStyle('A' . $headerRow . ':M' . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Style data rows
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A5:M' . $lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);

        // Add signature section if included
        if ($this->includeSignatures) {
            $signatureRow = $lastRow + 3;
            $sheet->setCellValue('A' . $signatureRow, 'Prepared by:');
            $sheet->setCellValue('E' . $signatureRow, 'Checked by:');
            $sheet->setCellValue('I' . $signatureRow, 'Approved by:');
            
            $sheet->setCellValue('A' . ($signatureRow + 3), '_____________________');
            $sheet->setCellValue('E' . ($signatureRow + 3), '_____________________');
            $sheet->setCellValue('I' . ($signatureRow + 3), '_____________________');
            
            $sheet->setCellValue('A' . ($signatureRow + 4), 'Accountant');
            $sheet->setCellValue('E' . ($signatureRow + 4), 'HR Manager');
            $sheet->setCellValue('I' . ($signatureRow + 4), 'Administrator');
        }

        return [];
    }

    public function title(): string
    {
        return 'Payroll Report';
    }
}
