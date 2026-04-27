<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payslips - {{ $payroll->period_name }}</title>
    @php
    $pageScaleValue = (float) ($pageScale ?? 1);
    $isA4ScaledLayout = $pageScaleValue < 0.999;
        @endphp
        <style>
        @page {
        margin: 6mm 10mm 6mm 8mm;
        size: 8.5in 13in;
        }

        * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        }

        body {
        font-family: Arial, sans-serif;
        font-size: 9.5px;
        line-height: 1.4;
        color: #000;
        }

        body.a4-scaled-layout {
        transform: scale(0.97);
        transform-origin: top left;
        width: 103.0928%;
        }

        .page-wrapper {
        page-break-after: always;
        position: relative;
        }

        .page-wrapper:last-child {
        page-break-after: auto;
        }

        .payslips-grid {
        width: 100%;
        border-collapse: separate;
        border-spacing: 7px 7px;
        }

        .payslip-cell {
        width: 50%;
        vertical-align: top;
        padding: 0;
        }

        .payslip {
        border: 1px solid #000;
        }

        /* Header */
        .slip-header {
        text-align: center;
        padding: 7px 10px;
        border-bottom: 1px solid #000;
        }

        .company-name {
        font-size: 11.5px;
        font-weight: bold;
        letter-spacing: 0.5px;
        }

        .company-address {
        font-size: 8px;
        margin-top: 1px;
        }

        .company-phone {
        font-size: 8px;
        }

        /* Main content table - using table for alignment */
        .slip-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
        }

        .slip-table td {
        padding: 2.5px 10px;
        vertical-align: top;
        }

        .slip-table .label-col {
        width: 42%;
        }

        .slip-table .colon-col {
        width: 3%;
        text-align: center;
        }

        .slip-table .value-col {
        width: 27%;
        text-align: right;
        }

        .slip-table .amount-col {
        width: 28%;
        text-align: right;
        }

        .slip-table .name-value {
        text-align: right;
        font-weight: bold;
        }

        .slip-table .bold {
        font-weight: bold;
        }

        .section-label {
        font-weight: bold;
        font-style: italic;
        padding-top: 5px !important;
        }

        /* Separator lines - only on first amount column */
        .separator-line td {
        padding: 0 !important;
        height: 1px;
        }

        .separator-line .line-right {
        border-top: 1px solid #000;
        }

        .separator-line-thick td {
        padding: 0 !important;
        height: 1px;
        }

        .separator-line-thick .line-right {
        border-top: 2px solid #000;
        }

        .space-row td {
        padding: 2px !important;
        }

        /* Signature section inside payslip */
        .slip-signature {
        padding: 7px 10px;
        border-top: 1px solid #000;
        font-size: 8px;
        }

        .slip-signature table {
        width: 100%;
        border-collapse: collapse;
        }

        .slip-signature td {
        padding: 2px 0;
        }

        .sig-name {
        text-decoration: underline;
        font-weight: bold;
        font-style: italic;
        }

        .received-line {
        border-bottom: 1px solid #000;
        display: inline-block;
        width: 100px;
        }

        /* Page footer signature section */
        .page-footer {
        width: 100%;
        margin-top: 12px;
        border-top: 1px solid #000;
        padding-top: 8px;
        }

        .footer-sig-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9px;
        }

        .footer-sig-table td {
        padding: 2px 5px;
        vertical-align: top;
        width: 25%;
        }

        .footer-sig-label {
        font-size: 8px;
        margin-bottom: 15px;
        }

        .footer-sig-name {
        font-weight: bold;
        text-decoration: underline;
        font-size: 9px;
        }

        .proprietor-section {
        text-align: center;
        margin-top: 12px;
        padding-top: 8px;
        border-top: 1px solid #000;
        }

        .proprietor-name {
        font-size: 10px;
        font-weight: bold;
        letter-spacing: 1px;
        }

        .proprietor-title {
        font-size: 9px;
        margin-top: 2px;
        }
        </style>
</head>

