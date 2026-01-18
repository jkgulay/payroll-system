<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Daily Time Record</title>
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

        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .document-title {
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
        }

        .employee-info {
            margin: 20px 0;
            border: 1px solid #000;
            padding: 10px;
        }

        .info-row {
            margin: 5px 0;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        .time-cell {
            text-align: center;
            font-size: 10px;
        }

        .totals {
            margin: 20px 0;
            border: 1px solid #000;
            padding: 10px;
        }

        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-box {
            display: inline-block;
            width: 45%;
            margin: 10px 2%;
            vertical-align: top;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
            text-align: center;
        }

        .footer {
            margin-top: 30px;
            font-size: 9px;
            text-align: center;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">{{ $company_name }}</div>
        <div class="document-title">DAILY TIME RECORD (DTR)</div>
        <div>Period: {{ $date_from->format('F d, Y') }} - {{ $date_to->format('F d, Y') }}</div>
    </div>

    <div class="employee-info">
        <div class="info-row">
            <span class="info-label">Employee No:</span>
            <span>{{ $employee->employee_number }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Name:</span>
            <span>{{ $employee->full_name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Position:</span>
            <span>{{ $employee->position->position_name ?? ($employee->staff_type ?? 'N/A') }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Department:</span>
            <span>{{ $employee->department ?? 'N/A' }}</span>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">Date</th>
                <th colspan="2">Morning</th>
                <th colspan="2">Afternoon</th>
                <th rowspan="2">Regular<br>Hours</th>
                <th rowspan="2">OT<br>Hours</th>
                <th rowspan="2">Late<br>(hrs)</th>
                <th rowspan="2">Undertime<br>(hrs)</th>
                <th rowspan="2">Status</th>
            </tr>
            <tr>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Time In</th>
                <th>Time Out</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendance as $record)
            <tr>
                <td>{{ $record->attendance_date->format('M d, D') }}</td>
                <td class="time-cell">{{ $record->time_in ? \Carbon\Carbon::parse($record->time_in)->format('h:i A') : '-' }}</td>
                <td class="time-cell">{{ $record->break_start ? \Carbon\Carbon::parse($record->break_start)->format('h:i A') : '-' }}</td>
                <td class="time-cell">{{ $record->break_end ? \Carbon\Carbon::parse($record->break_end)->format('h:i A') : '-' }}</td>
                <td class="time-cell">{{ $record->time_out ? \Carbon\Carbon::parse($record->time_out)->format('h:i A') : '-' }}</td>
                <td>{{ number_format($record->regular_hours ?? 0, 2) }}</td>
                <td>{{ number_format($record->overtime_hours ?? 0, 2) }}</td>
                <td>{{ number_format($record->late_hours ?? 0, 2) }}</td>
                <td>{{ number_format($record->undertime_hours ?? 0, 2) }}</td>
                <td style="text-transform: capitalize;">{{ $record->status }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #f0f0f0;">
                <td colspan="5" style="text-align: right;">TOTALS:</td>
                <td>{{ number_format($totals['regular_hours'], 2) }}</td>
                <td>{{ number_format($totals['overtime_hours'], 2) }}</td>
                <td>{{ number_format($totals['late_hours'], 2) }}</td>
                <td>{{ number_format($totals['undertime_hours'], 2) }}</td>
                <td>{{ $totals['days_present'] }} days</td>
            </tr>
        </tfoot>
    </table>

    <div class="totals">
        <strong>Summary:</strong><br>
        Total Days Present: {{ $totals['days_present'] }} days<br>
        Total Regular Hours: {{ number_format($totals['regular_hours'], 2) }} hours<br>
        Total Overtime Hours: {{ number_format($totals['overtime_hours'], 2) }} hours<br>
        Total Late: {{ number_format($totals['late_hours'], 2) }} hours<br>
        Total Undertime: {{ number_format($totals['undertime_hours'], 2) }} hours
    </div>

    <div class="totals">
        <strong>Earnings & Deductions Summary:</strong><br>
        <table style="margin-top: 10px;">
            <tr>
                <th colspan="2" style="background-color: #e8f5e9;">EARNINGS</th>
                <th colspan="2" style="background-color: #ffebee;">DEDUCTIONS</th>
            </tr>
            <tr>
                <td style="text-align: left; padding: 5px;">Daily Rate:</td>
                <td style="text-align: right; padding: 5px;">PHP {{ number_format($earnings['rate'], 2) }}</td>
                <td style="text-align: left; padding: 5px;">SSS Contribution:</td>
                <td style="text-align: right; padding: 5px;">PHP {{ number_format($deductions['sss'], 2) }}</td>
            </tr>
            <tr>
                <td style="text-align: left; padding: 5px;">Basic Pay:</td>
                <td style="text-align: right; padding: 5px;">PHP {{ number_format($earnings['basic_pay'], 2) }}</td>
                <td style="text-align: left; padding: 5px;">PhilHealth Contribution:</td>
                <td style="text-align: right; padding: 5px;">PHP {{ number_format($deductions['philhealth'], 2) }}</td>
            </tr>
            <tr>
                <td style="text-align: left; padding: 5px;">Overtime Pay:</td>
                <td style="text-align: right; padding: 5px;">PHP {{ number_format($earnings['overtime_pay'], 2) }}</td>
                <td style="text-align: left; padding: 5px;">Pag-IBIG Contribution:</td>
                <td style="text-align: right; padding: 5px;">PHP {{ number_format($deductions['pagibig'], 2) }}</td>
            </tr>
            <tr style="font-weight: bold; background-color: #f0f0f0;">
                <td style="text-align: left; padding: 5px;">GROSS PAY:</td>
                <td style="text-align: right; padding: 5px;">PHP {{ number_format($earnings['gross_pay'], 2) }}</td>
                <td style="text-align: left; padding: 5px;">TOTAL DEDUCTIONS:</td>
                <td style="text-align: right; padding: 5px;">PHP {{ number_format($deductions['total'], 2) }}</td>
            </tr>
            <tr style="font-weight: bold; background-color: #4CAF50; color: white;">
                <td colspan="3" style="text-align: left; padding: 8px; font-size: 13px;">NET PAY:</td>
                <td style="text-align: right; padding: 8px; font-size: 13px;">PHP {{ number_format($net_pay, 2) }}</td>
            </tr>
        </table>
        <div style="margin-top: 10px; font-size: 9px; font-style: italic;">
            * Government contributions are calculated based on gross pay for this period
        </div>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                Employee Signature
            </div>
            <div style="text-align: center; margin-top: 5px;">
                {{ $employee->full_name }}
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                Supervisor/Manager Signature
            </div>
            <div style="text-align: center; margin-top: 5px;">
                Date: __________________
            </div>
        </div>
    </div>

    <div class="footer">
        Generated on {{ $generated_at->format('F d, Y h:i A') }}<br>
        This is a computer-generated document. Signature is required for validation.
    </div>
</body>

</html>