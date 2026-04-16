<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $payroll->period_name }}</title>
    @php
    $pageScaleValue = (float) ($pageScale ?? 1);
    $isA4ScaledLayout = $pageScaleValue < 0.999;
        @endphp
        <style>
        @page {
        margin: {{ $pageMarginCss ?? '6mm 10mm 6mm 8mm' }};
        size: {{ $pageSizeCss ?? '8.5in 13in' }};
        }

        * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
        }

        body {
        font-family: Arial, sans-serif;
        font-size: 10px;
        line-height: 1.35;
        color: #000;
        }

        body.a4-scaled-layout {
        transform: scale({{ $pageScale ?? 1 }});
        transform-origin: top left;
        width: {{ $pageWidthPercent ?? 100 }}%;
        }

        .page-wrapper {
        width: 100%;
        }

        .payslip {
        border: 1px solid #000;
        }

        .slip-header {
        text-align: center;
        padding: 10px 14px 8px;
        border-bottom: 1px solid #000;
        }

        .company-name {
        font-size: 14px;
        font-weight: bold;
        letter-spacing: 0.5px;
        }

        .company-address {
        font-size: 9px;
        margin-top: 2px;
        }

        .company-phone {
        font-size: 9px;
        }

        .slip-title {
        margin-top: 6px;
        font-size: 10px;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
        }

        .meta-table {
        width: 100%;
        border-collapse: collapse;
        border-bottom: 1px solid #000;
        }

        .meta-table td {
        padding: 5px 10px;
        vertical-align: middle;
        border-right: 1px solid #000;
        }

        .meta-table td:last-child {
        border-right: none;
        }

        .meta-label {
        width: 16%;
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        color: #333;
        }

        .meta-value {
        width: 34%;
        font-size: 10px;
        font-weight: 600;
        }

        .meta-value.right {
        text-align: right;
        }

        .detail-grid {
        width: 100%;
        border-collapse: collapse;
        }

        .detail-col {
        width: 50%;
        vertical-align: top;
        border-right: 1px solid #000;
        padding: 0;
        }

        .detail-col:last-child {
        border-right: none;
        }

        .section-heading {
        padding: 6px 10px;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
        border-bottom: 1px solid #000;
        background: #f4f4f4;
        }

        .detail-table {
        width: 100%;
        border-collapse: collapse;
        }

        .detail-table th,
        .detail-table td {
        padding: 4px 8px;
        border-bottom: 1px solid #ddd;
        }

        .detail-table th {
        font-size: 8.5px;
        text-transform: uppercase;
        text-align: left;
        color: #333;
        background: #fafafa;
        }

        .detail-table .text-right {
        text-align: right;
        white-space: nowrap;
        }

        .detail-table .muted {
        color: #666;
        font-size: 9px;
        }

        .detail-table .total-row td {
        border-top: 1px solid #000;
        border-bottom: none;
        font-weight: bold;
        padding-top: 6px;
        padding-bottom: 6px;
        background: #f8f8f8;
        }

        .net-table {
        width: 100%;
        border-collapse: collapse;
        border-top: 1px solid #000;
        }

        .net-table td {
        padding: 7px 10px;
        font-weight: bold;
        font-size: 11px;
        text-transform: uppercase;
        }

        .net-table .net-label {
        width: 72%;
        }

        .net-table .net-amount {
        width: 28%;
        text-align: right;
        }

        .slip-signature {
        padding: 10px 10px 12px;
        border-top: 1px solid #000;
        font-size: 9px;
        }

        .slip-signature table {
        width: 100%;
        border-collapse: collapse;
        }

        .slip-signature td {
        width: 25%;
        padding: 3px 4px;
        vertical-align: top;
        }

        .sig-label {
        font-size: 8px;
        color: #444;
        text-transform: uppercase;
        margin-bottom: 14px;
        }

        .sig-name {
        display: inline-block;
        min-width: 120px;
        border-top: 1px solid #000;
        padding-top: 3px;
        font-weight: bold;
        font-style: italic;
        }

        .received-row td {
        padding-top: 10px;
        }

        .received-line {
        display: inline-block;
        width: 220px;
        border-bottom: 1px solid #000;
        margin-left: 6px;
        }
        </style>
</head>

