<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Carbon\Carbon;

class PayrollExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, WithColumnFormatting, WithEvents
{
    protected $payroll;
    protected $items;
    protected $rowCount;

    public function __construct($payroll)
    {
        $this->payroll = $payroll;
        $this->items = $payroll->items()->with(['employee.positionRate'])->get();
        $this->rowCount = $this->items->count();
    }

    public function collection()
    {
        return $this->items;
    }

    public function headings(): array
    {
        // Two-row header to match PDF format with merged OVERTIME header
        return [
            [
                'NAME',
                'RATE',
                'No. of Days',
                'AMOUNT',
                'OVERTIME', '', '', '',  // This will be merged
                'Adj. Prev. Salary',
                'Allowance',
                'GROSS AMOUNT',
                'Employee\'s Savings',
                'Loans',
                'Deductions',
                'Phic Prem',
                'HDMF Prem',
                'SSS Prem',
                'NET AMOUNT',
                'SIGNATURE',
            ],
            [
                '', '', '', '',
                'HRS', 'REG OT', 'HRS', 'SUN/SPL. HOL.',  // OVERTIME subheaders
                '', '', '', '', '', '', '', '', '', '', '',
            ],
        ];
    }

    public function map($item): array
    {
        static $index = 0;
        $index++;

        return [
            $index . '. ' . ($item->employee->full_name ?? ''),
            $item->effective_rate ?? $item->rate ?? 0,
            $this->formatDays($item->days_worked ?? 0),
            $item->basic_pay ?? 0,
            $item->regular_ot_hours > 0 ? $item->regular_ot_hours : '',
            $item->regular_ot_pay > 0 ? $item->regular_ot_pay : '',
            $item->special_ot_hours > 0 ? $item->special_ot_hours : '',
            $item->special_ot_pay > 0 ? $item->special_ot_pay : '',
            $item->salary_adjustment != 0 ? $item->salary_adjustment : '',
            $item->other_allowances > 0 ? $item->other_allowances : '',,
            $item->gross_pay ?? 0,
            $item->employee_savings > 0 ? $item->employee_savings : '',
            $item->loans > 0 ? $item->loans : '',
            $item->employee_deductions > 0 ? $item->employee_deductions : '',
            $item->philhealth > 0 ? $item->philhealth : '',
            $item->pagibig > 0 ? $item->pagibig : '',
            $item->sss > 0 ? $item->sss : '',
            $item->net_pay ?? 0,
            '', // SIGNATURE column (empty for user to sign)
        ];
    }

    private function formatDays($days)
    {
        return rtrim(rtrim(number_format($days, 2), '0'), '.');
    }

