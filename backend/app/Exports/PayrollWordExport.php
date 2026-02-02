<?php

namespace App\Exports;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;
use Carbon\Carbon;

class PayrollWordExport
{
    protected $payroll;
    protected $items;

    public function __construct($payroll)
    {
        $this->payroll = $payroll;
        $this->items = $payroll->items()->with(['employee.positionRate'])->get();
    }

    public function generate()
    {
        $phpWord = new PhpWord();

        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setTitle('Payroll - ' . $this->payroll->period_name);
        $properties->setCreator('Payroll System');
        $properties->setDescription('Payroll for ' . $this->payroll->period_name);

        // Add section with landscape orientation (A4 landscape: width=297mm, height=210mm)
        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'pageSizeW' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(29.7),
            'pageSizeH' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(21),
            'marginTop' => 500,
            'marginBottom' => 500,
            'marginLeft' => 500,
            'marginRight' => 500,
        ]);

        // Add company header
        $section->addText(
            'GIOVANNI CONSTRUCTION',
            ['name' => 'Arial', 'size' => 16, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );
        $section->addText(
            'Imadejas Subdivision, Capitol Bonbon',
            ['name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
        );

        // Add title
        $section->addText(
            'P A Y R O L L',
            ['name' => 'Arial', 'size' => 14, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );

        // Add period
        $periodStart = Carbon::parse($this->payroll->period_start)->format('F d');
        $periodEnd = Carbon::parse($this->payroll->period_end)->format('d, Y');
        $section->addText(
            $periodStart . ' - ' . $periodEnd,
            ['name' => 'Arial', 'size' => 10],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
        );

        // Create table
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 30,
            'width' => 100 * 50,
            'unit' => 'pct',
        ];
        $phpWord->addTableStyle('PayrollTable', $tableStyle);

        $table = $section->addTable('PayrollTable');

        // Header styles
        $headerStyle = [
            'bold' => true,
            'size' => 7,
            'name' => 'Arial',
        ];
        $headerCellStyle = [
            'valign' => 'center',
            'bgColor' => 'FFFFFF',
        ];

        // First header row with merged cells
        $table->addRow(300);
        $table->addCell(1800, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText('NAME', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText('RATE', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(600, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText("No. of\nDays", $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(900, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText('AMOUNT', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(2400, array_merge($headerCellStyle, ['gridSpan' => 4]))->addText('OVERTIME', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText('COLA', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(800, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText('Allowance', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(900, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText("GROSS\nAMOUNT", $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(900, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText("Employee's\nSavings", $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText('Loans', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(800, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText('Deductions', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText("Phic\nPrem", $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText("HDMF\nPrem", $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText("SSS\nPrem", $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(900, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText("NET\nAMOUNT", $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(800, array_merge($headerCellStyle, ['vMerge' => 'restart']))->addText('SIGNATURE', $headerStyle, ['alignment' => Jc::CENTER]);

        // Second header row (OVERTIME subheaders)
        $table->addRow(250);
        $table->addCell(1800, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(600, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(900, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(600, $headerCellStyle)->addText('HRS', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(600, $headerCellStyle)->addText('REG OT', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(600, $headerCellStyle)->addText('HRS', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(600, $headerCellStyle)->addText('SPE OT', $headerStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(800, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(900, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(900, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(800, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(700, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(900, array_merge($headerCellStyle, ['vMerge' => 'continue']));
        $table->addCell(800, array_merge($headerCellStyle, ['vMerge' => 'continue']));

        // Data cell styles
        $cellStyle = [
            'size' => 7,
            'name' => 'Arial',
        ];

        $totalGross = 0;
        $totalBasicPay = 0;
        $totalRegOtHours = 0;
        $totalRegOtPay = 0;
        $totalSpeOtHours = 0;
        $totalSpeOtPay = 0;
        $totalCola = 0;
        $totalAllowances = 0;
        $totalEmployeeSavings = 0;
        $totalLoans = 0;
        $totalEmployeeDeductions = 0;
        $totalPhilhealth = 0;
        $totalPagibig = 0;
        $totalSss = 0;
        $totalNet = 0;

        foreach ($this->items as $index => $item) {
            $table->addRow(280);
            $table->addCell(1800)->addText(($index + 1) . '. ' . ($item->employee->full_name ?? ''), $cellStyle, ['alignment' => Jc::LEFT]);
            $table->addCell(700)->addText(number_format($item->effective_rate ?? $item->rate ?? 0, 2), $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(600)->addText($this->formatDays($item->days_worked ?? 0), $cellStyle, ['alignment' => Jc::CENTER]);
            $table->addCell(900)->addText(number_format($item->basic_pay ?? 0, 2), $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(600)->addText($item->regular_ot_hours > 0 ? $item->regular_ot_hours : '', $cellStyle, ['alignment' => Jc::CENTER]);
            $table->addCell(600)->addText($item->regular_ot_pay > 0 ? number_format($item->regular_ot_pay, 2) : '', $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(600)->addText($item->special_ot_hours > 0 ? $item->special_ot_hours : '', $cellStyle, ['alignment' => Jc::CENTER]);
            $table->addCell(600)->addText($item->special_ot_pay > 0 ? number_format($item->special_ot_pay, 2) : '', $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(700)->addText($item->cola > 0 ? number_format($item->cola, 2) : '', $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(800)->addText($item->other_allowances > 0 ? number_format($item->other_allowances, 2) : '', $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(900)->addText(number_format($item->gross_pay ?? 0, 2), $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(900)->addText($item->employee_savings > 0 ? number_format($item->employee_savings, 2) : '', $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(700)->addText($item->loans > 0 ? number_format($item->loans, 2) : '', $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(800)->addText($item->employee_deductions > 0 ? number_format($item->employee_deductions, 2) : '', $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(700)->addText($item->philhealth > 0 ? number_format($item->philhealth, 2) : '', $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(700)->addText($item->pagibig > 0 ? number_format($item->pagibig, 2) : '', $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(700)->addText($item->sss > 0 ? number_format($item->sss, 2) : '', $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(900)->addText(number_format($item->net_pay ?? 0, 2), $cellStyle, ['alignment' => Jc::RIGHT]);
            $table->addCell(800)->addText('', $cellStyle);

            $totalBasicPay += $item->basic_pay ?? 0;
            $totalRegOtHours += $item->regular_ot_hours ?? 0;
            $totalRegOtPay += $item->regular_ot_pay ?? 0;
            $totalSpeOtHours += $item->special_ot_hours ?? 0;
            $totalSpeOtPay += $item->special_ot_pay ?? 0;
            $totalCola += $item->cola ?? 0;
            $totalAllowances += $item->other_allowances ?? 0;
            $totalGross += $item->gross_pay ?? 0;
            $totalEmployeeSavings += $item->employee_savings ?? 0;
            $totalLoans += $item->loans ?? 0;
            $totalEmployeeDeductions += $item->employee_deductions ?? 0;
            $totalPhilhealth += $item->philhealth ?? 0;
            $totalPagibig += $item->pagibig ?? 0;
            $totalSss += $item->sss ?? 0;
            $totalNet += $item->net_pay ?? 0;
        }

        // Add "nothing follows" row
        $table->addRow(250);
        $table->addCell(14600, ['gridSpan' => 19])->addText('nothing follows', ['italic' => true, 'size' => 7, 'name' => 'Arial'], ['alignment' => Jc::CENTER]);

        // Add TOTAL row
        $totalStyle = [
            'bold' => true,
            'size' => 7,
            'name' => 'Arial',
        ];

        $table->addRow(280);
        $table->addCell(1800)->addText('T O T A L', $totalStyle, ['alignment' => Jc::LEFT]);
        $table->addCell(700)->addText('', $totalStyle);
        $table->addCell(600)->addText('', $totalStyle);
        $table->addCell(900)->addText(number_format($totalBasicPay, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(600)->addText($totalRegOtHours > 0 ? $totalRegOtHours : '', $totalStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(600)->addText(number_format($totalRegOtPay, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(600)->addText($totalSpeOtHours > 0 ? $totalSpeOtHours : '', $totalStyle, ['alignment' => Jc::CENTER]);
        $table->addCell(600)->addText(number_format($totalSpeOtPay, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(700)->addText(number_format($totalCola, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(800)->addText(number_format($totalAllowances, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(900)->addText(number_format($totalGross, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(900)->addText(number_format($totalEmployeeSavings, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(700)->addText(number_format($totalLoans, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(800)->addText(number_format($totalEmployeeDeductions, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(700)->addText(number_format($totalPhilhealth, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(700)->addText(number_format($totalPagibig, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(700)->addText(number_format($totalSss, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(900)->addText(number_format($totalNet, 2), $totalStyle, ['alignment' => Jc::RIGHT]);
        $table->addCell(800)->addText('', $totalStyle);

        // Add acknowledgment
        $section->addTextBreak(1);
        $section->addText(
            '"I hereby acknowledge that the computation and total of my salary stated above for the given period is correct."',
            ['name' => 'Arial', 'size' => 8, 'italic' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
        );

        // Add signature section
        $signatureTable = $section->addTable([
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);

        $sigHeaderStyle = ['name' => 'Arial', 'size' => 7];
        $sigNameStyle = ['name' => 'Arial', 'size' => 7, 'bold' => true];

        $signatureTable->addRow(200);
        $signatureTable->addCell(3500)->addText('PREPARED BY:', $sigHeaderStyle, ['alignment' => Jc::CENTER]);
        $signatureTable->addCell(3500)->addText('CHECKED AND VERIFIED BY:', $sigHeaderStyle, ['alignment' => Jc::CENTER]);
        $signatureTable->addCell(3500)->addText('RECOMMENDED BY:', $sigHeaderStyle, ['alignment' => Jc::CENTER]);
        $signatureTable->addCell(3500)->addText('APPROVED BY:', $sigHeaderStyle, ['alignment' => Jc::CENTER]);

        $signatureTable->addRow(200);
        $signatureTable->addCell(3500)->addText('MERCIEL LAVASAN', $sigNameStyle, ['alignment' => Jc::CENTER]);
        $signatureTable->addCell(3500)->addText('SAIRAH JENITA', $sigNameStyle, ['alignment' => Jc::CENTER]);
        $signatureTable->addCell(3500)->addText('ENGR. FRANCIS GIOVANNI C. RIVERA', $sigNameStyle, ['alignment' => Jc::CENTER]);
        $cell = $signatureTable->addCell(3500);
        $cell->addText('ENGR. OSTRIC R. RIVERA JR.', $sigNameStyle, ['alignment' => Jc::CENTER]);
        $cell->addText('Proprietor/Manager', ['name' => 'Arial', 'size' => 6], ['alignment' => Jc::CENTER]);

        $signatureTable->addRow(200);
        $signatureTable->addCell(3500)->addText('', $sigNameStyle);
        $signatureTable->addCell(3500)->addText('PAICA CRISTEL MAE SUGABO', $sigNameStyle, ['alignment' => Jc::CENTER]);
        $signatureTable->addCell(3500)->addText('ENGR. OSTRIC C. RIVERA, III', $sigNameStyle, ['alignment' => Jc::CENTER]);
        $signatureTable->addCell(3500)->addText('ENG. ELISA MAY PARCON', $sigNameStyle, ['alignment' => Jc::CENTER]);

        return $phpWord;
    }

    private function formatDays($days)
    {
        return rtrim(rtrim(number_format($days, 2), '0'), '.');
    }
}