<body class="{{ $isA4ScaledLayout ? 'a4-scaled-layout' : '' }}">
    @php
    $item = $item ?? $payroll->items->first();

    $periodStart = \Carbon\Carbon::parse($payroll->period_start)->format('F d');
    $periodEnd = \Carbon\Carbon::parse($payroll->period_end)->format('M d, Y');
    $periodDisplay = $periodStart . ' - ' . $periodEnd;

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

    @if($item)
    @php
    $employee = $item->employee;
    $employeeName = $item->employee?->full_name ?? trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
    $employeeNumber = $employee?->employee_number ?? '-';
    $projectName = trim((string) ($employee?->project?->name ?? $employee?->department ?? ''));
    $projectName = $projectName !== '' ? $projectName : 'Unassigned Project';
    $positionName = trim((string) ($employee?->positionRate?->position_name ?? $employee?->position ?? ''));
    $positionName = $positionName !== '' ? $positionName : '-';

    $rate = $item->effective_rate ?? $item->rate ?? 0;
    $daysWorked = $item->days_worked ?? 0;
    $daysWorkedDisplay = rtrim(rtrim(number_format($daysWorked, 2), '0'), '.');
    $basicAmount = $rate * $daysWorked;

    $regOtHours = $item->regular_ot_hours ?? 0;
    $regOtPay = $item->regular_ot_pay ?? 0;
    $splHolHours = $item->special_ot_hours ?? 0;
    $splHolPay = $item->special_ot_pay ?? 0;
    $allowances = $item->other_allowances ?? 0;
    $salaryAdj = $item->salary_adjustment ?? 0;
    $allowancesAndPositiveAdj = $allowances + max(0, $salaryAdj);
    $grossAmount = $item->gross_pay ?? 0;

    $cashAdvance = $item->cash_advance ?? 0;
    $undertime = $item->undertime_deduction ?? 0;
    $sssPrem = $item->sss ?? 0;
    $phicPrem = $item->philhealth ?? 0;
    $hdmfPrem = $item->pagibig ?? 0;
    $totalDeductions = $item->total_deductions ?? 0;
    $netAmount = $item->net_pay ?? 0;

    $ppeBoots = 0;
    $cashBond = 0;
    $sssLoan = 0;
    $hdmfLoan = 0;
    $otherDeduction = 0;

    if (!empty($item->deductions_breakdown) && is_array($item->deductions_breakdown)) {
    foreach ($item->deductions_breakdown as $deduction) {
    $deductionName = strtolower($deduction['name'] ?? $deduction['type'] ?? '');
    $deductionAmount = $deduction['amount'] ?? 0;

    if (str_contains($deductionName, 'ppe') || str_contains($deductionName, 'safety') || str_contains($deductionName, 'boots')) {
    $ppeBoots += $deductionAmount;
    } elseif (str_contains($deductionName, 'cash bond')) {
    $cashBond += $deductionAmount;
    } elseif (str_contains($deductionName, 'sss loan')) {
    $sssLoan += $deductionAmount;
    } elseif (str_contains($deductionName, 'hdmf loan') || str_contains($deductionName, 'pag-ibig loan') || str_contains($deductionName, 'pagibig loan')) {
    $hdmfLoan += $deductionAmount;
    } else {
    $otherDeduction += $deductionAmount;
    }
    }
    }
    @endphp

    <div class="page-wrapper">
        <div class="payslip">
            <div class="slip-header">
                <div class="company-name">{{ strtoupper($companyInfo->company_name ?? 'GIOVANNI CONSTRUCTION') }}</div>
                <div class="company-address">{{ $companyInfo->address ?? '863 Capitol-Bonbon Road, Imadejas Subd., Butuan City' }}</div>
                <div class="company-phone">Tel. No. {{ $companyInfo->phone ?? '(085) 817-1833' }}</div>
                <div class="slip-title">Employee Payslip</div>
            </div>

            <table class="meta-table">
                <tr>
                    <td class="meta-label">Employee</td>
                    <td class="meta-value">{{ $employeeName !== '' ? $employeeName : '-' }}</td>
                    <td class="meta-label">Employee No.</td>
                    <td class="meta-value right">{{ $employeeNumber }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Payroll Period</td>
                    <td class="meta-value">{{ $periodDisplay }}</td>
                    <td class="meta-label">Payroll No.</td>
                    <td class="meta-value right">{{ $payroll->payroll_number ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Project</td>
                    <td class="meta-value">{{ $projectName }}</td>
                    <td class="meta-label">Position</td>
                    <td class="meta-value right">{{ $positionName }}</td>
                </tr>
            </table>

            <table class="detail-grid">
                <tr>
                    <td class="detail-col">
                        <div class="section-heading">Earnings</div>
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-right">Units/Rate</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Basic Pay</td>
                                    <td class="text-right muted">{{ number_format($rate, 2) }} x {{ $daysWorkedDisplay }}</td>
                                    <td class="text-right">{{ number_format($basicAmount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Reg. Overtime</td>
                                    <td class="text-right muted">{{ $regOtHours > 0 ? number_format($regOtHours, 2) : '-' }}</td>
                                    <td class="text-right">{{ $regOtPay > 0 ? number_format($regOtPay, 2) : '' }}</td>
                                </tr>
                                <tr>
                                    <td>Sun./Spl. Hol.</td>
                                    <td class="text-right muted">{{ $splHolHours > 0 ? number_format($splHolHours, 2) : '-' }}</td>
                                    <td class="text-right">{{ $splHolPay > 0 ? number_format($splHolPay, 2) : '' }}</td>
                                </tr>
                                <tr>
                                    <td>Allowance</td>
                                    <td class="text-right muted">{{ $allowances > 0 ? number_format($allowances, 2) : '-' }}</td>
                                    <td class="text-right"></td>
                                </tr>
                                <tr>
                                    <td>TRIPS/Sal. Adj.</td>
                                    <td class="text-right muted">{{ $salaryAdj != 0 ? number_format(abs($salaryAdj), 2) : '-' }}</td>
                                    <td class="text-right">{{ ($allowances > 0 || $salaryAdj > 0) ? number_format($allowancesAndPositiveAdj, 2) : '' }}</td>
                                </tr>
                                <tr class="total-row">
                                    <td colspan="2">Gross Amount</td>
                                    <td class="text-right">{{ number_format($grossAmount, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>

                    <td class="detail-col">
                        <div class="section-heading">Deductions</div>
                        <table class="detail-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Cash Advance</td>
                                    <td class="text-right">{{ $cashAdvance > 0 ? number_format($cashAdvance, 2) : '-' }}</td>
                                </tr>
                                <tr>
                                    <td>PPE/Safety Boots</td>
                                    <td class="text-right">{{ $ppeBoots > 0 ? number_format($ppeBoots, 2) : '-' }}</td>
                                </tr>
                                <tr>
                                    <td>Undertime</td>
                                    <td class="text-right">{{ $undertime > 0 ? number_format($undertime, 2) : '-' }}</td>
                                </tr>
                                <tr>
                                    <td>Cash Bond</td>
                                    <td class="text-right">{{ $cashBond > 0 ? number_format($cashBond, 2) : '-' }}</td>
                                </tr>
                                <tr>
                                    <td>SSS Prem.</td>
                                    <td class="text-right">{{ $sssPrem > 0 ? number_format($sssPrem, 2) : '-' }}</td>
                                </tr>
                                <tr>
                                    <td>PHIC Prem.</td>
                                    <td class="text-right">{{ $phicPrem > 0 ? number_format($phicPrem, 2) : '-' }}</td>
                                </tr>
                                <tr>
                                    <td>HDMF Prem.</td>
                                    <td class="text-right">{{ $hdmfPrem > 0 ? number_format($hdmfPrem, 2) : '-' }}</td>
                                </tr>
                                <tr>
                                    <td>SSS Loan</td>
                                    <td class="text-right">{{ $sssLoan > 0 ? number_format($sssLoan, 2) : '-' }}</td>
                                </tr>
                                <tr>
                                    <td>HDMF Loan</td>
                                    <td class="text-right">{{ $hdmfLoan > 0 ? number_format($hdmfLoan, 2) : '-' }}</td>
                                </tr>
                                <tr>
                                    <td>Other Deductions</td>
                                    <td class="text-right">{{ $otherDeduction > 0 ? number_format($otherDeduction, 2) : '-' }}</td>
                                </tr>
                                <tr class="total-row">
                                    <td>Total Deductions</td>
                                    <td class="text-right">{{ $totalDeductions > 0 ? number_format($totalDeductions, 2) : '' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>

            <table class="net-table">
                <tr>
                    <td class="net-label">Net Amount</td>
                    <td class="net-amount">{{ number_format($netAmount, 2) }}</td>
                </tr>
            </table>

            <div class="slip-signature">
                <table>
                    <tr>
                        <td>
                            <div class="sig-label">Prepared by</div>
                            <span class="sig-name">{{ $preparedBy }}</span>
                        </td>
                        <td>
                            <div class="sig-label">Checked by</div>
                            <span class="sig-name">{{ $checkedBy }}</span>
                        </td>
                        <td>
                            <div class="sig-label">Recommended by</div>
                            <span class="sig-name">{{ $recommendedBy }}</span>
                        </td>
                        <td>
                            <div class="sig-label">Approved by</div>
                            <span class="sig-name">{{ $approvedBy }}</span>
                        </td>
                    </tr>
                    <tr class="received-row">
                        <td colspan="4">
                            Received by: <span class="received-line"></span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    @else
    <p>No payroll item found for this employee.</p>
    @endif
</body>

</html>