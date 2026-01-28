<?php

namespace App\Exports;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

class PayrollWordExport
{
    protected $payroll;
    protected $items;

    public function __construct($payroll)
    {
        $this->payroll = $payroll;
        $this->items = $payroll->items()->with('employee')->get();
    }

    public function generate()
    {
        $phpWord = new PhpWord();
        
        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setTitle('Payroll Register - ' . $this->payroll->period_name);
        $properties->setCreator('Payroll System');
        $properties->setDescription('Payroll Register for ' . $this->payroll->period_name);

        // Add section
        $section = $phpWord->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);

        // Add title
        $section->addText(
            'PAYROLL REGISTER',
            ['name' => 'Arial', 'size' => 16, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
        );

        // Add payroll information
        $section->addText(
            'Payroll Number: ' . $this->payroll->payroll_number,
            ['name' => 'Arial', 'size' => 11],
            ['spaceAfter' => 100]
        );
        $section->addText(
            'Period: ' . $this->payroll->period_name,
            ['name' => 'Arial', 'size' => 11],
            ['spaceAfter' => 100]
        );
        $section->addText(
            'Pay Period: ' . date('F d, Y', strtotime($this->payroll->period_start)) . ' - ' . date('F d, Y', strtotime($this->payroll->period_end)),
            ['name' => 'Arial', 'size' => 11],
            ['spaceAfter' => 100]
        );
        $section->addText(
            'Payment Date: ' . date('F d, Y', strtotime($this->payroll->payment_date)),
            ['name' => 'Arial', 'size' => 11],
            ['spaceAfter' => 300]
        );

        // Create table
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 50,
            'width' => 100 * 50, // 100% width
            'unit' => 'pct',
        ];
        $phpWord->addTableStyle('PayrollTable', $tableStyle);
        
        $table = $section->addTable('PayrollTable');

        // Add header row
        $headerStyle = [
            'bold' => true,
            'size' => 9,
            'name' => 'Arial',
        ];
        $headerCellStyle = [
            'bgColor' => 'E0E0E0',
            'valign' => 'center',
        ];
        
        $table->addRow(500);
        $table->addCell(1500, $headerCellStyle)->addText('Employee No.', $headerStyle);
        $table->addCell(2500, $headerCellStyle)->addText('Employee Name', $headerStyle);
        $table->addCell(2000, $headerCellStyle)->addText('Position', $headerStyle);
        $table->addCell(1200, $headerCellStyle)->addText('Rate', $headerStyle);
        $table->addCell(800, $headerCellStyle)->addText('Days', $headerStyle);
        $table->addCell(1500, $headerCellStyle)->addText('Basic Pay', $headerStyle);
        $table->addCell(1000, $headerCellStyle)->addText('OT Hrs', $headerStyle);
        $table->addCell(1500, $headerCellStyle)->addText('OT Pay', $headerStyle);
        $table->addCell(1200, $headerCellStyle)->addText('COLA', $headerStyle);
        $table->addCell(1500, $headerCellStyle)->addText('Allowances', $headerStyle);
        $table->addCell(1500, $headerCellStyle)->addText('Gross Pay', $headerStyle);
        $table->addCell(1200, $headerCellStyle)->addText('SSS', $headerStyle);
        $table->addCell(1200, $headerCellStyle)->addText('PhilHealth', $headerStyle);
        $table->addCell(1200, $headerCellStyle)->addText('Pag-IBIG', $headerStyle);
        $table->addCell(1200, $headerCellStyle)->addText('Loans', $headerStyle);
        $table->addCell(1500, $headerCellStyle)->addText('Emp. Ded.', $headerStyle);
        $table->addCell(1500, $headerCellStyle)->addText('Other Ded.', $headerStyle);
        $table->addCell(1500, $headerCellStyle)->addText('Total Ded.', $headerStyle);
        $table->addCell(1800, $headerCellStyle)->addText('Net Pay', $headerStyle);