<body class="{{ $isA4ScaledLayout ? 'a4-scaled-layout' : '' }}">
    @php
    $resolveProjectName = function ($item) {
    $employee = $item->employee;
    $projectName = trim((string) ($employee?->project?->name ?? $employee?->department ?? ''));

    return $projectName !== '' ? $projectName : 'Unassigned Project';
    };

    // Keep pages grouped by project so each 4-slot sheet contains employees
    // from the same project as much as possible.
    $items = $payroll->items
    ->sortBy(function ($item) use ($resolveProjectName) {
    $project = strtolower($resolveProjectName($item));
    $employeeNumber = strtolower((string) ($item->employee?->employee_number ?? ''));
    $employeeName = strtolower(trim((string) ($item->employee?->full_name ?? '')));

    return $project . '|' . $employeeNumber . '|' . $employeeName;
    })
    ->values();

    // Keep project ordering, but continue filling each 4-slot page without
    // forcing a page break when project changes.
    $chunks = $items->chunk(4);

    $periodStart = \Carbon\Carbon::parse($payroll->period_start)->format('F d');
    $periodEnd = \Carbon\Carbon::parse($payroll->period_end)->format('M d, Y');
    $periodDisplay = $periodStart . ' - ' . $periodEnd;

    // Signature names from company info or defaults
    $preparedBy = $companyInfo->sig_payslip_prepared_by
    ?? $companyInfo->sig_prepared_by
    ?? 'MERCIEL LAVASAN';
    $checkedBy = $companyInfo->sig_payslip_checked_by
    ?? $companyInfo->sig_checked_by
    ?? 'SAIRAH JENITA';
    $recommendedBy = $companyInfo->sig_payslip_recommended_by
    ?? $companyInfo->sig_recommended_by
    ?? 'ENGR. FRANCIS GIOVANNI C. RIVERA';
    $approvedBy = $companyInfo->sig_payslip_approved_by
    ?? $companyInfo->sig_approved_by
    ?? 'ENGR. OSTRIC R. RIVERA JR.';
    @endphp

    @foreach($chunks as $chunkIndex => $chunk)
    @php
    $chunkArray = $chunk->values()->all();
    @endphp
    <div class="page-wrapper">
        <table class="payslips-grid">
            @for($row = 0; $row < 2; $row++)
                <tr>
                @for($col = 0; $col < 2; $col++)
                    @php
                    $idx=$row * 2 + $col;
                    $item=$chunkArray[$idx] ?? null;
                    @endphp
                    <td class="payslip-cell">
                    @if($item)
                    @php
                    $employee = $item->employee;
                    $rate = $item->effective_rate ?? $item->rate ?? 0;
                    $daysWorked = $item->days_worked ?? 0;
                    $basicAmount = $rate * $daysWorked;

                    $regOtHours = $item->regular_ot_hours ?? 0;
                    $regOtPay = $item->regular_ot_pay ?? 0;
                    $splHolHours = $item->special_ot_hours ?? 0;
                    $splHolPay = $item->special_ot_pay ?? 0;
                    $allowances = $item->other_allowances ?? 0;
                    $salaryAdj = $item->salary_adjustment ?? 0;
                    $grossAmount = $item->gross_pay ?? 0;

                    // Deductions
                    $cashAdvance = $item->cash_advance ?? 0;
                    $employeeSavings = $item->employee_savings ?? 0;
                    $loanDeductions = $item->loans ?? 0;
                    $combinedDeductions = ($item->employee_deductions ?? 0) + ($item->other_deductions ?? 0);
                    $undertime = $item->undertime_deduction ?? 0;
                    $sssPrem = $item->sss ?? 0;
                    $phicPrem = $item->philhealth ?? 0;
                    $hdmfPrem = $item->pagibig ?? 0;
                    $totalDeductions = $item->total_deductions ?? 0;
                    $netAmount = $item->net_pay ?? 0;
                    @endphp

                    <div class="payslip">
                        <!-- Header -->
                        <div class="slip-header">
                            <div class="company-name">{{ strtoupper($companyInfo->company_name ?? 'GIOVANNI CONSTRUCTION') }}</div>
                            <div class="company-address">{{ $companyInfo->address ?? '863 Capitol-Bonbon Road, Imadejas Subd., Butuan City' }}</div>
                            <div class="company-phone">Tel. No. {{ $companyInfo->phone ?? '(085) 817-1833' }}</div>
                        </div>

                        <!-- Main Table -->
                        <table class="slip-table">
                            <tr>
                                <td class="label-col">Name</td>
                                <td class="colon-col">:</td>
                                <td colspan="2" class="name-value">{{ $item->employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name) }}</td>
                            </tr>
                            <tr>
                                <td>Payroll Period</td>
                                <td class="colon-col">:</td>
                                <td colspan="2" style="text-align: right;">{{ $periodDisplay }}</td>
                            </tr>
                            <tr>
                                <td>Daily Rate</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">PHP {{ number_format($rate, 2) }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>No. of Days</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ rtrim(rtrim(number_format($daysWorked, 2), '0'), '.') }}</td>
                                <td class="amount-col">PHP {{ number_format($basicAmount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Reg. Overtime</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $regOtHours > 0 ? number_format($regOtHours, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $regOtPay > 0 ? 'PHP ' . number_format($regOtPay, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>Sun./Spl. Hol.</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $splHolHours > 0 ? number_format($splHolHours, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $splHolPay > 0 ? 'PHP ' . number_format($splHolPay, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>Allowance</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $allowances > 0 ? number_format($allowances, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $allowances > 0 ? 'PHP ' . number_format($allowances, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>TRIPS/Sal. Adj.</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $salaryAdj != 0 ? number_format(abs($salaryAdj), 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $salaryAdj != 0 ? 'PHP ' . number_format(abs($salaryAdj), 2) : '' }}</td>
                            </tr>
                            <tr class="separator-line">
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="line-right"></td>
                            </tr>
                            <tr>
                                <td class="bold">GROSS AMOUNT</td>
                                <td class="colon-col"></td>
                                <td class="value-col"></td>
                                <td class="amount-col bold">PHP {{ number_format($grossAmount, 2) }}</td>
                            </tr>
                            <tr class="space-row">
                                <td colspan="4"></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="section-label">LESS DEDUCTION:</td>
                            </tr>
                            <tr>
                                <td>EMP. SAVINGS</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $employeeSavings > 0 ? number_format($employeeSavings, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $employeeSavings > 0 ? 'PHP ' . number_format($employeeSavings, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>CASH ADVANCE</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $cashAdvance > 0 ? number_format($cashAdvance, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $cashAdvance > 0 ? 'PHP ' . number_format($cashAdvance, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>LOANS</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $loanDeductions > 0 ? number_format($loanDeductions, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $loanDeductions > 0 ? 'PHP ' . number_format($loanDeductions, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>DEDUCTIONS</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $combinedDeductions > 0 ? number_format($combinedDeductions, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $combinedDeductions > 0 ? 'PHP ' . number_format($combinedDeductions, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>UNDERTIME</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $undertime > 0 ? number_format($undertime, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $undertime > 0 ? 'PHP ' . number_format($undertime, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>SSS Prem.</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $sssPrem > 0 ? number_format($sssPrem, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $sssPrem > 0 ? 'PHP ' . number_format($sssPrem, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>PHIC Prem.</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $phicPrem > 0 ? number_format($phicPrem, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $phicPrem > 0 ? 'PHP ' . number_format($phicPrem, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>HDMF Prem.</td>
                                <td class="colon-col">:</td>
                                <td class="value-col">{{ $hdmfPrem > 0 ? number_format($hdmfPrem, 2) : '0.00' }}</td>
                                <td class="amount-col">{{ $hdmfPrem > 0 ? 'PHP ' . number_format($hdmfPrem, 2) : '' }}</td>
                            </tr>
                            <tr class="separator-line">
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="line-right"></td>
                            </tr>
                            <tr>
                                <td class="bold">Total Deductions</td>
                                <td class="colon-col"></td>
                                <td class="value-col"></td>
                                <td class="amount-col bold">PHP {{ number_format($totalDeductions, 2) }}</td>
                            </tr>
                            <tr class="separator-line-thick">
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="line-right"></td>
                            </tr>
                            <tr>
                                <td class="bold">NET AMOUNT</td>
                                <td class="colon-col"></td>
                                <td class="value-col"></td>
                                <td class="amount-col bold">PHP {{ number_format($netAmount, 2) }}</td>
                            </tr>
                        </table>

                        <!-- Payslip Signature -->
                        <div class="slip-signature">
                            <table>
                                <tr>
                                    <td style="text-align: left;">Prepared by: <span class="sig-name">{{ $preparedBy }}</span></td>
                                    <td style="text-align: right;">Checked by: <span class="sig-name">{{ $checkedBy }}</span></td>
                                </tr>
                                <tr>
                                    <td style="text-align: left;">Recommended by: <span class="sig-name">{{ $recommendedBy }}</span></td>
                                    <td style="text-align: right;">Approved by: <span class="sig-name">{{ $approvedBy }}</span></td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="text-align: left;">Received by: <span class="received-line"></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    @endif
                    </td>
                    @endfor
                    </tr>
                    @endfor
        </table>
    </div>
    @endforeach
</body>

</html>