    public function title(): string
    {
        return 'Payroll - ' . $this->payroll->period_name;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Get company name (you can customize this)
                $companyName = 'GIOVANNI CONSTRUCTION';
                $companyAddress = 'Imadejas Subdivision, Capitol Bonbon';
                $periodStart = Carbon::parse($this->payroll->period_start)->format('F d');
                $periodEnd = Carbon::parse($this->payroll->period_end)->format('d, Y');

                // Insert header rows at the top
                $sheet->insertNewRowBefore(1, 6);

                // Add company header
                $sheet->setCellValue('A1', $companyName);
                $sheet->mergeCells('A1:S1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A2', $companyAddress);
                $sheet->mergeCells('A2:S2');
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A4', 'P A Y R O L L');
                $sheet->mergeCells('A4:S4');
                $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $sheet->setCellValue('A5', $periodStart . ' - ' . $periodEnd);
                $sheet->mergeCells('A5:S5');
                $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Merge header rows (row 7 is first header row, row 8 is second header row)
                // Merge OVERTIME columns in row 7
                $sheet->mergeCells('E7:H7');

                // Merge cells for row 7 that span both rows (all except OVERTIME)
                $sheet->mergeCells('A7:A8');  // NAME
                $sheet->mergeCells('B7:B8');  // RATE
                $sheet->mergeCells('C7:C8');  // No. of Days
                $sheet->mergeCells('D7:D8');  // AMOUNT
                $sheet->mergeCells('I7:I8');  // Adj. Prev. Salary
                $sheet->mergeCells('J7:J8');  // Allowance
                $sheet->mergeCells('K7:K8');  // GROSS AMOUNT
                $sheet->mergeCells('L7:L8');  // Employee's Savings
                $sheet->mergeCells('M7:M8');  // Loans
                $sheet->mergeCells('N7:N8');  // Deductions
                $sheet->mergeCells('O7:O8');  // Phic Prem
                $sheet->mergeCells('P7:P8');  // HDMF Prem
                $sheet->mergeCells('Q7:Q8');  // SSS Prem
                $sheet->mergeCells('R7:R8');  // NET AMOUNT
                $sheet->mergeCells('S7:S8');  // SIGNATURE

                // Style header rows
                $headerStyle = [
                    'font' => ['bold' => true, 'size' => 9],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ];
                $sheet->getStyle('A7:S8')->applyFromArray($headerStyle);

                // Calculate data range (starts at row 9, after headers)
                $dataStartRow = 9;
                $dataEndRow = $dataStartRow + $this->rowCount - 1;

                // Apply borders to data rows
                $dataStyle = [
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                    'font' => ['size' => 9],
                ];
                $sheet->getStyle("A{$dataStartRow}:S{$dataEndRow}")->applyFromArray($dataStyle);

                // Add "nothing follows" row
                $nothingFollowsRow = $dataEndRow + 1;
                $sheet->setCellValue("A{$nothingFollowsRow}", 'nothing follows');
                $sheet->mergeCells("A{$nothingFollowsRow}:S{$nothingFollowsRow}");
                $sheet->getStyle("A{$nothingFollowsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$nothingFollowsRow}")->getFont()->setItalic(true)->setSize(9);
                $sheet->getStyle("A{$nothingFollowsRow}:S{$nothingFollowsRow}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                // Add TOTAL row
                $totalRow = $nothingFollowsRow + 1;
                $sheet->setCellValue("A{$totalRow}", 'T O T A L');
                $sheet->getStyle("A{$totalRow}")->getFont()->setBold(true);

                // Calculate totals
                $sheet->setCellValue("D{$totalRow}", $this->items->sum('basic_pay'));
                $sheet->setCellValue("E{$totalRow}", $this->items->sum('regular_ot_hours'));
                $sheet->setCellValue("F{$totalRow}", $this->items->sum('regular_ot_pay'));
                $sheet->setCellValue("G{$totalRow}", $this->items->sum('special_ot_hours'));
                $sheet->setCellValue("H{$totalRow}", $this->items->sum('special_ot_pay'));
                $sheet->setCellValue("I{$totalRow}", $this->items->sum('salary_adjustment'));
                $sheet->setCellValue("J{$totalRow}", $this->items->sum('other_allowances'));
                $sheet->setCellValue("K{$totalRow}", $this->items->sum('gross_pay'));
                $sheet->setCellValue("L{$totalRow}", $this->items->sum('employee_savings'));
                $sheet->setCellValue("M{$totalRow}", $this->items->sum('loans'));
                $sheet->setCellValue("N{$totalRow}", $this->items->sum('employee_deductions'));
                $sheet->setCellValue("O{$totalRow}", $this->items->sum('philhealth'));
                $sheet->setCellValue("P{$totalRow}", $this->items->sum('pagibig'));
                $sheet->setCellValue("Q{$totalRow}", $this->items->sum('sss'));
                $sheet->setCellValue("R{$totalRow}", $this->items->sum('net_pay'));

                $totalStyle = [
                    'font' => ['bold' => true, 'size' => 9],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ];
                $sheet->getStyle("A{$totalRow}:S{$totalRow}")->applyFromArray($totalStyle);

                // Add acknowledgment
                $ackRow = $totalRow + 2;
                $sheet->setCellValue("A{$ackRow}", '"I hereby acknowledge that the computation and total of my salary stated above for the given period is correct."');
                $sheet->mergeCells("A{$ackRow}:S{$ackRow}");
                $sheet->getStyle("A{$ackRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$ackRow}")->getFont()->setItalic(true)->setSize(8);

                // Add signature section
                $sigRow = $ackRow + 2;
                $sheet->setCellValue("A{$sigRow}", 'PREPARED BY:');
                $sheet->setCellValue("F{$sigRow}", 'CHECKED AND VERIFIED BY:');
                $sheet->setCellValue("K{$sigRow}", 'RECOMMENDED BY:');
                $sheet->setCellValue("P{$sigRow}", 'APPROVED BY:');

                $sigNameRow = $sigRow + 1;
                $sheet->setCellValue("A{$sigNameRow}", 'MERCIEL LAVASAN');
                $sheet->setCellValue("F{$sigNameRow}", 'SAIRAH JENITA');
                $sheet->setCellValue("K{$sigNameRow}", 'ENGR. FRANCIS GIOVANNI C. RIVERA');
                $sheet->setCellValue("P{$sigNameRow}", 'ENGR. OSTRIC R. RIVERA JR.');
                $sheet->getStyle("A{$sigNameRow}:S{$sigNameRow}")->getFont()->setBold(true)->setSize(8);

                $sigNameRow2 = $sigNameRow + 1;
                $sheet->setCellValue("F{$sigNameRow2}", 'PAICA CRISTEL MAE SUGABO');
                $sheet->setCellValue("K{$sigNameRow2}", 'ENGR. OSTRIC C. RIVERA, III');
                $sheet->setCellValue("P{$sigNameRow2}", 'ENG. ELISA MAY PARCON');
                $sheet->getStyle("A{$sigNameRow2}:S{$sigNameRow2}")->getFont()->setBold(true)->setSize(8);

                // Set column widths
                $sheet->getColumnDimension('A')->setWidth(25);  // NAME
                $sheet->getColumnDimension('B')->setWidth(10);  // RATE
                $sheet->getColumnDimension('C')->setWidth(8);   // No. of Days
                $sheet->getColumnDimension('D')->setWidth(12);  // AMOUNT
                $sheet->getColumnDimension('E')->setWidth(6);   // OT HRS
                $sheet->getColumnDimension('F')->setWidth(10);  // REG OT
                $sheet->getColumnDimension('G')->setWidth(6);   // SPE HRS
                $sheet->getColumnDimension('H')->setWidth(12);  // SUN/SPL. HOL.
                $sheet->getColumnDimension('I')->setWidth(14);  // Adj. Prev. Salary
                $sheet->getColumnDimension('J')->setWidth(10);  // Allowance
                $sheet->getColumnDimension('K')->setWidth(12);  // GROSS AMOUNT
                $sheet->getColumnDimension('L')->setWidth(12);  // Employee's Savings
                $sheet->getColumnDimension('M')->setWidth(10);  // Loans
                $sheet->getColumnDimension('N')->setWidth(10);  // Deductions
                $sheet->getColumnDimension('O')->setWidth(10);  // Phic Prem
                $sheet->getColumnDimension('P')->setWidth(10);  // HDMF Prem
                $sheet->getColumnDimension('Q')->setWidth(10);  // SSS Prem
                $sheet->getColumnDimension('R')->setWidth(12);  // NET AMOUNT
                $sheet->getColumnDimension('S')->setWidth(12);  // SIGNATURE

                // Set page to landscape
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Basic styles applied in registerEvents instead
        return [];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Rate
            'D' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Basic Pay
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // REG OT
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // SUN/SPL. HOL.
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // COLA
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Allowance
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Gross Pay
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Employee's Savings
            'M' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Loans
            'N' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Deductions
            'O' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // PhilHealth
            'P' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Pag-IBIG
            'Q' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // SSS
            'R' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1, // Net Pay
        ];
    }
}
