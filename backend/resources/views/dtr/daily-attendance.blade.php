<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Daily Attendance Record</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 30px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .document-title {
            font-size: 16px;
            font-weight: bold;
            margin: 15px 0;
        }

        .date-display {
            font-size: 14px;
            margin: 10px 0;
        }

        .employee-info {
            margin: 30px 0;
            border: 2px solid #000;
            padding: 15px;
            background-color: #f9f9f9;
        }

        .info-row {
            margin: 8px 0;
            font-size: 13px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 140px;
        }

        .attendance-details {
            margin: 30px 0;
            border: 2px solid #000;
            padding: 15px;
        }

        .time-table {
            width: 100%;
            margin: 20px 0;
        }

        .time-row {
            margin: 15px 0;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .time-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }

        .time-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }

        .summary-box {
            margin: 20px 0;
            padding: 15px;
            background-color: #e8f4f8;
            border: 1px solid #4a90e2;
        }

        .summary-item {
            margin: 8px 0;
            font-size: 13px;
        }

        .summary-label {
            font-weight: bold;
            display: inline-block;
            width: 180px;
        }

        .signature-section {
            margin-top: 60px;
            page-break-inside: avoid;
        }

        .signature-box {
            margin: 40px 0;
        }

        .signature-line {
            border-top: 2px solid #000;
            margin-top: 60px;
            padding-top: 8px;
            text-align: center;
            font-weight: bold;
        }

        .note {
            margin: 20px 0;
            padding: 10px;
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            font-size: 11px;
        }

        .footer {
            margin-top: 40px;
            font-size: 10px;
            text-align: center;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">{{ $company_name }}</div>
        <div class="document-title">DAILY ATTENDANCE RECORD</div>
        <div class="date-display">{{ $date->format('l, F d, Y') }}</div>
    </div>

    <div class="employee-info">
        <div class="info-row">
            <span class="info-label">Employee Number:</span>
            <span>{{ $employee->employee_number }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Employee Name:</span>
            <span>{{ $employee->full_name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Position:</span>
            <span>{{ $employee->position ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Project/Department:</span>
            <span>{{ $employee->project->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Date:</span>
            <span>{{ $attendance->attendance_date->format('F d, Y (l)') }}</span>
        </div>
    </div>

    <div class="attendance-details">
        <h3 style="margin-top: 0;">Time Records</h3>

        <div class="time-table">
            <div class="time-row">
                <span class="time-label">Morning Time In:</span>
                <span class="time-value">
                    {{ $attendance->time_in ? \Carbon\Carbon::parse($attendance->time_in)->format('h:i A') : 'Not recorded' }}
                </span>
            </div>

            <div class="time-row">
                <span class="time-label">Lunch Break Start:</span>
                <span class="time-value">
                    {{ $attendance->break_start ? \Carbon\Carbon::parse($attendance->break_start)->format('h:i A') : 'Not recorded' }}
                </span>
            </div>

            <div class="time-row">
                <span class="time-label">Lunch Break End:</span>
                <span class="time-value">
                    {{ $attendance->break_end ? \Carbon\Carbon::parse($attendance->break_end)->format('h:i A') : 'Not recorded' }}
                </span>
            </div>

            <div class="time-row">
                <span class="time-label">Afternoon Time Out:</span>
                <span class="time-value">
                    {{ $attendance->time_out ? \Carbon\Carbon::parse($attendance->time_out)->format('h:i A') : 'Not recorded' }}
                </span>
            </div>
        </div>

        <div class="summary-box">
            <h4 style="margin-top: 0;">Hours Summary</h4>
            <div class="summary-item">
                <span class="summary-label">Regular Hours:</span>
                <span>{{ number_format($attendance->regular_hours ?? 0, 2) }} hours</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Overtime Hours:</span>
                <span>{{ number_format($attendance->overtime_hours ?? 0, 2) }} hours</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Late (Hours):</span>
                <span>{{ number_format($attendance->late_hours ?? 0, 2) }} hours</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Undertime (Hours):</span>
                <span>{{ number_format($attendance->undertime_hours ?? 0, 2) }} hours</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Night Differential:</span>
                <span>{{ number_format($attendance->night_differential_hours ?? 0, 2) }} hours</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Status:</span>
                <span style="text-transform: capitalize; font-weight: bold;">{{ $attendance->status }}</span>
            </div>
        </div>
    </div>

    @if($attendance->manual_reason || $attendance->notes)
    <div class="note">
        <strong>Note/Remarks:</strong><br>
        {{ $attendance->manual_reason ?? $attendance->notes ?? 'No remarks' }}
    </div>
    @endif

    <div class="signature-section">
        <div class="signature-box">
            <div>I hereby certify that the above time record is true and correct.</div>
            <div class="signature-line">
                Employee Signature over Printed Name
            </div>
            <div style="text-align: center; margin-top: 10px; font-size: 14px;">
                {{ $employee->full_name }}
            </div>
            <div style="text-align: center; margin-top: 15px;">
                Date: __________________
            </div>
        </div>

        <div class="signature-box">
            <div>Verified and approved by:</div>
            <div class="signature-line">
                Supervisor/Manager Signature over Printed Name
            </div>
            <div style="text-align: center; margin-top: 15px;">
                Date: __________________
            </div>
        </div>
    </div>

    <div class="footer">
        Generated on {{ $generated_at->format('F d, Y h:i A') }}<br>
        This is a computer-generated document. Signature is required for validation.<br>
        {{ $company_name }} - Daily Attendance Record
    </div>
</body>

</html>