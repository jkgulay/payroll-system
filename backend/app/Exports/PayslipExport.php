<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PayslipExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $payslip;

    public function __construct($payslip)
    {
        $this->payslip = $payslip;
    }

    public function collection()
    {
        // Return a collection with the payslip data
        return collect([
            ['label' => 'EMPLOYEE INFORMATION', 'value' => ''],
            ['label' => 'Employee Name', 'value' => $this->payslip->employee->full_name],
            ['label' => 'Employee Number', 'value' => $this->payslip->employee->employee_number],
            ['label' => 'Project', 'value' => $this->payslip->employee->project->name ?? 'N/A'],
            ['label' => 'Position', 'value' => $this->payslip->employee->position],
            ['label' => '', 'value' => ''],
            ['label' => 'PAYROLL PERIOD', 'value' => ''],
            ['label' => 'Period Start', 'value' => date('M d, Y', strtotime($this->payslip->payroll->period_start))],
            ['label' => 'Period End', 'value' => date('M d, Y', strtotime($this->payslip->payroll->period_end))],
            ['label' => 'Pay Date', 'value' => date('M d, Y', strtotime($this->payslip->payroll->pay_date))],
            ['label' => '', 'value' => ''],
            ['label' => 'EARNINGS', 'value' => ''],
            ['label' => 'Basic Salary', 'value' => number_format($this->payslip->basic_salary, 2)],
            ['label' => 'Overtime Pay', 'value' => number_format($this->payslip->overtime_pay, 2)],
            ['label' => 'Holiday Pay', 'value' => number_format($this->payslip->holiday_pay, 2)],
            ['label' => 'Night Differential', 'value' => number_format($this->payslip->night_differential, 2)],
            ['label' => 'Allowances', 'value' => number_format($this->payslip->allowances, 2)],
            ['label' => 'Bonuses', 'value' => number_format($this->payslip->bonuses, 2)],
            ['label' => 'Total Gross Pay', 'value' => number_format($this->payslip->gross_pay, 2)],
            ['label' => '', 'value' => ''],
            ['label' => 'DEDUCTIONS', 'value' => ''],
            ['label' => 'SSS Contribution', 'value' => number_format($this->payslip->sss_contribution, 2)],
            ['label' => 'PhilHealth Contribution', 'value' => number_format($this->payslip->philhealth_contribution, 2)],
            ['label' => 'Pag-IBIG Contribution', 'value' => number_format($this->payslip->pagibig_contribution, 2)],
            ['label' => 'Withholding Tax', 'value' => number_format($this->payslip->withholding_tax, 2)],
            ['label' => 'Loan Deductions', 'value' => number_format($this->payslip->loan_deductions, 2)],
            ['label' => 'Other Deductions', 'value' => number_format($this->payslip->other_deductions, 2)],
            ['label' => 'Absences', 'value' => number_format($this->payslip->absences, 2)],
            ['label' => 'Late/Undertime', 'value' => number_format($this->payslip->late_undertime, 2)],
            ['label' => 'Total Deductions', 'value' => number_format($this->payslip->total_deductions, 2)],
            ['label' => '', 'value' => ''],
            ['label' => 'NET PAY', 'value' => number_format($this->payslip->net_pay, 2)],
        ]);
    }

    public function headings(): array
    {
        return [
            'Description',
            'Amount',
        ];
    }

    public function map($row): array
    {
        return [
            $row['label'],
            $row['value'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
            2 => ['font' => ['bold' => true]],
            7 => ['font' => ['bold' => true]],
            12 => ['font' => ['bold' => true]],
            21 => ['font' => ['bold' => true]],
            32 => ['font' => ['bold' => true, 'size' => 14]],
        ];
    }
}
