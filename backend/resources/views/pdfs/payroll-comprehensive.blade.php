<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Payroll Report - {{ $payroll->payroll_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            line-height: 1.3;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin: 5px 0;
        }

        .period-info {
            font-size: 10px;
            margin: 3px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th {
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 5px 3px;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
        }

        td {
            border: 1px solid #000;
            padding: 4px 3px;
            font-size: 8px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .total-row {
            background-color: #e8e8e8;
            font-weight: bold;
        }

        .grand-total-row {
            background-color: #d0d0d0;
            font-weight: bold;
            font-size: 9px;
        }

        .signature-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .signature-row {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .signature-box {
            display: table-cell;
            width: 50%;
            padding: 0 15px;
        }

        .signature-label {
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 10px;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 30px;
            padding-top: 3px;
            font-size: 9px;
            max-width: 200px;
        }

        .signature-names {
            margin: 8px 0;
            min-height: 20px;
            font-size: 9px;
        }

        .filter-badge {
            display: inline-block;
            background: #ff6f00;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">{{ $company_name }}</div>
        <div class="report-title">
            PAYROLL REGISTER
            @if($filter_type !== 'All Employees')
            <span class="filter-badge">{{ $filter_type }}</span>
            @endif
        </div>
        <div class="period-info">
            Period: {{ $payroll->period_start->format('F d, Y') }} - {{ $payroll->period_end->format('F d, Y') }}
        </div>
        <div class="period-info">
            Payroll No: {{ $payroll->payroll_number }} | Payment Date: {{ $payroll->payment_date->format('F d, Y') }}
        </div>
        @if(isset($project) && $project)
        <div class="period-info" style="margin-top: 5px;">
            <strong>Project:</strong> {{ $project->name }} @if($project->code)({{ $project->code }})@endif
            @if($project->description)
            <br><strong>Description:</strong> {{ $project->description }}
            @endif
        </div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 3%;">No.</th>
                <th style="width: 7%;">Employee #</th>
                <th style="width: 11%;">Employee Name</th>
                <th style="width: 9%;">Position</th>
                <th style="width: 5%;">Rate</th>
                <th style="width: 3%;">Days</th>
                <th style="width: 5%;">Basic Pay</th>
                <th style="width: 3%;">OT Hrs</th>
                <th style="width: 5%;">OT Pay</th>
                <th style="width: 5%;">Allowances</th>
                <th style="width: 5%;">Gross</th>
                <th style="width: 4%;">SSS</th>
                <th style="width: 4%;">PHIC</th>
                <th style="width: 4%;">HDMF</th>
                <th style="width: 4%;">Tax</th>
                <th style="width: 4%;">Loans</th>
                <th style="width: 4%;">Other</th>
                <th style="width: 5%;">Net Pay</th>
                <th style="width: 10%;">Employee Signature</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $item->employee->employee_number ?? 'N/A' }}</td>
                <td>{{ $item->employee->full_name ?? 'Unknown' }}</td>
                <td>{{ $item->employee->positionRate->position_name ?? 'N/A' }}</td>
                <td class="text-right">PHP {{ number_format($item->basic_rate ?? 0, 2) }}</td>
                <td class="text-center">{{ number_format($item->days_worked ?? 0, 1) }}</td>
                <td class="text-right">PHP {{ number_format($item->basic_pay ?? 0, 2) }}</td>
                <td class="text-center">{{ number_format($item->overtime_hours ?? 0, 1) }}</td>
                <td class="text-right">PHP {{ number_format($item->overtime_pay ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format(($item->water_allowance ?? 0) + ($item->cola ?? 0) + ($item->other_allowances ?? 0), 2) }}</td>
                <td class="text-right">PHP {{ number_format($item->gross_pay ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($item->sss_contribution ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($item->philhealth_contribution ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($item->pagibig_contribution ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($item->withholding_tax ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($item->total_loan_deductions ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($item->total_other_deductions ?? 0, 2) }}</td>
                <td class="text-right"><strong>PHP {{ number_format($item->net_pay ?? 0, 2) }}</strong></td>
                <td style="border: 1px solid #000;"></td>
            </tr>
            @empty
            <tr>
                <td colspan="19" class="text-center" style="padding: 20px; color: #999;">
                    No payroll items found for this period.
                </td>
            </tr>
            @endforelse

            <!-- Totals Row -->
            <tr class="grand-total-row">
                <td colspan="6" class="text-right">TOTAL:</td>
                <td class="text-right">PHP {{ number_format($totals['basic_pay'] ?? 0, 2) }}</td>
                <td></td>
                <td class="text-right">PHP {{ number_format($totals['overtime_pay'] ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['total_allowances'] ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['gross_pay'] ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['sss'] ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['philhealth'] ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['pagibig'] ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['tax'] ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['loans'] ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['other_deductions'] ?? 0, 2) }}</td>
                <td class="text-right">PHP {{ number_format($totals['net_pay'] ?? 0, 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="signature-section">
        <div class="signature-row">
            <div class="signature-box">
                <div class="signature-label">Prepared by:</div>
                <div class="signature-names">
                    @if(isset($signatures['prepared_by']))
                    @foreach($signatures['prepared_by'] as $person)
                    {{ $person }}<br>
                    @endforeach
                    @else
                    ________________
                    @endif
                </div>
                <div class="signature-line">Name & Signature</div>
            </div>

            <div class="signature-box">
                <div class="signature-label">Checked & Verified By:</div>
                <div class="signature-names">
                    @if(isset($signatures['checked_by']))
                    @foreach($signatures['checked_by'] as $person)
                    {{ $person }}<br>
                    @endforeach
                    @else
                    ________________
                    @endif
                </div>
                <div class="signature-line">Name & Signature</div>
            </div>
        </div>

        <div class="signature-row">
            <div class="signature-box">
                <div class="signature-label">Recommended By:</div>
                <div class="signature-names">
                    @if(isset($signatures['recommended_by']))
                    @foreach($signatures['recommended_by'] as $person)
                    {{ $person }}<br>
                    @endforeach
                    @else
                    ________________
                    @endif
                </div>
                <div class="signature-line">Name & Signature</div>
            </div>

            <div class="signature-box">
                <div class="signature-label">Approved By:</div>
                <div class="signature-names">
                    @if(isset($signatures['approved_by']))
                    @foreach($signatures['approved_by'] as $person)
                    {{ $person }}<br>
                    @endforeach
                    @else
                    ________________
                    @endif
                </div>
                <div class="signature-line">Name & Signature</div>
            </div>
        </div>
    </div>
</body>

</html>