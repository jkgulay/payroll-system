<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payroll Register</title>
    <style>
        @page {
            size: 13in 8.5in;
            margin: 6mm 10mm 6mm 8mm;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 0;
            font-size: 11px;
            color: #000;
        }

        /* ===== HEADER ===== */
        .header {
            text-align: center;
            margin-bottom: 8px;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .company-address {
            font-size: 11px;
            margin-top: 2px;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 8px;
            margin-top: 8px;
            text-transform: uppercase;
        }

        .period {
            font-size: 12px;
            margin-top: 3px;
        }

        /* ===== PROJECT INFO ===== */
        .project-info {
            margin: 6px 0 4px 0;
            font-size: 11px;
        }

        .project-info div {
            margin: 2px 0;
        }

        /* ===== MAIN DATA TABLE ===== */
        table.payroll-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 4px;
        }

        table.payroll-table th,
        table.payroll-table td {
            border: 1px solid #000;
            padding: 2px 1px;
            font-size: 8px;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
        }

        table.payroll-table thead th {
            font-weight: bold;
            font-size: 7.5px;
            text-transform: uppercase;
            padding: 3px 1px;
        }

        table.payroll-table thead tr:last-child th {
            font-size: 7px;
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
            font-size: 9px;
            padding: 4px !important;
        }

        /* Total row */
        .total-row td {
            font-weight: bold;
            font-size: 9px;
        }

        /* Footer block: nothing-follows + total + signature kept together */
        .table-footer-block {
            page-break-inside: avoid;
        }

        .table-footer-block table.payroll-table {
            margin-top: 0;
        }

        /* ===== SIGNATURE SECTION (inline, under table) ===== */
        .signature-section {
            margin-top: 10px;
        }

        .signature-acknowledgment {
            font-size: 9px;
            font-style: italic;
            text-align: center;
            margin-bottom: 6px;
        }

        table.signature-table {
            width: 100%;
            border: none;
            border-collapse: collapse;
        }

        table.signature-table td {
            border: none;
            text-align: center;
            padding: 1px 4px;
            vertical-align: top;
        }

        .signature-title {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 14px;
        }

        .signature-name {
            font-size: 9px;
            font-weight: bold;
            border-top: 1px solid #000;
            display: inline-block;
            padding-top: 2px;
            min-width: 80%;
        }

        .signature-position {
            font-size: 8px;
            font-style: italic;
        }
    </style>
</head>

