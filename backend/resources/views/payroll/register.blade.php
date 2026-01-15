<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payroll Register</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 10px;
            font-size: 9px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
        }
        .title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 5px;
        }
        .period {
            font-size: 11px;
            margin-top: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #333;
            color: white;
            padding: 6px 4px;
            text-align: center;
            border: 1px solid #000;
            font-size: 8px;
        }
        td {
            padding: 4px 3px;
            border: 1px solid #ddd;
            text-align: center;
            font-size: 8px;
        }
        .text-left {
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 9px;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #333;
        }
        .signature-section {
            display: table;
            width: 100%;
            margin-top: 30px;
        }
        .signature-box {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 0 10px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin: 30px 10px 3px 10px;
        }
        .signature-title {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 5px;
        }
        .signature-name {
            font-size: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">Construction Company</div>
        <div class="title">PAYROLL REGISTER</div>
        <div class="period">
            Period: {{ \Carbon\Carbon::parse($payroll->period_start)->format('F d, Y') }} - {{ \Carbon\Carbon::parse($payroll->period_end)->format('F d, Y') }}<br>
            Payroll No: {{ $payroll->payroll_number }} | Payment Date: {{ \Carbon\Carbon::parse($payroll->payment_date)->format('F d, Y') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">No.</th>
                <th rowspan="2">Employee #</th>
                <th rowspan="2">Employee Name</th>
                <th rowspan="2">Position</th>
                <th rowspan="2">Rate</th>
                <th rowspan="2">Days</th>
                <th rowspan="2">Basic Pay</th>
                <th colspan="2">OT Hrs</th>
                <th colspan="2">OT Pay</th>
                <th rowspan="2">Allowances</th>
                <th rowspan="2">Gross</th>
                <th rowspan="2">SSS</th>
                <th rowspan="2">PHIC</th>
                <th rowspan="2">HDMF</th>
                <th rowspan="2">Tax</th>
                <th rowspan="2">Loans</th>
                <th rowspan="2">Other</th>
                <th rowspan="2">Net Pay</th>
                <th rowspan="2">Employee Signature</th>
            </tr>
            <tr>
                <th>REG</th>
                <th>SPE</th>
                <th>REG</th>
                <th>SPE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payroll->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->employee->employee_number }}</td>
                <td class="text-left">{{ $item->employee->first_name }} {{ $item->employee->last_name }}</td>
                <td class="text-left">{{ $item->employee->position->name ?? 'N/A' }}</td>
                <td class="text-right">{{ number_format($item->rate, 2) }}</td>
                <td>{{ $item->days_worked }}</td>
                <td class="text-right">{{ number_format($item->basic_pay, 2) }}</td>
                <td>{{ $item->regular_ot_hours }}</td>
                <td>{{ $item->special_ot_hours }}</td>
                <td class="text-right">{{ number_format($item->regular_ot_pay, 2) }}</td>
                <td class="text-right">{{ number_format($item->special_ot_pay, 2) }}</td>
                <td class="text-right">{{ number_format($item->cola + $item->other_allowances, 2) }}</td>
                <td class="text-right">{{ number_format($item->gross_pay, 2) }}</td>
                <td class="text-right">{{ number_format($item->sss, 2) }}</td>
                <td class="text-right">{{ number_format($item->philhealth, 2) }}</td>
                <td class="text-right">{{ number_format($item->pagibig, 2) }}</td>
                <td class="text-right">{{ number_format($item->withholding_tax, 2) }}</td>
                <td class="text-right">{{ number_format($item->loans, 2) }}</td>
                <td class="text-right">{{ number_format($item->employee_savings + $item->cash_advance + $item->other_deductions, 2) }}</td>
                <td class="text-right">{{ number_format($item->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL:</td>
                <td>{{ $payroll->items->sum('days_worked') }}</td>
                <td class="text-right">₱{{ number_format($payroll->items->sum('basic_pay'), 2) }}</td>
                <td>{{ $payroll->items->sum('regular_ot_hours') }}</td>
                <td>{{ $payroll->items->sum('special_ot_hours') }}</td>
                <td class="text-right">₱{{ number_format($payroll->items->sum('regular_ot_pay'), 2) }}</td>
                <td class="text-right">₱{{ number_format($payroll->items->sum('special_ot_pay'), 2) }}</td>
                <td class="text-right">₱{{ number_format($payroll->items->sum('cola') + $payroll->items->sum('other_allowances'), 2) }}</td>
                <td class="text-right">₱{{ number_format($payroll->total_gross, 2) }}</td>
                <td class="text-right">₱{{ number_format($payroll->items->sum('sss'), 2) }}</td>
                <td class="text-right">₱{{ number_format($payroll->items->sum('philhealth'), 2) }}</td>
                <td class="text-right">₱{{ number_format($payroll->items->sum('pagibig'), 2) }}</td>
                <td class="text-right">₱{{ number_format($payroll->items->sum('withholding_tax'), 2) }}</td>
                <td class="text-right">₱{{ number_format($payroll->items->sum('loans'), 2) }}</td>
                <td class="text-right">₱{{ number_format($payroll->items->sum('employee_savings') + $payroll->items->sum('cash_advance') + $payroll->items->sum('other_deductions'), 2) }}</td>
                <td class="text-right">₱{{ number_format($payroll->total_net, 2) }}</td>
                <td>-</td>
            </tr>
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-title">Prepared by:</div>
            <div class="signature-line"></div>
            <div class="signature-name">{{ $payroll->creator->name ?? 'System' }}</div>
            <div class="signature-name">Name & Signature</div>
        </div>
        <div class="signature-box">
            <div class="signature-title">Checked & Verified By:</div>
            <div class="signature-line"></div>
            <div class="signature-name">___________________</div>
            <div class="signature-name">Name & Signature</div>
        </div>
        <div class="signature-box">
            <div class="signature-title">Recommended By:</div>
            <div class="signature-line"></div>
            <div class="signature-name">___________________</div>
            <div class="signature-name">Name & Signature</div>
        </div>
        <div class="signature-box">
            <div class="signature-title">Approved By:</div>
            <div class="signature-line"></div>
            <div class="signature-name">___________________</div>
            <div class="signature-name">Proprietor/Manager</div>
        </div>
    </div>

    <div class="footer" style="text-align: center; font-size: 8px; margin-top: 10px;">
        Generated on {{ now()->format('F d, Y h:i A') }}
    </div>
</body>
</html>
