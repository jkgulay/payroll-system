<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payroll Register</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 10mm 60mm 10mm;
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
            border: 1px solid #000;
        }

        th,
        td {
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

        /* Fixed footer signature section on every page */
        .page-footer {
            position: fixed;
            bottom: -50mm;
            left: 0mm;
            right: 0mm;
            height: 50mm;
            text-align: center;
            background-color: white;
        }

        .footer-acknowledgment {
            font-size: 8px;
            font-style: italic;
            text-align: center;
            margin-bottom: 8px;
        }

        .footer-signature-section {
            width: 100%;
            margin-top: 5px;
        }

        .footer-signature-table {
            width: 100%;
            border: none;
            border-collapse: collapse;
        }

        .footer-signature-table td {
            border: none;
            text-align: center;
            padding: 2px 5px;
            vertical-align: top;
        }

        .footer-signature-title {
            font-size: 7px;
            margin-bottom: 12px;
        }

        .footer-signature-name {
            font-size: 7px;
            font-weight: bold;
        }

        .footer-signature-position {
            font-size: 6px;
        }

        /* Hide inline signature section since we have it in footer */
        .signature-section {
            display: none;
        }
    </style>
</head>

<body>
    <!-- Fixed footer signature section that appears on every page -->
    <div class="page-footer">
        <div class="footer-acknowledgment">
            "I hereby acknowledge that the computation and total of my salary stated above for the given period is correct."
        </div>

        <table class="footer-signature-table">
            <tr>
                <td style="width: 25%;">
                    <div class="footer-signature-title">PREPARED BY:</div>
                    <div class="footer-signature-name">MERCIEL LAVASAN</div>
                </td>
                <td style="width: 25%;">
                    <div class="footer-signature-title">CHECKED AND VERIFIED BY:</div>
                    <div class="footer-signature-name">SAIRAH JENITA</div>
                </td>
                <td style="width: 25%;">
                    <div class="footer-signature-title">RECOMMENDED BY:</div>
                    <div class="footer-signature-name">ENGR. FRANCIS GIOVANNI C. RIVERA</div>
                </td>
                <td style="width: 25%;">
                    <div class="footer-signature-title">APPROVED BY:</div>
                    <div class="footer-signature-name">ENGR. OSTRIC R. RIVERA JR.</div>
                    <div class="footer-signature-position">Proprietor/Manager</div>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="height: 5px;"></td>
            </tr>
            <tr>
                <td style="width: 25%;"></td>
                <td style="width: 25%;">
                    <div class="footer-signature-name">PAICA CRISTEL MAE SUGABO</div>
                </td>
                <td style="width: 25%;">
                    <div class="footer-signature-name">ENGR. OSTRIC C. RIVERA, III</div>
                </td>
                <td style="width: 25%;">
                    <div class="footer-signature-name">ENG. ELISA MAY PARCON</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="header">
        <div class="company-name">{{ $companyInfo->company_name ?? 'GIOVANNI CONSTRUCTION' }}</div>
        <div class="company-address">{{ $companyInfo->address ?? 'Imadejas Subdivision, Capitol Bonbon' }}</div>
        <div class="title">P A Y R O L L</div>
        <div class="period">
            {{ \Carbon\Carbon::parse($payroll->period_start)->format('F d') }} - {{ \Carbon\Carbon::parse($payroll->period_end)->format('d, Y') }}
        </div>
    </div>


    @if($groupedItems)
    {{-- Multiple groups: separate table for each --}}
    @foreach($groupedItems as $groupName => $items)
    <div class="project-info" @if(!$loop->first) style="margin-top: 30px; page-break-before: always;" @endif>
        <div><strong>{{ $filterType === 'staff_type' ? 'STAFF TYPE' : 'DEPARTMENT' }}:</strong> {{ $groupName }}</div>
        <div><strong>DESIGNATION:</strong> {{ $items->first()?->employee?->project?->description ?? 'N/A' }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">NAME</th>
                <th rowspan="2">RATE</th>
                <th rowspan="2">No. of<br>Days</th>
                <th rowspan="2">AMOUNT</th>
                <th colspan="4">OVERTIME</th>
                <th rowspan="2">UT</th>
                <th rowspan="2">Adj. Prev.<br>Salary</th>
                <th rowspan="2">Allowance</th>
                <th rowspan="2">GROSS<br>AMOUNT</th>
                <th rowspan="2">Employee's<br>Savings</th>
                <th rowspan="2">Loans</th>
                <th rowspan="2">Deductions</th>
                <th colspan="3">PREMIUMS</th>
                <th rowspan="2">NET<br>AMOUNT</th>
                <th rowspan="2">SIGNATURE</th>
            </tr>
            <tr>
                <th>HRS</th>
                <th>REG OT</th>
                <th>HRS</th>
                <th>SUN/SPL. HOL.</th>
                <th>SSS</th>
                <th>PHIC</th>
                <th>HDMF</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $item)
            @php
            // Use the undertime deduction already calculated by PayrollService
            $undertimeDeduction = $item->undertime_deduction ?? 0;
            $amount = ($item->effective_rate ?? 0) * ($item->days_worked ?? 0);
            @endphp
            <tr>
                <td class="text-left">{{ $index + 1 }}. {{ $item->employee->full_name }}</td>
                <td class="text-right">{{ number_format($item->effective_rate, 2) }}</td>
                <td>{{ rtrim(rtrim(number_format($item->days_worked, 2), '0'), '.') }}</td>
                <td class="text-right">{{ number_format($amount, 2) }}</td>
                <td>{{ $item->regular_ot_hours > 0 ? number_format($item->regular_ot_hours, 2) : '' }}</td>
                <td class="text-right">{{ $item->regular_ot_pay > 0 ? number_format($item->regular_ot_pay, 2) : '' }}</td>
                <td>{{ $item->special_ot_hours > 0 ? number_format($item->special_ot_hours, 2) : '' }}</td>
                <td class="text-right">{{ $item->special_ot_pay > 0 ? number_format($item->special_ot_pay, 2) : '' }}</td>
                <td class="text-right">{{ $undertimeDeduction > 0 ? number_format($undertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ $item->salary_adjustment != 0 ? number_format($item->salary_adjustment, 2) : '' }}</td>
                <td class="text-right">{{ $item->other_allowances > 0 ? number_format($item->other_allowances, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->gross_pay, 2) }}</td>
                <td class="text-right">{{ $item->employee_savings > 0 ? number_format($item->employee_savings, 2) : '' }}</td>
                <td class="text-right">{{ $item->loans > 0 ? number_format($item->loans, 2) : '' }}</td>
                <td class="text-right">{{ $item->employee_deductions > 0 ? number_format($item->employee_deductions, 2) : '' }}</td>
                <td class="text-right">{{ $item->sss > 0 ? number_format($item->sss, 2) : '' }}</td>
                <td class="text-right">{{ $item->philhealth > 0 ? number_format($item->philhealth, 2) : '' }}</td>
                <td class="text-right">{{ $item->pagibig > 0 ? number_format($item->pagibig, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
            <tr>
                <td colspan="21" class="nothing-follows"><em>nothing follows</em></td>
            </tr>
            @php
            // Calculate total undertime deduction
            $totalUndertimeDeduction = $items->sum(function($item) {
            return $item->undertime_deduction ?? 0;
            });
            @endphp
            @php
            $totalAmount = $items->sum(function($item) {
            return ($item->effective_rate ?? 0) * ($item->days_worked ?? 0);
            });
            @endphp
            <tr class="total-row">
                <td class="text-left"><strong>T O T A L</strong></td>
                <td></td>
                <td></td>
                <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
                <td>{{ number_format($items->sum('regular_ot_hours'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('regular_ot_pay'), 2) }}</td>
                <td>{{ number_format($items->sum('special_ot_hours'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('special_ot_pay'), 2) }}</td>
                <td class="text-right">{{ $totalUndertimeDeduction > 0 ? number_format($totalUndertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ number_format($items->sum('salary_adjustment'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('other_allowances'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('gross_pay'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('employee_savings'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('loans'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('employee_deductions'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('sss'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('philhealth'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('pagibig'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('net_pay'), 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @endforeach
    @else
    {{-- Single table for all employees --}}
    @if($filterInfo)
    <div class="project-info">
        <div><strong>{{ strtoupper(explode(':', $filterInfo)[0]) }}:</strong> {{ explode(':', $filterInfo)[1] ?? '' }}</div>
        <div><strong>DESIGNATION:</strong> {{ $payroll->items->first()?->employee?->project?->description ?? 'N/A' }}</div>
    </div>
    @else
    <div class="project-info">
        <div><strong>DEPARTMENT:</strong> {{ $payroll->items->first()?->employee->department ?? 'N/A' }}</div>
        <div><strong>DESIGNATION:</strong> {{ $payroll->items->first()?->employee?->project?->description ?? 'N/A' }}</div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th rowspan="2">NAME</th>
                <th rowspan="2">RATE</th>
                <th rowspan="2">No. of<br>Days</th>
                <th rowspan="2">AMOUNT</th>
                <th colspan="4">OVERTIME</th>
                <th rowspan="2">UT</th>
                <th rowspan="2">Adj. Prev.<br>Salary</th>
                <th rowspan="2">Allowance</th>
                <th rowspan="2">GROSS<br>AMOUNT</th>
                <th rowspan="2">Employee's<br>Savings</th>
                <th rowspan="2">Loans</th>
                <th rowspan="2">Deductions</th>
                <th colspan="3">PREMIUMS</th>
                <th rowspan="2">NET<br>AMOUNT</th>
                <th rowspan="2">SIGNATURE</th>
            </tr>
            <tr>
                <th>HRS</th>
                <th>REG OT</th>
                <th>HRS</th>
                <th>SUN/SPL. HOL.</th>
                <th>SSS</th>
                <th>PHIC</th>
                <th>HDMF</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payroll->items as $index => $item)
            @php
            // Use the undertime deduction already calculated by PayrollService
            $undertimeDeduction = $item->undertime_deduction ?? 0;
            $amount = ($item->effective_rate ?? 0) * ($item->days_worked ?? 0);
            @endphp
            <tr>
                <td class="text-left">{{ $index + 1 }}. {{ $item->employee->full_name }}</td>
                <td class="text-right">{{ number_format($item->effective_rate, 2) }}</td>
                <td>{{ rtrim(rtrim(number_format($item->days_worked, 2), '0'), '.') }}</td>
                <td class="text-right">{{ number_format($amount, 2) }}</td>
                <td>{{ $item->regular_ot_hours > 0 ? number_format($item->regular_ot_hours, 2) : '' }}</td>
                <td class="text-right">{{ $item->regular_ot_pay > 0 ? number_format($item->regular_ot_pay, 2) : '' }}</td>
                <td>{{ $item->special_ot_hours > 0 ? number_format($item->special_ot_hours, 2) : '' }}</td>
                <td class="text-right">{{ $item->special_ot_pay > 0 ? number_format($item->special_ot_pay, 2) : '' }}</td>
                <td class="text-right">{{ $undertimeDeduction > 0 ? number_format($undertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ $item->salary_adjustment != 0 ? number_format($item->salary_adjustment, 2) : '' }}</td>
                <td class="text-right">{{ $item->other_allowances > 0 ? number_format($item->other_allowances, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->gross_pay, 2) }}</td>
                <td class="text-right">{{ $item->employee_savings > 0 ? number_format($item->employee_savings, 2) : '' }}</td>
                <td class="text-right">{{ $item->loans > 0 ? number_format($item->loans, 2) : '' }}</td>
                <td class="text-right">{{ $item->employee_deductions > 0 ? number_format($item->employee_deductions, 2) : '' }}</td>
                <td class="text-right">{{ $item->sss > 0 ? number_format($item->sss, 2) : '' }}</td>
                <td class="text-right">{{ $item->philhealth > 0 ? number_format($item->philhealth, 2) : '' }}</td>
                <td class="text-right">{{ $item->pagibig > 0 ? number_format($item->pagibig, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
            <tr>
                <td colspan="21" class="nothing-follows"><em>nothing follows</em></td>
            </tr>
            @php
            // Calculate total undertime deduction
            $totalUndertimeDeduction = $payroll->items->sum(function($item) {
            return $item->undertime_deduction ?? 0;
            });
            @endphp
            @php
            $totalAmount = $payroll->items->sum(function($item) {
            return ($item->effective_rate ?? 0) * ($item->days_worked ?? 0);
            });
            @endphp
            <tr class="total-row">
                <td class="text-left"><strong>T O T A L</strong></td>
                <td></td>
                <td></td>
                <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
                <td>{{ number_format($payroll->items->sum('regular_ot_hours'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('regular_ot_pay'), 2) }}</td>
                <td>{{ number_format($payroll->items->sum('special_ot_hours'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('special_ot_pay'), 2) }}</td>
                <td class="text-right">{{ $totalUndertimeDeduction > 0 ? number_format($totalUndertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('salary_adjustment'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('other_allowances'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->total_gross, 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('employee_savings'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('loans'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('employee_deductions'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('sss'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('philhealth'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('pagibig'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->total_net, 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @endif

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

    <div class="signature-section" style="margin-top: 40px;">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name">PAICA CRISTEL MAE SUGABO</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name">ENGR. OSTRIC C. RIVERA, III</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name">ENG. ELISA MAY PARCON</div>
        </div>
    </div>
</body>

</html>