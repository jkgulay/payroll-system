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
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .company-address {
            font-size: 10px;
            margin-top: 3px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 8px;
            margin-top: 15px;
        }
        .period {
            font-size: 11px;
            margin-top: 5px;
        }
        .project-info {
            margin: 15px 0;
            font-size: 10px;
        }
        .project-info div {
            margin: 3px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px 3px;
            font-size: 8px;
        }
        th {
            background-color: white;
            color: black;
            text-align: center;
            font-weight: bold;
        }
        td {
            text-align: center;
        }
        .text-left {
            text-align: left;
            padding-left: 5px;
        }
        .text-right {
            text-align: right;
            padding-right: 5px;
        }
        .nothing-follows {
            text-align: center;
            font-style: italic;
            font-size: 9px;
            padding: 5px;
        }
        .total-row {
            font-weight: bold;
            font-size: 9px;
        }
        .acknowledgment {
            margin-top: 10px;
            font-size: 8px;
            font-style: italic;
            text-align: center;
        }
        .signature-section {
            display: table;
            width: 100%;
            margin-top: 40px;
        }
        .signature-box {
            display: table-cell;
            width: 25%;
            text-align: center;
            padding: 0 5px;
        }
        .signature-title {
            font-size: 8px;
            margin-bottom: 5px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin: 30px 10px 5px 10px;
        }
        .signature-name {
            font-size: 8px;
            font-weight: bold;
        }
        .signature-position {
            font-size: 7px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">GIOVANNI CONSTRUCTION</div>
        <div class="company-address">Imadejas Subdivision, Capitol Bonbon</div>
        <div class="title">P A Y R O L L</div>
        <div class="period">
            {{ \Carbon\Carbon::parse($payroll->period_start)->format('F d') }} - {{ \Carbon\Carbon::parse($payroll->period_end)->format('d, Y') }}
        </div>
    </div>

    <div class="project-info">
        <div><strong>PROJECT LOCATION:</strong> {{ $payroll->items->first()?->employee->project->name ?? 'N/A' }}</div>
        <div style="margin-left: 120px;"><strong>:</strong> {{ $payroll->items->first()?->employee->project->location ?? 'N/A' }}</div>
        <div><strong>DESIGNATION:</strong> {{ $payroll->items->first()?->employee->position->position_name ?? 'N/A' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">NAME</th>
                <th rowspan="2">RATE</th>
                <th rowspan="2">No. of<br>Days</th>
                <th rowspan="2">AMOUNT</th>
                <th colspan="4">OVERTIME</th>
                <th rowspan="2">COLA</th>
                <th rowspan="2">GROSS<br>AMOUNT</th>
                <th rowspan="2">Employee's<br>Savings</th>
                <th rowspan="2">Flashlight</th>
                <th rowspan="2">Cash<br>Advance</th>
                <th rowspan="2">Phic<br>Prem</th>
                <th rowspan="2">HDMF<br>Prem</th>
                <th rowspan="2">SSS<br>Prem</th>
                <th rowspan="2">NET<br>AMOUNT</th>
                <th rowspan="2">SIGNATURE</th>
            </tr>
            <tr>
                <th>HRS</th>
                <th>REG OT</th>
                <th>HRS</th>
                <th>SPE OT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payroll->items as $index => $item)
            <tr>
                <td class="text-left">{{ $index + 1 }}. {{ $item->employee->full_name }}</td>
                <td class="text-right">{{ number_format($item->effective_rate, 2) }}</td>
                <td>{{ $item->days_worked }}</td>
                <td class="text-right">{{ number_format($item->basic_pay, 2) }}</td>
                <td>{{ $item->regular_ot_hours > 0 ? $item->regular_ot_hours : '' }}</td>
                <td class="text-right">{{ $item->regular_ot_pay > 0 ? number_format($item->regular_ot_pay, 2) : '' }}</td>
                <td>{{ $item->special_ot_hours > 0 ? $item->special_ot_hours : '' }}</td>
                <td class="text-right">{{ $item->special_ot_pay > 0 ? number_format($item->special_ot_pay, 2) : '' }}</td>
                <td class="text-right">{{ $item->cola > 0 ? number_format($item->cola, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->gross_pay, 2) }}</td>
                <td class="text-right">{{ $item->employee_savings > 0 ? number_format($item->employee_savings, 2) : '' }}</td>
                <td class="text-right">{{ $item->other_deductions > 0 ? number_format($item->other_deductions, 2) : '' }}</td>
                <td class="text-right">{{ $item->cash_advance > 0 ? number_format($item->cash_advance, 2) : '' }}</td>
                <td class="text-right">{{ $item->philhealth_contribution > 0 ? number_format($item->philhealth_contribution, 2) : '' }}</td>
                <td class="text-right">{{ $item->pagibig_contribution > 0 ? number_format($item->pagibig_contribution, 2) : '' }}</td>
                <td class="text-right">{{ $item->sss_contribution > 0 ? number_format($item->sss_contribution, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
            <tr>
                <td colspan="18" class="nothing-follows"><em>nothing follows</em></td>
            </tr>
            <tr class="total-row">
                <td class="text-left"><strong>T O T A L</strong></td>
                <td></td>
                <td></td>
                <td class="text-right">{{ number_format($payroll->items->sum('basic_pay'), 2) }}</td>
                <td>{{ $payroll->items->sum('regular_ot_hours') }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('regular_ot_pay'), 2) }}</td>
                <td>{{ $payroll->items->sum('special_ot_hours') }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('special_ot_pay'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('cola'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->total_gross, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('employee_savings'), 2) }}</td>
                <td>-</td>
                <td>-</td>
                <td class="text-right">{{ number_format($payroll->items->sum('philhealth_contribution'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('pagibig_contribution'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('sss_contribution'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->total_net, 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="acknowledgment">
        "I hereby acknowledge that the computation and total of my salary stated above for the given period is correct."
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-title">PREPARED BY:</div>
            <div class="signature-line"></div>
            <div class="signature-name">MERCIEL LAVASAN</div>
        </div>
        <div class="signature-box">
            <div class="signature-title">CHECKED AND VERIFIED BY:</div>
            <div class="signature-line"></div>
            <div class="signature-name">SAIRAH JENITA</div>
        </div>
        <div class="signature-box">
            <div class="signature-title">RECOMMENDED BY:</div>
            <div class="signature-line"></div>
            <div class="signature-name">ENGR. FRANCIS GIOVANNI C. RIVERA</div>
        </div>
        <div class="signature-box">
            <div class="signature-title">APPROVED BY:</div>
            <div class="signature-line"></div>
            <div class="signature-name">ENGR. OSTRIC R. RIVERA JR.</div>
            <div class="signature-position">Proprietor/Manager</div>
        </div>
    </div>

    <div style="margin-top: 20px;">
        <div class="signature-box" style="display: inline-block; width: 48%;">
            <div class="signature-title">PAICA CRISTEL MAE SUGABO</div>
        </div>
        <div class="signature-box" style="display: inline-block; width: 48%;">
            <div class="signature-title">ENG. ELISA MAY PARCON</div>
        </div>
    </div>
    <div style="margin-top: 5px;">
        <div class="signature-box" style="display: inline-block; width: 100%; text-align: center;">
            <div class="signature-title">ENGR. OSTRIC C. RIVERA, III</div>
        </div>
    </div>
</body>
</html>