<body>
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
        <div><strong>{{ $filterType === 'staff_type' ? 'STAFF TYPE' : 'PROJECT' }}:</strong> {{ $groupName }}</div>
        <div><strong>DESIGNATION:</strong> {{ $items->first()?->employee?->project?->description ?? 'N/A' }}</div>
    </div>

    <table class="payroll-table">
        <colgroup>
            <col style="width: 13.5%">
            <col style="width: 4.5%">
            <col style="width: 3%">
            <col style="width: 5%">
            <col style="width: 2.8%">
            <col style="width: 4.5%">
            <col style="width: 2.8%">
            <col style="width: 4.5%">
            <col style="width: 4.3%">
            <col style="width: 4.3%">
            <col style="width: 5.5%">
            <col style="width: 3.8%">
            <col style="width: 3.5%">
            <col style="width: 3%">
            <col style="width: 3.8%">
            <col style="width: 3.8%">
            <col style="width: 3.5%">
            <col style="width: 3.5%">
            <col style="width: 3.5%">
            <col style="width: 5.5%">
            <col style="width: 7%">
        </colgroup>
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
                <th rowspan="2">Other<br>Ded.</th>
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
                <td class="text-right">{{ $item->other_deductions > 0 ? number_format($item->other_deductions, 2) : '' }}</td>
                <td class="text-right">{{ $item->sss > 0 ? number_format($item->sss, 2) : '' }}</td>
                <td class="text-right">{{ $item->philhealth > 0 ? number_format($item->philhealth, 2) : '' }}</td>
                <td class="text-right">{{ $item->pagibig > 0 ? number_format($item->pagibig, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="table-footer-block">
        @php
        $totalUndertimeDeduction = $items->sum(function($item) {
        return $item->undertime_deduction ?? 0;
        });
        $totalAmount = $items->sum(function($item) {
        return ($item->effective_rate ?? 0) * ($item->days_worked ?? 0);
        });
        @endphp
        <table class="payroll-table">
            <colgroup>
                <col style="width: 13.5%">
                <col style="width: 4.5%">
                <col style="width: 3%">
                <col style="width: 5%">
                <col style="width: 2.8%">
                <col style="width: 4.5%">
                <col style="width: 2.8%">
                <col style="width: 4.5%">
                <col style="width: 4.3%">
                <col style="width: 4.3%">
                <col style="width: 5.5%">
                <col style="width: 3.8%">
                <col style="width: 3.5%">
                <col style="width: 3%">
                <col style="width: 3.8%">
                <col style="width: 3.8%">
                <col style="width: 3.5%">
                <col style="width: 3.5%">
                <col style="width: 3.5%">
                <col style="width: 5.5%">
                <col style="width: 7%">
            </colgroup>
            <tbody>
                <tr>
                    <td colspan="21" class="nothing-follows"><em>*** nothing follows ***</em></td>
                </tr>
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
                    <td class="text-right">{{ number_format($items->sum('other_deductions'), 2) }}</td>
                    <td class="text-right">{{ number_format($items->sum('sss'), 2) }}</td>
                    <td class="text-right">{{ number_format($items->sum('philhealth'), 2) }}</td>
                    <td class="text-right">{{ number_format($items->sum('pagibig'), 2) }}</td>
                    <td class="text-right">{{ number_format($items->sum('net_pay'), 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        @include('payroll.partials.signature')
    </div>
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
        <div><strong>PROJECT:</strong> {{ $payroll->items->first()?->employee->department ?? 'N/A' }}</div>
        <div><strong>DESIGNATION:</strong> {{ $payroll->items->first()?->employee?->project?->description ?? 'N/A' }}</div>
    </div>
    @endif

    <table class="payroll-table">
        <colgroup>
            <col style="width: 13.5%">
            <col style="width: 4.5%">
            <col style="width: 3%">
            <col style="width: 5%">
            <col style="width: 2.8%">
            <col style="width: 4.5%">
            <col style="width: 2.8%">
            <col style="width: 4.5%">
            <col style="width: 4.3%">
            <col style="width: 4.3%">
            <col style="width: 5.5%">
            <col style="width: 3.8%">
            <col style="width: 3.5%">
            <col style="width: 3%">
            <col style="width: 3.8%">
            <col style="width: 3.8%">
            <col style="width: 3.5%">
            <col style="width: 3.5%">
            <col style="width: 3.5%">
            <col style="width: 5.5%">
            <col style="width: 7%">
        </colgroup>
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
                <th rowspan="2">Other<br>Ded.</th>
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
                <td class="text-right">{{ $item->other_deductions > 0 ? number_format($item->other_deductions, 2) : '' }}</td>
                <td class="text-right">{{ $item->sss > 0 ? number_format($item->sss, 2) : '' }}</td>
                <td class="text-right">{{ $item->philhealth > 0 ? number_format($item->philhealth, 2) : '' }}</td>
                <td class="text-right">{{ $item->pagibig > 0 ? number_format($item->pagibig, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="table-footer-block">
        @php
        $totalUndertimeDeduction = $payroll->items->sum(function($item) {
        return $item->undertime_deduction ?? 0;
        });
        $totalAmount = $payroll->items->sum(function($item) {
        return ($item->effective_rate ?? 0) * ($item->days_worked ?? 0);
        });
        @endphp
        <table class="payroll-table">
            <colgroup>
                <col style="width: 13.5%">
                <col style="width: 4.5%">
                <col style="width: 3%">
                <col style="width: 5%">
                <col style="width: 2.8%">
                <col style="width: 4.5%">
                <col style="width: 2.8%">
                <col style="width: 4.5%">
                <col style="width: 4.3%">
                <col style="width: 4.3%">
                <col style="width: 5.5%">
                <col style="width: 3.8%">
                <col style="width: 3.5%">
                <col style="width: 3%">
                <col style="width: 3.8%">
                <col style="width: 3.8%">
                <col style="width: 3.5%">
                <col style="width: 3.5%">
                <col style="width: 3.5%">
                <col style="width: 5.5%">
                <col style="width: 7%">
            </colgroup>
            <tbody>
                <tr>
                    <td colspan="21" class="nothing-follows"><em>*** nothing follows ***</em></td>
                </tr>
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
                    <td class="text-right">{{ number_format($payroll->items->sum('other_deductions'), 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->items->sum('sss'), 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->items->sum('philhealth'), 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->items->sum('pagibig'), 2) }}</td>
                    <td class="text-right">{{ number_format($payroll->items->sum('net_pay'), 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        @include('payroll.partials.signature')
    </div>
    @endif
</body>

</html>