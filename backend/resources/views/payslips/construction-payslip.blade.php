<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $payslip->payroll->payroll_number }}</title>
    <style>
        @page {
            margin: 15mm;
            size: letter;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .company-info {
            font-size: 10px;
            color: #666;
        }

        .payslip-title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }

        .employee-info {
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

        .info-value {
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th {
            background-color: #f0f0f0;
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
            font-weight: bold;
        }

        td {
            padding: 6px 8px;
            border: 1px solid #ddd;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 8px;
            background-color: #e0e0e0;
            padding: 5px 8px;
            border-left: 4px solid #333;
        }

        .amount-column {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background-color: #f5f5f5;
        }

        .net-pay {
            font-size: 14px;
            font-weight: bold;
            text-align: right;
            margin-top: 10px;
            padding: 10px;
            background-color: #e8f5e9;
            border: 2px solid #4caf50;
        }

        .signatures {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-box {
            display: inline-block;
            width: 23%;
            text-align: center;
            margin-right: 2%;
            vertical-align: top;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
            font-size: 10px;
        }

        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #666;
            text-align: center;
        }

        .no-data {
            text-align: center;
            color: #999;
            font-style: italic;
        }
    </style>
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <div class="company-name">{{ config('app.company_name', 'CONSTRUCTION COMPANY') }}</div>
        <div class="company-info">
            {{ config('app.company_address', 'Company Address Here') }}<br>
            {{ config('app.company_contact', 'Contact: (123) 456-7890') }}
        </div>
        <div class="payslip-title">PAYSLIP</div>
    </div>

    {{-- Employee Information --}}
    <div class="employee-info">
        <div class="info-row">
            <div class="info-label">Employee No.:</div>
            <div class="info-value">{{ $employee->employee_number }}</div>
            <div class="info-label">Pay Period:</div>
            <div class="info-value">{{ $payslip->payroll->period_start_date }} to {{ $payslip->payroll->period_end_date }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Employee Name:</div>
            <div class="info-value">{{ $employee->full_name }}</div>
            <div class="info-label">Payroll No.:</div>
            <div class="info-value">{{ $payslip->payroll->payroll_number }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Position:</div>
            <div class="info-value">{{ $employee->position->position_name ?? ($employee->staff_type ?? 'N/A') }}</div>
            <div class="info-label">Payment Date:</div>
            <div class="info-value">{{ $payslip->payroll->paid_at ? date('M d, Y', strtotime($payslip->payroll->paid_at)) : 'Pending' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Department:</div>
            <div class="info-value">{{ $employee->department->name ?? 'N/A' }}</div>
            <div class="info-label">Job Site:</div>
            <div class="info-value">{{ $employee->location->name ?? 'N/A' }}</div>
        </div>
    </div>

    {{-- Earnings Section --}}
    <div class="section-title">EARNINGS</div>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="width: 80px;" class="amount-column">Days/Hrs</th>
                <th style="width: 100px;" class="amount-column">Rate</th>
                <th style="width: 120px;" class="amount-column">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php
            $earnings = $payslip->items->where('item_type', 'earning');
            $totalEarnings = 0;
            @endphp

            @forelse($earnings as $item)
            @php
            $totalEarnings += $item->amount;
            $quantity = $item->quantity ?? 0;
            $rate = $item->rate ?? 0;
            @endphp
            <tr>
                <td>{{ $item->description }}</td>
                <td class="amount-column">{{ number_format($quantity, 2) }}</td>
                <td class="amount-column">{{ number_format($rate, 2) }}</td>
                <td class="amount-column">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="no-data">No earnings recorded</td>
            </tr>
            @endforelse

            <tr class="total-row">
                <td colspan="3">TOTAL EARNINGS</td>
                <td class="amount-column">₱ {{ number_format($totalEarnings, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Deductions Section --}}
    <div class="section-title">DEDUCTIONS</div>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="width: 120px;" class="amount-column">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php
            $deductions = $payslip->items->where('item_type', 'deduction');
            $totalDeductions = 0;
            @endphp

            @forelse($deductions as $item)
            @php
            $totalDeductions += $item->amount;
            @endphp
            <tr>
                <td>{{ $item->description }}</td>
                <td class="amount-column">{{ number_format($item->amount, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="2" class="no-data">No deductions recorded</td>
            </tr>
            @endforelse

            <tr class="total-row">
                <td>TOTAL DEDUCTIONS</td>
                <td class="amount-column">₱ {{ number_format($totalDeductions, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Government Contributions Section --}}
    <div class="section-title">GOVERNMENT CONTRIBUTIONS</div>
    <table>
        <tbody>
            <tr>
                <td>SSS Contribution</td>
                <td class="amount-column" style="width: 120px;">₱ {{ number_format($payslip->sss_employee ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>PhilHealth Contribution</td>
                <td class="amount-column">₱ {{ number_format($payslip->philhealth_employee ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Pag-IBIG Contribution</td>
                <td class="amount-column">₱ {{ number_format($payslip->pagibig_employee ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Withholding Tax</td>
                <td class="amount-column">₱ {{ number_format($payslip->withholding_tax ?? 0, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>TOTAL GOVERNMENT CONTRIBUTIONS</td>
                <td class="amount-column">₱ {{ number_format(
                    ($payslip->sss_employee ?? 0) + 
                    ($payslip->philhealth_employee ?? 0) + 
                    ($payslip->pagibig_employee ?? 0) + 
                    ($payslip->withholding_tax ?? 0), 2) 
                }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Net Pay --}}
    <div class="net-pay">
        NET PAY: ₱ {{ number_format($payslip->net_pay, 2) }}
    </div>

    {{-- Signatures --}}
    <div class="signatures">
        <div class="signature-box">
            <div class="signature-line">
                PREPARED BY<br>
                {{ $payslip->payroll->prepared_by_user->name ?? '' }}
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                CHECKED BY<br>
                {{ $payslip->payroll->checked_by_user->name ?? '' }}
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                RECOMMENDED BY<br>
                {{ $payslip->payroll->recommended_by_user->name ?? '' }}
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                APPROVED BY<br>
                {{ $payslip->payroll->approved_by_user->name ?? '' }}
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center;">
        <div class="signature-box" style="width: 40%;">
            <div class="signature-line">
                EMPLOYEE SIGNATURE<br>
                {{ $employee->full_name }}
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        This is a computer-generated payslip. No signature required.<br>
        Generated on {{ date('M d, Y h:i A') }}
    </div>
</body>

</html>