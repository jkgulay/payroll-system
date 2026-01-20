<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payroll Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }

        .header p {
            margin: 5px 0;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table th,
        table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }

        table th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }

        .signatures {
            margin-top: 40px;
            display: table;
            width: 100%;
        }

        .signature-block {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 10px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            margin-bottom: 5px;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }

        .signature-label {
            font-weight: bold;
            margin-bottom: 40px;
        }

        .signature-title {
            font-size: 10px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>PAYROLL REPORT</h1>
        <p>Period: {{ $payroll->period_start }} to {{ $payroll->period_end }}</p>
        <p>Payment Date: {{ $payroll->payment_date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Employee No.</th>
                <th>Employee Name</th>
                <th>Position</th>
                <th>Dept.</th>
                <th>Basic Salary</th>
                <th>Allowances</th>
                <th>Gross Pay</th>
                <th>SSS</th>
                <th>PhilHealth</th>
                <th>Pag-IBIG</th>
                <th>Other Ded.</th>
                <th>Total Ded.</th>
                <th>Net Pay</th>
                @if($includeSignatures)
                <th>Signature</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php
            $totalBasic = 0;
            $totalAllowances = 0;
            $totalGross = 0;
            $totalSSS = 0;
            $totalPhilHealth = 0;
            $totalPagIBIG = 0;
            $totalOtherDed = 0;
            $totalDeductions = 0;
            $totalNetPay = 0;
            @endphp

            @foreach($payslips as $payslip)
            @php
            $totalBasic += $payslip->basic_salary;
            $totalAllowances += $payslip->allowances ?? 0;
            $totalGross += $payslip->gross_pay;
            $totalSSS += $payslip->sss ?? 0;
            $totalPhilHealth += $payslip->philhealth ?? 0;
            $totalPagIBIG += $payslip->pagibig ?? 0;
            $totalOtherDed += $payslip->other_deductions ?? 0;
            $totalDeductions += $payslip->total_deductions;
            $totalNetPay += $payslip->net_pay;
            @endphp
            <tr>
                <td class="text-center">{{ $payslip->employee->employee_number ?? 'N/A' }}</td>
                <td>{{ $payslip->employee->first_name }} {{ $payslip->employee->last_name }}</td>
                <td>{{ $payslip->employee->position->position_name ?? ($payslip->employee->staff_type ?? 'N/A') }}</td>
                <td>{{ $payslip->employee->department->name ?? 'N/A' }}</td>
                <td class="text-right">₱{{ number_format($payslip->basic_salary, 2) }}</td>
                <td class="text-right">₱{{ number_format($payslip->allowances ?? 0, 2) }}</td>
                <td class="text-right">₱{{ number_format($payslip->gross_pay, 2) }}</td>
                <td class="text-right">₱{{ number_format($payslip->sss ?? 0, 2) }}</td>
                <td class="text-right">₱{{ number_format($payslip->philhealth ?? 0, 2) }}</td>
                <td class="text-right">₱{{ number_format($payslip->pagibig ?? 0, 2) }}</td>
                <td class="text-right">₱{{ number_format($payslip->other_deductions ?? 0, 2) }}</td>
                <td class="text-right">₱{{ number_format($payslip->total_deductions, 2) }}</td>
                <td class="text-right">₱{{ number_format($payslip->net_pay, 2) }}</td>
                @if($includeSignatures)
                <td class="text-center">_______________</td>
                @endif
            </tr>
            @endforeach

            <tr class="total-row">
                <td colspan="4" class="text-center">TOTAL</td>
                <td class="text-right">₱{{ number_format($totalBasic, 2) }}</td>
                <td class="text-right">₱{{ number_format($totalAllowances, 2) }}</td>
                <td class="text-right">₱{{ number_format($totalGross, 2) }}</td>
                <td class="text-right">₱{{ number_format($totalSSS, 2) }}</td>
                <td class="text-right">₱{{ number_format($totalPhilHealth, 2) }}</td>
                <td class="text-right">₱{{ number_format($totalPagIBIG, 2) }}</td>
                <td class="text-right">₱{{ number_format($totalOtherDed, 2) }}</td>
                <td class="text-right">₱{{ number_format($totalDeductions, 2) }}</td>
                <td class="text-right">₱{{ number_format($totalNetPay, 2) }}</td>
                @if($includeSignatures)
                <td></td>
                @endif
            </tr>
        </tbody>
    </table>

    @if($includeSignatures)
    <div class="signatures">
        <div class="signature-block">
            <div class="signature-label">Prepared by:</div>
            <div class="signature-line"></div>
            <div class="signature-title">Accountant</div>
        </div>
        <div class="signature-block">
            <div class="signature-label">Checked by:</div>
            <div class="signature-line"></div>
            <div class="signature-title">HR Manager</div>
        </div>
        <div class="signature-block">
            <div class="signature-label">Approved by:</div>
            <div class="signature-line"></div>
            <div class="signature-title">Administrator</div>
        </div>
    </div>
    @endif
</body>

</html>