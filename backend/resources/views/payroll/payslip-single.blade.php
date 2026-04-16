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
        font-size: 10px;
        line-height: 1.4;
        color: #000;
        }

        body.a4-scaled-layout {
        transform: scale(0.97);
        transform-origin: top left;
        width: 103.0928%;
        }

        .page-wrapper {
        width: 100%;
        }

        .payslip {
        border: 1px solid #000;
        }

        .slip-header {
        text-align: center;
        padding: 10px 14px;
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

        .slip-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
        }

        .slip-table td {
        padding: 4px 12px;
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
        padding-top: 6px !important;
        }

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
        padding: 3px !important;
        }

        .slip-signature {
        padding: 10px 12px;
        border-top: 1px solid #000;
        font-size: 9px;
        }

        .slip-signature table {
        width: 100%;
        border-collapse: collapse;
        }

        .slip-signature td {
        padding: 3px 0;
        }

        .sig-name {
        text-decoration: underline;
        font-weight: bold;
        font-style: italic;
        }

        .received-line {
        border-bottom: 1px solid #000;
        display: inline-block;
        width: 160px;
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
            </div>

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
                    <td class="value-col">{{ number_format($rate, 2) }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr>
                    <td>No. of Days</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ rtrim(rtrim(number_format($daysWorked, 2), '0'), '.') }}</td>
                    <td class="amount-col">{{ number_format($basicAmount, 2) }}</td>
                </tr>
                <tr>
                    <td>Reg. Overtime</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $regOtHours > 0 ? number_format($regOtHours, 2) : '-' }}</td>
                    <td class="amount-col">{{ $regOtPay > 0 ? number_format($regOtPay, 2) : '' }}</td>
                </tr>
                <tr>
                    <td>Sun./Spl. Hol.</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $splHolHours > 0 ? number_format($splHolHours, 2) : '-' }}</td>
                    <td class="amount-col">{{ $splHolPay > 0 ? number_format($splHolPay, 2) : '' }}</td>
                </tr>
                <tr>
                    <td>Allowance</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $allowances > 0 ? number_format($allowances, 2) : '-' }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr>
                    <td>TRIPS/Sal. Adj.</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $salaryAdj != 0 ? number_format(abs($salaryAdj), 2) : '-' }}</td>
                    <td class="amount-col">{{ ($allowances > 0 || $salaryAdj > 0) ? number_format($allowances + max(0, $salaryAdj), 2) : '' }}</td>
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
                    <td class="amount-col bold">{{ number_format($grossAmount, 2) }}</td>
                </tr>
                <tr class="space-row">
                    <td colspan="4"></td>
                </tr>
                <tr>
                    <td colspan="4" class="section-label">LESS DEDUCTION:</td>
                </tr>
                <tr>
                    <td>CASH ADVANCE</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $cashAdvance > 0 ? number_format($cashAdvance, 2) : '-' }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr>
                    <td>PPE/safety boots</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $ppeBoots > 0 ? number_format($ppeBoots, 2) : '-' }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr>
                    <td>UNDERTIME</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $undertime > 0 ? number_format($undertime, 2) : '-' }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr>
                    <td>CASH BOND</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $cashBond > 0 ? number_format($cashBond, 2) : '-' }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr>
                    <td>SSS Prem.</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $sssPrem > 0 ? number_format($sssPrem, 2) : '-' }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr>
                    <td>PHIC Prem.</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $phicPrem > 0 ? number_format($phicPrem, 2) : '-' }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr>
                    <td>HDMF Prem.</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $hdmfPrem > 0 ? number_format($hdmfPrem, 2) : '-' }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr>
                    <td>SSS Loan</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $sssLoan > 0 ? number_format($sssLoan, 2) : '-' }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr>
                    <td>HDMF Loan</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">{{ $hdmfLoan > 0 ? number_format($hdmfLoan, 2) : '-' }}</td>
                    <td class="amount-col"></td>
                </tr>
                <tr class="separator-line">
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="line-right"></td>
                </tr>
                <tr>
                    <td>Total Deductions</td>
                    <td class="colon-col">:</td>
                    <td class="value-col">-</td>
                    <td class="amount-col">{{ $totalDeductions > 0 ? number_format($totalDeductions, 2) : '' }}</td>
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
                    <td class="amount-col bold">{{ number_format($netAmount, 2) }}</td>
                </tr>
            </table>

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
    </div>
    @else
    <p>No payroll item found for this employee.</p>
    @endif
</body>

</html>