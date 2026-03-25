<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payroll Register</title>
    <style>
        @page {
            size: 13in 8.5in;
            margin: 6mm 10mm 5mm 8mm;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            margin: 0;
            padding: 0;
            font-size: 13px;
            color: #000;
        }

        .page-signature-footer {
            position: fixed;
            left: 8mm;
            right: 10mm;
            bottom: 0mm;
            width: calc(100% - 18mm);
            min-height: 72mm;
            padding-top: 6mm;
            padding-bottom: 1mm;
        }

        .page-signature-footer .signature-section {
            margin-top: 0;
        }

        .page-signature-footer .signature-acknowledgment {
            font-size: 12px;
            margin-bottom: 2px;
        }

        .page-signature-footer .signature-title {
            font-size: 11px;
            margin-bottom: 22px;
        }

        .page-signature-footer .signature-name {
            font-size: 12px;
            min-width: 84%;
            margin-bottom: 12px;
        }

        .page-signature-footer .signature-position {
            font-size: 11px;
        }

        /* ===== HEADER ===== */
        .header {
            text-align: center;
            margin-bottom: 8px;
        }

        .company-name {
            font-size: 19px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .company-address {
            font-size: 13px;
            margin-top: 2px;
        }

        .title {
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 8px;
            margin-top: 8px;
            text-transform: uppercase;
        }

        .period {
            font-size: 13px;
            margin-top: 3px;
        }

        /* ===== PROJECT INFO ===== */
        .project-info {
            margin: 6px 0 4px 0;
            font-size: 13px;
        }

        .project-info div {
            margin: 2px 0;
        }

        /* ===== MAIN DATA TABLE ===== */
        table.payroll-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
            margin-top: 4px;
        }

        table.payroll-table th,
        table.payroll-table td {
            border: 1px solid #000;
            padding: 2px 1px;
            font-size: 12px;
            text-align: center;
            vertical-align: middle;
            overflow: visible;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
            line-height: 1.3;
            height: auto;
        }

        tr.page-break-row {
            page-break-after: always;
        }

        tr.page-break-row td {
            border: 0 !important;
            padding: 0 !important;
            height: 0 !important;
            line-height: 0 !important;
        }

        table.payroll-table thead th {
            font-weight: bold;
            font-size: 9.5px;
            text-transform: uppercase;
            padding: 2px 1px;
            line-height: 1.35;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        table.payroll-table thead tr:last-child th {
            font-size: 9px;
            padding: 2px 1px;
        }

        /* Text alignment helpers */
        .text-left {
            text-align: left !important;
            padding-left: 4px !important;
            overflow: visible !important;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .text-right {
            text-align: right !important;
            padding-right: 4px !important;
        }

        /* Nothing follows row */
        .nothing-follows {
            text-align: center !important;
            font-style: italic;
            font-size: 11px;
            padding: 2px !important;
        }

        /* Total row */
        .total-row td {
            font-weight: bold;
            font-size: 11px;
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
            display: block;
            width: 100%;
            box-sizing: border-box;
        }

        .signature-acknowledgment {
            font-size: 13px;
            font-style: italic;
            text-align: center;
            margin-bottom: 8px;
            margin-top: 8px;
            line-height: 1.3;
        }

        table.signature-table {
            width: 100%;
            border: none;
            border-collapse: collapse;
            line-height: 1.2;
            table-layout: fixed;
            margin-top: 6px;
        }

        table.signature-table td {
            border: none;
            text-align: center;
            padding: 0px 2px;
            vertical-align: top;
            width: 25%;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .signature-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 22px;
            word-wrap: break-word;
            line-height: 1.2;
        }

        .signature-name {
            font-size: 13px;
            font-weight: bold;
            display: block;
            padding-top: 1px;
            min-height: 18px;
            margin-bottom: 12px;
            word-wrap: break-word;
            word-break: break-word;
            overflow-wrap: break-word;
            line-height: 1.2;
        }

        .signature-position {
            font-size: 12px;
            font-style: italic;
            margin-top: -3px;
            word-wrap: break-word;
            line-height: 1.2;
        }
    </style>
</head>

<body>
    @php
    $rowsPerPage = max(1, (int) config('payroll.exports.register_rows_per_page', 14));
    @endphp

    <div class="page-signature-footer">
        @include('payroll.partials.signature')
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
    @if(!$loop->first)
    <div style="page-break-before: always; margin-top: 20px;">
        <div class="header" style="margin-bottom: 8px;">
            <div class="company-name">{{ $companyInfo->company_name ?? 'GIOVANNI CONSTRUCTION' }}</div>
            <div class="company-address">{{ $companyInfo->address ?? 'Imadejas Subdivision, Capitol Bonbon' }}</div>
            <div class="title">P A Y R O L L</div>
            <div class="period">
                {{ \Carbon\Carbon::parse($payroll->period_start)->format('F d') }} - {{ \Carbon\Carbon::parse($payroll->period_end)->format('d, Y') }}
            </div>
        </div>
        <div class="project-info">
            <div><strong>{{ $filterType === 'staff_type' ? 'STAFF TYPE' : 'PROJECT' }}:</strong> {{ $groupName }}</div>
            <div><strong>DESIGNATION:</strong> {{ $items->first()?->employee?->project?->description ?? 'N/A' }}</div>
        </div>
    </div>
    @else
    <div class="project-info">
        <div><strong>{{ $filterType === 'staff_type' ? 'STAFF TYPE' : 'PROJECT' }}:</strong> {{ $groupName }}</div>
        <div><strong>DESIGNATION:</strong> {{ $items->first()?->employee?->project?->description ?? 'N/A' }}</div>
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
                <th rowspan="2">Cash<br>Advance</th>
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
            @php
            $lastPageMaxRows = 20;
            $totalGroupItems = count($items);
            $pageBreakIndices = [];
            $pi = 0;
            while ($pi < $totalGroupItems) {
                $remaining = $totalGroupItems - $pi;
                if ($remaining <= $lastPageMaxRows) break;
                if ($remaining <= $rowsPerPage) {
                    $pageBreakIndices[] = $pi + ($remaining - $lastPageMaxRows);
                    break;
                }
                $pageBreakIndices[] = $pi + $rowsPerPage;
                $pi += $rowsPerPage;
            }
            $currentPageStart = 0;
            @endphp
            @foreach($items as $index => $item)
            @php
            $undertimeDeduction = $item->undertime_deduction ?? 0;
            $amount = ($item->effective_rate ?? 0) * ($item->days_worked ?? 0);
            $sunSplHolHours = ($item->special_ot_hours ?? 0) + ($item->sunday_hours ?? 0);
            $sunSplHolPay = ($item->special_ot_pay ?? 0) + ($item->sunday_pay ?? 0);
            @endphp
            {{-- Add header before each new page of data --}}
            @if(in_array($index, $pageBreakIndices))
            @php
            $pageItems = $items->slice($currentPageStart, $index - $currentPageStart);
            $pageUndertimeDeduction = $pageItems->sum(function($entry) {
            return $entry->undertime_deduction ?? 0;
            });
            $pageAmount = $pageItems->sum(function($entry) {
            return ($entry->effective_rate ?? 0) * ($entry->days_worked ?? 0);
            });
            $pageSunSplHolHours = $pageItems->sum(function($entry) {
            return ($entry->special_ot_hours ?? 0) + ($entry->sunday_hours ?? 0);
            });
            $pageSunSplHolPay = $pageItems->sum(function($entry) {
            return ($entry->special_ot_pay ?? 0) + ($entry->sunday_pay ?? 0);
            });
            @endphp
            <tr>
                <td colspan="21" class="nothing-follows"><em>*** nothing follows ***</em></td>
            </tr>
            <tr class="total-row">
                <td class="text-left"><strong>T O T A L</strong></td>
                <td></td>
                <td></td>
                <td class="text-right">{{ number_format($pageAmount, 2) }}</td>
                <td>{{ number_format($pageItems->sum('regular_ot_hours'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('regular_ot_pay'), 2) }}</td>
                <td>{{ number_format($pageSunSplHolHours, 2) }}</td>
                <td class="text-right">{{ number_format($pageSunSplHolPay, 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('salary_adjustment'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('other_allowances'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('gross_pay'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('employee_savings'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('loans'), 2) }}</td>
                <td class="text-right">{{ $pageUndertimeDeduction > 0 ? number_format($pageUndertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('employee_deductions'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('cash_advance'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('sss'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('philhealth'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('pagibig'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('net_pay'), 2) }}</td>
                <td></td>
            </tr>
            </tbody>
            </table>
            </div>

            <div style="page-break-before: always; margin-top: 20px;">
                <div class="header" style="margin-bottom: 8px;">
                    <div class="company-name">{{ $companyInfo->company_name ?? 'GIOVANNI CONSTRUCTION' }}</div>
                    <div class="company-address">{{ $companyInfo->address ?? 'Imadejas Subdivision, Capitol Bonbon' }}</div>
                    <div class="title">P A Y R O L L</div>
                    <div class="period">
                        {{ \Carbon\Carbon::parse($payroll->period_start)->format('F d') }} - {{ \Carbon\Carbon::parse($payroll->period_end)->format('d, Y') }}
                    </div>
                </div>
                <div class="project-info">
                    <div><strong>{{ $filterType === 'staff_type' ? 'STAFF TYPE' : 'PROJECT' }}:</strong> {{ $groupName }}</div>
                    <div><strong>DESIGNATION:</strong> {{ $items->first()?->employee?->project?->description ?? 'N/A' }}</div>
                </div>
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
                        <th rowspan="2">Cash<br>Advance</th>
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
            @php
            $currentPageStart = $index;
            @endphp
            @endif
            <tr>
                <td class="text-left">{{ $index + 1 }}. {{ $item->employee->full_name }}</td>
                <td class="text-right">{{ number_format($item->effective_rate, 2) }}</td>
                <td>{{ rtrim(rtrim(number_format($item->days_worked, 2), '0'), '.') }}</td>
                <td class="text-right">{{ number_format($amount, 2) }}</td>
                <td>{{ $item->regular_ot_hours > 0 ? number_format($item->regular_ot_hours, 2) : '' }}</td>
                <td class="text-right">{{ $item->regular_ot_pay > 0 ? number_format($item->regular_ot_pay, 2) : '' }}</td>
                <td>{{ $sunSplHolHours > 0 ? number_format($sunSplHolHours, 2) : '' }}</td>
                <td class="text-right">{{ $sunSplHolPay > 0 ? number_format($sunSplHolPay, 2) : '' }}</td>
                <td class="text-right">{{ $item->salary_adjustment != 0 ? number_format($item->salary_adjustment, 2) : '' }}</td>
                <td class="text-right">{{ $item->other_allowances > 0 ? number_format($item->other_allowances, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->gross_pay, 2) }}</td>
                <td class="text-right">{{ $item->employee_savings > 0 ? number_format($item->employee_savings, 2) : '' }}</td>
                <td class="text-right">{{ $item->loans > 0 ? number_format($item->loans, 2) : '' }}</td>
                <td class="text-right">{{ $undertimeDeduction > 0 ? number_format($undertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ $item->employee_deductions > 0 ? number_format($item->employee_deductions, 2) : '' }}</td>
                <td class="text-right">{{ $item->cash_advance > 0 ? number_format($item->cash_advance, 2) : '' }}</td>
                <td class="text-right">{{ $item->sss > 0 ? number_format($item->sss, 2) : '' }}</td>
                <td class="text-right">{{ $item->philhealth > 0 ? number_format($item->philhealth, 2) : '' }}</td>
                <td class="text-right">{{ $item->pagibig > 0 ? number_format($item->pagibig, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
            @php
            $finalPageItems = $items->slice($currentPageStart);
            $totalUndertimeDeduction = $finalPageItems->sum(function($entry) {
            return $entry->undertime_deduction ?? 0;
            });
            $totalAmount = $finalPageItems->sum(function($entry) {
            return ($entry->effective_rate ?? 0) * ($entry->days_worked ?? 0);
            });
            $totalSunSplHolHours = $finalPageItems->sum(function($entry) {
            return ($entry->special_ot_hours ?? 0) + ($entry->sunday_hours ?? 0);
            });
            $totalSunSplHolPay = $finalPageItems->sum(function($entry) {
            return ($entry->special_ot_pay ?? 0) + ($entry->sunday_pay ?? 0);
            });
            @endphp
            <tr>
                <td colspan="21" class="nothing-follows"><em>*** nothing follows ***</em></td>
            </tr>
            <tr class="total-row">
                <td class="text-left"><strong>T O T A L</strong></td>
                <td></td>
                <td></td>
                <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
                <td>{{ number_format($finalPageItems->sum('regular_ot_hours'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('regular_ot_pay'), 2) }}</td>
                <td>{{ number_format($totalSunSplHolHours, 2) }}</td>
                <td class="text-right">{{ number_format($totalSunSplHolPay, 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('salary_adjustment'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('other_allowances'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('gross_pay'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('employee_savings'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('loans'), 2) }}</td>
                <td class="text-right">{{ $totalUndertimeDeduction > 0 ? number_format($totalUndertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('employee_deductions'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('cash_advance'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('sss'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('philhealth'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('pagibig'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('net_pay'), 2) }}</td>
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
                <th rowspan="2">Cash<br>Advance</th>
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
            @php
            $lastPageMaxRows = 15;
            $totalGroupItems = count($payroll->items);
            $pageBreakIndices = [];
            $pi = 0;
            while ($pi < $totalGroupItems) {
                $remaining = $totalGroupItems - $pi;
                if ($remaining <= $lastPageMaxRows) break;
                if ($remaining <= $rowsPerPage) {
                    $pageBreakIndices[] = $pi + ($remaining - $lastPageMaxRows);
                    break;
                }
                $pageBreakIndices[] = $pi + $rowsPerPage;
                $pi += $rowsPerPage;
            }
            $currentPageStart = 0;
            @endphp
            @foreach($payroll->items as $index => $item)
            @php
            $undertimeDeduction = $item->undertime_deduction ?? 0;
            $amount = ($item->effective_rate ?? 0) * ($item->days_worked ?? 0);
            $sunSplHolHours = ($item->special_ot_hours ?? 0) + ($item->sunday_hours ?? 0);
            $sunSplHolPay = ($item->special_ot_pay ?? 0) + ($item->sunday_pay ?? 0);
            @endphp
            {{-- Add header before each new page of data --}}
            @if(in_array($index, $pageBreakIndices))
            @php
            $pageItems = $payroll->items->slice($currentPageStart, $index - $currentPageStart);
            $pageUndertimeDeduction = $pageItems->sum(function($entry) {
            return $entry->undertime_deduction ?? 0;
            });
            $pageAmount = $pageItems->sum(function($entry) {
            return ($entry->effective_rate ?? 0) * ($entry->days_worked ?? 0);
            });
            $pageSunSplHolHours = $pageItems->sum(function($entry) {
            return ($entry->special_ot_hours ?? 0) + ($entry->sunday_hours ?? 0);
            });
            $pageSunSplHolPay = $pageItems->sum(function($entry) {
            return ($entry->special_ot_pay ?? 0) + ($entry->sunday_pay ?? 0);
            });
            @endphp
            <tr>
                <td colspan="21" class="nothing-follows"><em>*** nothing follows ***</em></td>
            </tr>
            <tr class="total-row">
                <td class="text-left"><strong>T O T A L</strong></td>
                <td></td>
                <td></td>
                <td class="text-right">{{ number_format($pageAmount, 2) }}</td>
                <td>{{ number_format($pageItems->sum('regular_ot_hours'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('regular_ot_pay'), 2) }}</td>
                <td>{{ number_format($pageSunSplHolHours, 2) }}</td>
                <td class="text-right">{{ number_format($pageSunSplHolPay, 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('salary_adjustment'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('other_allowances'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('gross_pay'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('employee_savings'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('loans'), 2) }}</td>
                <td class="text-right">{{ $pageUndertimeDeduction > 0 ? number_format($pageUndertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('employee_deductions'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('cash_advance'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('sss'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('philhealth'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('pagibig'), 2) }}</td>
                <td class="text-right">{{ number_format($pageItems->sum('net_pay'), 2) }}</td>
                <td></td>
            </tr>
            </tbody>
            </table>

            <div style="page-break-before: always; margin-top: 20px;">
                <div class="header" style="margin-bottom: 8px;">
                    <div class="company-name">{{ $companyInfo->company_name ?? 'GIOVANNI CONSTRUCTION' }}</div>
                    <div class="company-address">{{ $companyInfo->address ?? 'Imadejas Subdivision, Capitol Bonbon' }}</div>
                    <div class="title">P A Y R O L L</div>
                    <div class="period">
                        {{ \Carbon\Carbon::parse($payroll->period_start)->format('F d') }} - {{ \Carbon\Carbon::parse($payroll->period_end)->format('d, Y') }}
                    </div>
                </div>
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
                        <th rowspan="2">Cash<br>Advance</th>
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
            @php
            $currentPageStart = $index;
            @endphp
            @endif
            <tr>
                <td class="text-left">{{ $index + 1 }}. {{ $item->employee->full_name }}</td>
                <td class="text-right">{{ number_format($item->effective_rate, 2) }}</td>
                <td>{{ rtrim(rtrim(number_format($item->days_worked, 2), '0'), '.') }}</td>
                <td class="text-right">{{ number_format($amount, 2) }}</td>
                <td>{{ $item->regular_ot_hours > 0 ? number_format($item->regular_ot_hours, 2) : '' }}</td>
                <td class="text-right">{{ $item->regular_ot_pay > 0 ? number_format($item->regular_ot_pay, 2) : '' }}</td>
                <td>{{ $sunSplHolHours > 0 ? number_format($sunSplHolHours, 2) : '' }}</td>
                <td class="text-right">{{ $sunSplHolPay > 0 ? number_format($sunSplHolPay, 2) : '' }}</td>
                <td class="text-right">{{ $item->salary_adjustment != 0 ? number_format($item->salary_adjustment, 2) : '' }}</td>
                <td class="text-right">{{ $item->other_allowances > 0 ? number_format($item->other_allowances, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->gross_pay, 2) }}</td>
                <td class="text-right">{{ $item->employee_savings > 0 ? number_format($item->employee_savings, 2) : '' }}</td>
                <td class="text-right">{{ $item->loans > 0 ? number_format($item->loans, 2) : '' }}</td>
                <td class="text-right">{{ $undertimeDeduction > 0 ? number_format($undertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ $item->employee_deductions > 0 ? number_format($item->employee_deductions, 2) : '' }}</td>
                <td class="text-right">{{ $item->cash_advance > 0 ? number_format($item->cash_advance, 2) : '' }}</td>
                <td class="text-right">{{ $item->sss > 0 ? number_format($item->sss, 2) : '' }}</td>
                <td class="text-right">{{ $item->philhealth > 0 ? number_format($item->philhealth, 2) : '' }}</td>
                <td class="text-right">{{ $item->pagibig > 0 ? number_format($item->pagibig, 2) : '' }}</td>
                <td class="text-right">{{ number_format($item->net_pay, 2) }}</td>
                <td></td>
            </tr>
            @endforeach
            @php
            $finalPageItems = $payroll->items->slice($currentPageStart);
            $totalUndertimeDeduction = $finalPageItems->sum(function($entry) {
            return $entry->undertime_deduction ?? 0;
            });
            $totalAmount = $finalPageItems->sum(function($entry) {
            return ($entry->effective_rate ?? 0) * ($entry->days_worked ?? 0);
            });
            $totalSunSplHolHours = $finalPageItems->sum(function($entry) {
            return ($entry->special_ot_hours ?? 0) + ($entry->sunday_hours ?? 0);
            });
            $totalSunSplHolPay = $finalPageItems->sum(function($entry) {
            return ($entry->special_ot_pay ?? 0) + ($entry->sunday_pay ?? 0);
            });
            @endphp
            <tr>
                <td colspan="21" class="nothing-follows"><em>*** nothing follows ***</em></td>
            </tr>
            <tr class="total-row">
                <td class="text-left"><strong>T O T A L</strong></td>
                <td></td>
                <td></td>
                <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
                <td>{{ number_format($finalPageItems->sum('regular_ot_hours'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('regular_ot_pay'), 2) }}</td>
                <td>{{ number_format($totalSunSplHolHours, 2) }}</td>
                <td class="text-right">{{ number_format($totalSunSplHolPay, 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('salary_adjustment'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('other_allowances'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('gross_pay'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('employee_savings'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('loans'), 2) }}</td>
                <td class="text-right">{{ $totalUndertimeDeduction > 0 ? number_format($totalUndertimeDeduction, 2) : '' }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('employee_deductions'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('cash_advance'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('sss'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('philhealth'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('pagibig'), 2) }}</td>
                <td class="text-right">{{ number_format($finalPageItems->sum('net_pay'), 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    @endif
</body>

</html>