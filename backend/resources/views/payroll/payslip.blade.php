<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #333;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .payroll-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }

        .period-info {
            font-size: 13px;
            margin-top: 5px;
        }

        .section {
            margin-bottom: 15px;
        }

        .section-title {
            background-color: #f0f0f0;
            padding: 5px 10px;
            font-weight: bold;
            margin-bottom: 10px;
            border-left: 4px solid #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table td {
            padding: 6px 10px;
            border: 1px solid #ddd;
        }

        .label {
            font-weight: bold;
            width: 40%;
            background-color: #f9f9f9;
        }

        .value {
            text-align: right;
        }

        .total-row {
            background-color: #e8e8e8;
            font-weight: bold;
            font-size: 14px;
        }

        .net-pay {
            background-color: #4CAF50;
            color: white;
            font-size: 16px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #333;
            text-align: center;
            font-size: 11px;
        }

        .acknowledgment {
            margin-top: 40px;
            font-size: 11px;
            font-style: italic;
            text-align: center;
        }

        .signature-section {
            margin-top: 50px;
            display: table;
            width: 100%;
        }

        .signature-box {
            display: table-cell;
            width: 50%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin: 40px 50px 5px 50px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">GIOVANNI CONSTRUCTION</div>
        <div>Imadejas Subdivision, Capitol Bonbon</div>
        <div class="payroll-title">P A Y R O L L</div>
        <div class="period-info">{{ $payroll->period_name }} ({{ \Carbon\Carbon::parse($payroll->period_start)->format('F d, Y') }} - {{ \Carbon\Carbon::parse($payroll->period_end)->format('F d, Y') }})</div>
    </div>

    <!-- Employee Information -->
    <div class="section">
        <div class="section-title">EMPLOYEE INFORMATION</div>
        <table>
            <tr>
                <td class="label">NAME</td>
                <td>{{ $employee->first_name }} {{ $employee->middle_name }} {{ $employee->last_name }}</td>
                <td class="label">EMPLOYEE #</td>
                <td>{{ $employee->employee_number }}</td>
            </tr>
            <tr>
                <td class="label">POSITION</td>
                <td>{{ $employee->position->position_name ?? ($employee->staff_type ?? 'N/A') }}</td>
                <td class="label">DEPARTMENT</td>
                <td>{{ $employee->department ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <!-- Earnings -->
    <div class="section">
        <div class="section-title">EARNINGS</div>
        <table>
            <tr>
                <td class="label">RATE</td>
                <td class="value">PHP {{ number_format($item->rate, 2) }}</td>
            </tr>
            <tr>
                <td class="label">No. of Days</td>
                <td class="value">{{ $item->days_worked }}</td>
            </tr>
            <tr>
                <td class="label">BASIC PAY (AMOUNT)</td>
                <td class="value">PHP {{ number_format($item->basic_pay, 2) }}</td>
            </tr>
            @if($item->holiday_pay > 0)
            <tr>
                <td class="label">HOLIDAY PAY</td>
                <td class="value">PHP {{ number_format($item->holiday_pay, 2) }}</td>
            </tr>
            @endif
            @if($item->regular_ot_hours > 0)
            <tr>
                <td class="label">REGULAR OT (HRS)</td>
                <td class="value">{{ $item->regular_ot_hours }}</td>
            </tr>
            <tr>
                <td class="label">REGULAR OT PAY</td>
                <td class="value">PHP {{ number_format($item->regular_ot_pay, 2) }}</td>
            </tr>
            @endif
            @if($item->special_ot_hours > 0)
            <tr>
                <td class="label">SPECIAL OT (HRS)</td>
                <td class="value">{{ $item->special_ot_hours }}</td>
            </tr>
            <tr>
                <td class="label">SPECIAL OT PAY</td>
                <td class="value">PHP {{ number_format($item->special_ot_pay, 2) }}</td>
            </tr>
            @endif
            @if($item->cola > 0)
            <tr>
                <td class="label">COLA</td>
                <td class="value">PHP {{ number_format($item->cola, 2) }}</td>
            </tr>
            @endif
            @if(!empty($item->allowances_breakdown) && count($item->allowances_breakdown) > 0)
            @foreach($item->allowances_breakdown as $allowance)
            <tr>
                <td class="label">{{ strtoupper($allowance['name']) }}</td>
                <td class="value">PHP {{ number_format($allowance['amount'], 2) }}</td>
            </tr>
            @endforeach
            @elseif($item->other_allowances > 0)
            <tr>
                <td class="label">OTHER ALLOWANCES</td>
                <td class="value">PHP {{ number_format($item->other_allowances, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="label">GROSS AMOUNT</td>
                <td class="value">PHP {{ number_format($item->gross_pay, 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- Deductions -->
    <div class="section">
        <div class="section-title">DEDUCTIONS</div>
        <table>
            @if($item->employee_savings > 0)
            <tr>
                <td class="label">Employee's Savings</td>
                <td class="value">PHP {{ number_format($item->employee_savings, 2) }}</td>
            </tr>
            @endif
            @if($item->cash_advance > 0)
            <tr>
                <td class="label">Cash Advance</td>
                <td class="value">PHP {{ number_format($item->cash_advance, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">SSS Contribution</td>
                <td class="value">PHP {{ number_format($item->sss ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td class="label">PhilHealth Contribution (PHIC)</td>
                <td class="value">PHP {{ number_format($item->philhealth ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Pag-IBIG Contribution</td>
                <td class="value">PHP {{ number_format($item->pagibig ?? 0, 2) }}</td>
            </tr>
            @if($item->loans > 0)
            <tr>
                <td class="label">Loans</td>
                <td class="value">PHP {{ number_format($item->loans, 2) }}</td>
            </tr>
            @endif
            @if($item->other_deductions > 0)
            <tr>
                <td class="label">Other Deductions</td>
                <td class="value">PHP {{ number_format($item->other_deductions, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="label">TOTAL DEDUCTIONS</td>
                <td class="value">PHP {{ number_format($item->total_deductions, 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- Net Pay -->
    <div class="section">
        <table>
            <tr class="net-pay">
                <td class="label">NET AMOUNT</td>
                <td class="value"><?php echo 'PHP '; ?>{{ number_format($item->net_pay, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="acknowledgment">
        "I hereby acknowledge that the computation and total of my salary stated above for the given period is correct."
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div>PREPARED BY:</div>
            <div class="signature-line"></div>
            <div>{{ $payroll->creator->name ?? 'System' }}</div>
        </div>
        <div class="signature-box">
            <div>EMPLOYEE SIGNATURE:</div>
            <div class="signature-line"></div>
            <div>{{ $employee->first_name }} {{ $employee->last_name }}</div>
        </div>
    </div>

    <div class="footer">
        Generated on {{ now()->format('F d, Y h:i A') }}
    </div>
</body>

</html>