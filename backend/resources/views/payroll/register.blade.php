<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payroll Register</title>
    <style>
        @page {
            size: 13in 8.5in;
            margin: 8mm 8mm 52mm 8mm;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 6px 8px;
            font-size: 8px;
            color: #222;
        }

        /* ===== HEADER ===== */
        .header {
            text-align: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #333;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #111;
        }

        .company-address {
            font-size: 9px;
            margin-top: 2px;
            color: #444;
        }

        .title {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 10px;
            margin-top: 10px;
            text-transform: uppercase;
            color: #111;
        }

        .period {
            font-size: 10px;
            margin-top: 3px;
            color: #333;
        }

        /* ===== PROJECT / DEPARTMENT INFO ===== */
        .project-info {
            margin: 8px 0 4px 0;
            font-size: 9px;
        }

        .project-info div {
            margin: 2px 0;
        }

        /* ===== MAIN DATA TABLE ===== */
        table.payroll-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        /* Every cell gets all 4 borders — dompdf-safe, no missing edges */
        table.payroll-table th,
        table.payroll-table td {
            border: 1px solid #444;
            padding: 3px 2px;
            font-size: 7.5px;
            text-align: center;
            vertical-align: middle;
        }

        /* Header row styling — dark background for formal look */
        table.payroll-table thead th {
            background-color: #2c3e50;
            color: #fff;
            font-weight: bold;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 4px 2px;
            border-color: #1a252f;
        }

        /* Sub-header row (HRS, REG OT, SSS, etc) — slightly lighter */
        table.payroll-table thead tr:last-child th {
            background-color: #34495e;
            font-size: 6.5px;
        }

        /* Data cells */
        table.payroll-table tbody td {
            background-color: #fff;
            color: #222;
            border-color: #999;
        }

        /* Zebra striping for readability */
        table.payroll-table tbody tr:nth-child(even) td {
            background-color: #f5f7fa;
        }

        /* Text alignment helpers */
        .text-left {
            text-align: left !important;
            padding-left: 4px !important;
        }

        .text-right {
            text-align: right !important;
            padding-right: 4px !important;
        }

        /* Nothing follows row */
        .nothing-follows {
            text-align: center !important;
            font-style: italic;
            font-size: 8px;
            padding: 4px !important;
            background-color: #fff !important;
            color: #666;
        }

        /* Total row — bold with top accent border */
        .total-row td {
            font-weight: bold;
            font-size: 7.5px;
            background-color: #ebedef !important;
            border-top: 2px solid #2c3e50 !important;
            color: #111;
        }

        /* ===== FIXED FOOTER — SIGNATURES ===== */
        .page-footer {
            position: fixed;
            bottom: -44mm;
            left: 0mm;
            right: 0mm;
            height: 44mm;
            background-color: white;
            padding: 0 2mm;
        }

        .footer-acknowledgment {
            font-size: 7px;
            font-style: italic;
            text-align: center;
            margin-bottom: 6px;
            color: #444;
        }

        table.footer-signature-table {
            width: 100%;
            border: none;
            border-collapse: collapse;
        }

        table.footer-signature-table td {
            border: none;
            text-align: center;
            padding: 1px 4px;
            vertical-align: top;
        }

        .footer-signature-title {
            font-size: 6.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #555;
            margin-bottom: 14px;
        }

        .footer-signature-name {
            font-size: 7px;
            font-weight: bold;
            color: #111;
            border-top: 1px solid #333;
            display: inline-block;
            padding-top: 2px;
            min-width: 80%;
        }

        .footer-signature-position {
            font-size: 6px;
            color: #555;
            font-style: italic;
        }

        /* Hide the inline signature section (footer handles it) */
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
                    <div class="footer-signature-name">JAMAICA CRISTEL MAE SUGABO</div>
                </td>
                <td style="width: 25%;">
                    <div class="footer-signature-name">ENGR. OSTRIC C. RIVERA, III</div>
                </td>
                <td style="width: 25%;">
                    <div class="footer-signature-name">ENGR. ELISA MAY PARCON</div>
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

    <table class="payroll-table">
        <thead>
            <tr>
                <th rowspan="2">NAME</th>
                <th rowspan="2">RATE</th>
                <th rowspan="2">No. of<br>Days</th>
                <th rowspan="2">AMOUNT</th>
                <th colspan="4">OVERTIME</th>
                <th rowspan="2">Adj. Prev.<br>Salary</th>
                <th rowspan="2">Allowance</th>
                <th rowspan="2">GROSS<br>AMOUNT</th>
                <th rowspan="2">Employee's<br>Savings</th>
                <th rowspan="2">Loans</th>
                <th rowspan="2">UT</th>
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
                <td class="text-right">{{ $item->salary_adjustment != 0 ? number_format($item->salary_adjustment, 2) : '' }}</td>
                <td class="text-right">{{ $item->other_allowances > 0 ? number_format($item->other_allowances, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->gross_pay, 2) }}</td>
                <td class="text-right">{{ $item->employee_savings > 0 ? number_format($item->employee_savings, 2) : '' }}</td>
                <td class="text-right">{{ $item->loans > 0 ? number_format($item->loans, 2) : '' }}</td>
                <td class="text-right">{{ $undertimeDeduction > 0 ? number_format($undertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ $item->employee_deductions > 0 ? number_format($item->employee_deductions, 2) : '' }}</td>
                <td class="text-right">{{ $item->sss > 0 ? number_format($item->sss, 2) : '' }}</td>
                <td class="text-right">{{ $item->philhealth > 0 ? number_format($item->philhealth, 2) : '' }}</td>
                <td class="text-right">{{ $item->pagibig > 0 ? number_format($item->pagibig, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
            <tr>
                <td colspan="21" class="nothing-follows"><em>*** nothing follows ***</em></td>
            </tr>
            @php
            $totalUndertimeDeduction = $items->sum(function($item) {
            return $item->undertime_deduction ?? 0;
            });
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
                <td class="text-right">{{ number_format($items->sum('salary_adjustment'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('other_allowances'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('gross_pay'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('employee_savings'), 2) }}</td>
                <td class="text-right">{{ number_format($items->sum('loans'), 2) }}</td>
                <td class="text-right">{{ $totalUndertimeDeduction > 0 ? number_format($totalUndertimeDeduction, 2) : '' }}</td>
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
        @if(str_starts_with($filterInfo, 'Employee:'))
        <div><strong>POSITION:</strong> {{ $payroll->items->first()?->employee?->position ?? 'N/A' }}</div>
        @else
        <div><strong>DESIGNATION:</strong> {{ $payroll->items->first()?->employee?->project?->description ?? 'N/A' }}</div>
        @endif
    </div>
    @else
    <div class="project-info">
        <div><strong>DEPARTMENT:</strong> {{ $payroll->items->first()?->employee->department ?? 'N/A' }}</div>
        <div><strong>DESIGNATION:</strong> {{ $payroll->items->first()?->employee?->project?->description ?? 'N/A' }}</div>
    </div>
    @endif

    <table class="payroll-table">
        <thead>
            <tr>
                <th rowspan="2">NAME</th>
                <th rowspan="2">RATE</th>
                <th rowspan="2">No. of<br>Days</th>
                <th rowspan="2">AMOUNT</th>
                <th colspan="4">OVERTIME</th>
                <th rowspan="2">Adj. Prev.<br>Salary</th>
                <th rowspan="2">Allowance</th>
                <th rowspan="2">GROSS<br>AMOUNT</th>
                <th rowspan="2">Employee's<br>Savings</th>
                <th rowspan="2">Loans</th>
                <th rowspan="2">UT</th>
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
                <td class="text-right">{{ $item->salary_adjustment != 0 ? number_format($item->salary_adjustment, 2) : '' }}</td>
                <td class="text-right">{{ $item->other_allowances > 0 ? number_format($item->other_allowances, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->gross_pay, 2) }}</td>
                <td class="text-right">{{ $item->employee_savings > 0 ? number_format($item->employee_savings, 2) : '' }}</td>
                <td class="text-right">{{ $item->loans > 0 ? number_format($item->loans, 2) : '' }}</td>
                <td class="text-right">{{ $undertimeDeduction > 0 ? number_format($undertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ $item->employee_deductions > 0 ? number_format($item->employee_deductions, 2) : '' }}</td>
                <td class="text-right">{{ $item->sss > 0 ? number_format($item->sss, 2) : '' }}</td>
                <td class="text-right">{{ $item->philhealth > 0 ? number_format($item->philhealth, 2) : '' }}</td>
                <td class="text-right">{{ $item->pagibig > 0 ? number_format($item->pagibig, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
            <tr>
                <td colspan="21" class="nothing-follows"><em>*** nothing follows ***</em></td>
            </tr>
            @php
            $totalUndertimeDeduction = $payroll->items->sum(function($item) {
            return $item->undertime_deduction ?? 0;
            });
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
                <td class="text-right">{{ number_format($payroll->items->sum('salary_adjustment'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('other_allowances'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('gross_pay'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('employee_savings'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('loans'), 2) }}</td>
                <td class="text-right">{{ $totalUndertimeDeduction > 0 ? number_format($totalUndertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('employee_deductions'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('sss'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('philhealth'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('pagibig'), 2) }}</td>
                <td class="text-right">{{ number_format($payroll->items->sum('net_pay'), 2) }}</td>
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
            <div class="signature-name">JAMAICA CRISTEL MAE SUGABOO</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name">ENGR. OSTRIC C. RIVERA, III</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-name">ENGR. ELISA MAY PARCON</div>
        </div>
    </div>
</body>

</html>