        // Add data rows
        $cellStyle = [
            'size' => 9,
            'name' => 'Arial',
        ];
        
        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;

        foreach ($this->items as $item) {
            $table->addRow(400);
            $table->addCell(1500)->addText($item->employee->employee_number ?? '', $cellStyle);
            $table->addCell(2500)->addText($item->employee->full_name ?? '', $cellStyle);
            $table->addCell(2000)->addText($item->employee->position->name ?? '', $cellStyle);
            $table->addCell(1200)->addText(number_format($item->rate, 2), $cellStyle);
            $table->addCell(800)->addText(number_format($item->days_worked, 2), $cellStyle);
            $table->addCell(1500)->addText(number_format($item->basic_pay, 2), $cellStyle);
            $table->addCell(1000)->addText(number_format($item->overtime_hours ?? 0, 2), $cellStyle);
            $table->addCell(1500)->addText(number_format($item->regular_ot_pay ?? 0, 2), $cellStyle);
            $table->addCell(1200)->addText(number_format($item->cola ?? 0, 2), $cellStyle);
            $table->addCell(1500)->addText(number_format($item->other_allowances ?? 0, 2), $cellStyle);
            $table->addCell(1500)->addText(number_format($item->gross_pay, 2), $cellStyle);
            $table->addCell(1200)->addText(number_format($item->sss ?? 0, 2), $cellStyle);
            $table->addCell(1200)->addText(number_format($item->philhealth ?? 0, 2), $cellStyle);
            $table->addCell(1200)->addText(number_format($item->pagibig ?? 0, 2), $cellStyle);
            $table->addCell(1200)->addText(number_format($item->loans ?? 0, 2), $cellStyle);
            $table->addCell(1500)->addText(number_format($item->employee_savings ?? 0, 2), $cellStyle);
            $table->addCell(1500)->addText(number_format($item->other_deductions ?? 0, 2), $cellStyle);
            $table->addCell(1500)->addText(number_format($item->total_deductions, 2), $cellStyle);
            $table->addCell(1800)->addText(number_format($item->net_pay, 2), $cellStyle);

            $totalGross += $item->gross_pay;
            $totalDeductions += $item->total_deductions;
            $totalNet += $item->net_pay;
        }

        // Add total row
        $totalStyle = [
            'bold' => true,
            'size' => 9,
            'name' => 'Arial',
        ];
        $totalCellStyle = [
            'bgColor' => 'F0F0F0',
        ];

        $table->addRow(500);
        $table->addCell(9500, array_merge($totalCellStyle, ['gridSpan' => 10]))->addText('TOTAL', $totalStyle);
        $table->addCell(1500, $totalCellStyle)->addText(number_format($totalGross, 2), $totalStyle);
        $table->addCell(7400, array_merge($totalCellStyle, ['gridSpan' => 7]))->addText('', $totalStyle);
        $table->addCell(1500, $totalCellStyle)->addText(number_format($totalDeductions, 2), $totalStyle);
        $table->addCell(1800, $totalCellStyle)->addText(number_format($totalNet, 2), $totalStyle);

        // Add footer with summary
        $section->addTextBreak(2);
        $section->addText(
            'Summary:',
            ['name' => 'Arial', 'size' => 11, 'bold' => true],
            ['spaceAfter' => 100]
        );
        $section->addText(
            'Total Employees: ' . $this->items->count(),
            ['name' => 'Arial', 'size' => 10]
        );
        $section->addText(
            'Total Gross Pay: ₱' . number_format($totalGross, 2),
            ['name' => 'Arial', 'size' => 10]
        );
        $section->addText(
            'Total Deductions: ₱' . number_format($totalDeductions, 2),
            ['name' => 'Arial', 'size' => 10]
        );
        $section->addText(
            'Total Net Pay: ₱' . number_format($totalNet, 2),
            ['name' => 'Arial', 'size' => 10, 'bold' => true]
        );

        return $phpWord;
    }
}
