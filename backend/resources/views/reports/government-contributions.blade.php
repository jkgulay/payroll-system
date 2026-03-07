<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Government Contributions Report - {{ $period }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
            color: #333;
        }

        .company-address {
            font-size: 11px;
            margin: 3px 0;
            color: #666;
        }

        .report-title {
            font-size: 16px;
            font-weight: bold;
            margin: 15px 0 5px 0;
            color: #1a73e8;
        }

        .report-period {
            font-size: 12px;
            margin: 5px 0;
            color: #555;
        }

        .info-section {
            margin: 20px 0;
            font-size: 11px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 9px;
        }

        table th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            padding: 8px 5px;
            text-align: center;
            border: 1px solid #2C5899;
        }

        table td {
            border: 1px solid #ddd;
            padding: 6px 5px;
            text-align: left;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .total-row {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 10px;
        }

        .contributions-label {
            font-size: 12px;
            font-weight: bold;
            margin: 15px 0 10px 0;
            color: #333;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }

        .footer {
            margin-top: 30px;
            font-size: 9px;
            color: #666;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
            font-size: 10px;
        }

        .amount {
            font-family: 'Courier New', monospace;
        }

        @page {
            margin: 15mm;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="header">
        @if($companyInfo)
        <h1 class="company-name">{{ $companyInfo->company_name ?? 'Company Name' }}</h1>
        <p class="company-address">{{ $companyInfo->address ?? '' }}</p>
        @if($companyInfo->phone)
        <p class="company-address">Tel: {{ $companyInfo->phone }}</p>
        @endif
        @endif
        <h2 class="report-title">Government Contributions Report</h2>
        <p class="report-period">Period: {{ $period }}</p>
        @if($department)
        <p class="report-period">Department: {{ $department }}</p>
        @endif
    </div>

    <!-- Summary Information -->
    <div class="info-section">
        <div class="info-row">
            <span><strong>Total Employees:</strong> {{ $employeeCount }}</span>
            <span><strong>Generated on:</strong> {{ now()->format('F d, Y h:i A') }}</span>
        </div>
        <div class="info-row">
            <span>
                <strong>Contribution Types:</strong>
                @php
                    $typeLabels = [];
                    foreach($contributionTypes as $type) {
                        if($type === 'sss') $typeLabels[] = 'SSS';
                        elseif($type === 'philhealth') $typeLabels[] = 'PhilHealth';
                        elseif($type === 'pagibig') $typeLabels[] = 'Pag-IBIG';
                    }
                    echo implode(', ', $typeLabels);
                @endphp
            </span>
        </div>
    </div>

    <!-- Employee Contributions Table -->
    @if(in_array('sss', $contributionTypes))
    <div class="contributions-label">SSS Contributions</div>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">No.</th>
                <th style="width: 15%;">Employee No.</th>
                <th style="width: 27%;">Employee Name</th>
                <th style="width: 20%;">Position</th>
                <th style="width: 15%;">Employee Share</th>
                <th style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($employees as $index => $employee)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $employee['employee_number'] }}</td>
                <td>{{ $employee['full_name'] }}</td>
                <td>{{ $employee['position'] }}</td>
                <td class="text-right amount">PHP {{ number_format($employee['sss'], 2) }}</td>
                <td class="text-right amount">PHP {{ number_format($employee['sss_total'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4" class="text-right">TOTAL SSS CONTRIBUTIONS:</td>
                <td class="text-right amount">PHP {{ number_format($totals['sss_employee'], 2) }}</td>
                <td class="text-right amount">PHP {{ number_format($totals['sss_total'], 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    @if(in_array('philhealth', $contributionTypes))
    <div class="contributions-label">PhilHealth Contributions</div>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">No.</th>
                <th style="width: 15%;">Employee No.</th>
                <th style="width: 27%;">Employee Name</th>
                <th style="width: 20%;">Position</th>
                <th style="width: 15%;">Employee Share</th>
                <th style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($employees as $index => $employee)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $employee['employee_number'] }}</td>
                <td>{{ $employee['full_name'] }}</td>
                <td>{{ $employee['position'] }}</td>
                <td class="text-right amount">PHP {{ number_format($employee['philhealth'], 2) }}</td>
                <td class="text-right amount">PHP {{ number_format($employee['philhealth_total'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4" class="text-right">TOTAL PHILHEALTH CONTRIBUTIONS:</td>
                <td class="text-right amount">PHP {{ number_format($totals['philhealth_employee'], 2) }}</td>
                <td class="text-right amount">PHP {{ number_format($totals['philhealth_total'], 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    @if(in_array('pagibig', $contributionTypes))
    <div class="contributions-label">Pag-IBIG Contributions</div>
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">No.</th>
                <th style="width: 15%;">Employee No.</th>
                <th style="width: 27%;">Employee Name</th>
                <th style="width: 20%;">Position</th>
                <th style="width: 15%;">Employee Share</th>
                <th style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($employees as $index => $employee)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $employee['employee_number'] }}</td>
                <td>{{ $employee['full_name'] }}</td>
                <td>{{ $employee['position'] }}</td>
                <td class="text-right amount">PHP {{ number_format($employee['pagibig'], 2) }}</td>
                <td class="text-right amount">PHP {{ number_format($employee['pagibig_total'], 2) }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4" class="text-right">TOTAL PAG-IBIG CONTRIBUTIONS:</td>
                <td class="text-right amount">PHP {{ number_format($totals['pagibig_employee'], 2) }}</td>
                <td class="text-right amount">PHP {{ number_format($totals['pagibig_total'], 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <!-- Summary Section -->
    <div style="margin-top: 30px; padding: 15px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">
        <h3 style="margin: 0 0 10px 0; font-size: 12px; color: #333;">Contribution Summary</h3>
        <table style="margin: 0; border: none;">
            <tr style="border: none;">
                <td style="border: none; font-weight: bold; width: 70%;">Total Employee Contributions:</td>
                <td style="border: none; text-align: right; font-weight: bold;" class="amount">
                    PHP {{ number_format(
                        (in_array('sss', $contributionTypes) ? $totals['sss_employee'] : 0) +
                        (in_array('philhealth', $contributionTypes) ? $totals['philhealth_employee'] : 0) +
                        (in_array('pagibig', $contributionTypes) ? $totals['pagibig_employee'] : 0),
                        2
                    ) }}
                </td>
            </tr>
            <tr style="border: none;">
                <td style="border: none; font-weight: bold;">Total Employer Contributions:</td>
                <td style="border: none; text-align: right; font-weight: bold;" class="amount">
                    PHP {{ number_format(
                        (in_array('sss', $contributionTypes) ? $totals['sss_employer'] : 0) +
                        (in_array('philhealth', $contributionTypes) ? $totals['philhealth_employer'] : 0) +
                        (in_array('pagibig', $contributionTypes) ? $totals['pagibig_employer'] : 0),
                        2
                    ) }}
                </td>
            </tr>
            <tr style="border: none; background-color: #e3f2fd;">
                <td style="border: none; font-weight: bold; font-size: 11px; padding: 10px 5px;">GRAND TOTAL:</td>
                <td style="border: none; text-align: right; font-weight: bold; font-size: 11px; padding: 10px 5px;" class="amount">
                    PHP {{ number_format(
                        (in_array('sss', $contributionTypes) ? $totals['sss_total'] : 0) +
                        (in_array('philhealth', $contributionTypes) ? $totals['philhealth_total'] : 0) +
                        (in_array('pagibig', $contributionTypes) ? $totals['pagibig_total'] : 0),
                        2
                    ) }}
                </td>
            </tr>
        </table>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                Prepared By
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                Approved By
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This is a system-generated report. For questions or concerns, please contact the HR Department.</p>
        <p>{{ $companyInfo->company_name ?? 'Company Name' }} &copy; {{ date('Y') }}</p>
    </div>
</body>

</html>
