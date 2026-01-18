<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .payslip-title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }

        .info-section {
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            width: 150px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background-color: #f0f0f0;
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }

        td {
            padding: 8px;
            border: 1px solid #ddd;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .net-pay {
            font-size: 16px;
            font-weight: bold;
            margin-top: 20px;
            padding: 15px;
            background-color: #e8f5e9;
            border: 2px solid #4caf50;
            text-align: center;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">Construction Company</div>
        <div>Payroll System</div>
        <div class="payslip-title">PAYSLIP</div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <div><span class="info-label">Employee Name:</span> {{ $payslip->employee->full_name }}</div>
            <div><span class="info-label">Employee No:</span> {{ $payslip->employee->employee_number }}</div>
        </div>
        <div class="info-row">
            <div><span class="info-label">Department:</span> {{ $payslip->employee->department->name ?? 'N/A' }}</div>
            <div><span class="info-label">Position:</span> {{ $payslip->employee->position }}</div>
        </div>
        <div class="info-row">
            <div><span class="info-label">Pay Period:</span> {{ date('M d, Y', strtotime($payslip->payroll->period_start)) }} - {{ date('M d, Y', strtotime($payslip->payroll->period_end)) }}</div>
            <div><span class="info-label">Pay Date:</span> {{ date('M d, Y', strtotime($payslip->payroll->pay_date)) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <!-- Earnings -->
            <tr class="total-row">
                <td colspan="2"><strong>EARNINGS</strong></td>
            </tr>
            <tr>
                <td>Basic Salary</td>
                <td class="text-right">₱{{ number_format($payslip->basic_pay ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Regular Overtime Pay</td>
                <td class="text-right">₱{{ number_format($payslip->regular_ot_pay ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Special Overtime Pay</td>
                <td class="text-right">₱{{ number_format($payslip->special_ot_pay ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Night Differential</td>
                <td class="text-right">₱{{ number_format($payslip->night_differential, 2) }}</td>
            </tr>
            <tr>
                <td>Allowances</td>
                <td class="text-right">₱{{ number_format($payslip->allowances, 2) }}</td>
            </tr>
            <tr>
                <td>Bonuses</td>
                <td class="text-right">₱{{ number_format($payslip->bonuses, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td><strong>Total Gross Pay</strong></td>
                <td class="text-right"><strong>₱{{ number_format($payslip->gross_pay, 2) }}</strong></td>
            </tr>

            <!-- Deductions -->
            <tr class="total-row">
                <td colspan="2"><strong>DEDUCTIONS</strong></td>
            </tr>
            <tr>
                <td>SSS Contribution</td>
                <td class="text-right">₱{{ number_format($payslip->sss, 2) }}</td>
            </tr>
            <tr>
                <td>PhilHealth Contribution</td>
                <td class="text-right">₱{{ number_format($payslip->philhealth, 2) }}</td>
            </tr>
            <tr>
                <td>Pag-IBIG Contribution</td>
                <td class="text-right">₱{{ number_format($payslip->pagibig, 2) }}</td>
            </tr>
            <tr>
                <td>Withholding Tax</td>
                <td class="text-right">₱{{ number_format($payslip->withholding_tax, 2) }}</td>
            </tr>
            <tr>
                <td>Loan Deductions</td>
                <td class="text-right">₱{{ number_format($payslip->loan_deductions, 2) }}</td>
            </tr>
            <tr>
                <td>Other Deductions</td>
                <td class="text-right">₱{{ number_format($payslip->other_deductions, 2) }}</td>
            </tr>
            <tr>
                <td>Absences</td>
                <td class="text-right">₱{{ number_format($payslip->absences, 2) }}</td>
            </tr>
            <tr>
                <td>Late/Undertime</td>
                <td class="text-right">₱{{ number_format($payslip->late_undertime, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td><strong>Total Deductions</strong></td>
                <td class="text-right"><strong>₱{{ number_format($payslip->total_deductions, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="net-pay">
        NET PAY: ₱{{ number_format($payslip->net_pay, 2) }}
    </div>

    <div class="footer">
        <p>This is a system-generated payslip. No signature required.</p>
        <p>Generated on {{ date('F d, Y h:i A') }}</p>
    </div>
</body>

</html>