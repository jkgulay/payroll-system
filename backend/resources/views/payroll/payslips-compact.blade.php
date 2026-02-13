<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Payslips - {{ $payroll->period_name }}</title>
    <style>
        @page {
            margin: 8mm 8mm 12mm 8mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            line-height: 1.2;
            color: #000;
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
            border-spacing: 6px 5px;
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
            padding: 4px 5px;
            border-bottom: 1px solid #000;
        }

        .company-name {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .company-address {
            font-size: 6.5px;
        }

        .company-phone {
            font-size: 6.5px;
        }

        /* Main content table */
        .slip-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 7.5px;
        }

        .slip-table td {
            border: 1px solid #000;
            padding: 1px 3px;
        }

        .slip-table .label-col {
            width: 50%;
        }

        .slip-table .value-col {
            width: 25%;
            text-align: right;
        }

        .slip-table .amount-col {
            width: 25%;
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
            text-decoration: underline;
        }

        /* Signature inside payslip */
        .slip-signature {
            font-size: 6.5px;
            padding: 2px 3px;
            width: 100%;
            border-collapse: collapse;
        }

        .slip-signature td {
            padding: 1px 3px;
        }

        .sig-name {
            text-decoration: underline;
            font-weight: bold;
        }

        .received-line {
            border-bottom: 1px solid #000;
            display: inline-block;
            width: 70px;
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
            width: 33.33%;
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

<body>
    @php
        $items = $payroll->items;
        $chunks = $items->chunk(4);
        $periodStart = \Carbon\Carbon::parse($payroll->period_start)->format('F d');
        $periodEnd = \Carbon\Carbon::parse($payroll->period_end)->format('M d, Y');
        $periodDisplay = $periodStart . ' - ' . $periodEnd;
        
        // Signature names from company info or defaults
        $preparedBy = $companyInfo->prepared_by ?? 'JERCIEL';
        $checkedBy = $companyInfo->checked_by ?? 'JAMAICA';
        $recommendedBy = $companyInfo->recommended_by ?? 'ENGR. FGCR';
        $approvedBy = $companyInfo->approved_by ?? 'ENGR. ORR. JR.';
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
                    $idx = $row * 2 + $col;
                    $item = $chunkArray[$idx] ?? null;
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
                        $undertime = $item->undertime_deduction ?? 0;
                        $sssPrem = $item->sss ?? 0;
                        $phicPrem = $item->philhealth ?? 0;
                        $hdmfPrem = $item->pagibig ?? 0;
                        $totalDeductions = $item->total_deductions ?? 0;
                        $netAmount = $item->net_pay ?? 0;
                        
                        // Parse deductions breakdown
                        $ppeBoots = 0;
                        $cashBond = 0;
                        $sssLoan = 0;
                        $hdmfLoan = 0;
                        
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
                                }
                            }
                        }
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
                                <td colspan="2" class="name-value">{{ $item->employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name) }}</td>
                            </tr>
                            <tr>
                                <td>Payroll Period</td>
                                <td colspan="2" style="text-align: right;">{{ $periodDisplay }}</td>
                            </tr>
                            <tr>
                                <td>Daily Rate</td>
                                <td class="value-col">{{ number_format($rate, 2) }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>No. of Days</td>
                                <td class="value-col">{{ rtrim(rtrim(number_format($daysWorked, 2), '0'), '.') }}</td>
                                <td class="amount-col">{{ number_format($basicAmount, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Reg. Overtime</td>
                                <td class="value-col">{{ $regOtHours > 0 ? number_format($regOtHours, 2) : '' }}</td>
                                <td class="amount-col">{{ $regOtPay > 0 ? number_format($regOtPay, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>Sun./Spl. Hol.</td>
                                <td class="value-col">{{ $splHolHours > 0 ? number_format($splHolHours, 2) : '' }}</td>
                                <td class="amount-col">{{ $splHolPay > 0 ? number_format($splHolPay, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td>Water Allowance</td>
                                <td class="value-col">{{ $allowances > 0 ? number_format($allowances, 2) : '' }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>TRIPS/Sal. Adj.</td>
                                <td class="value-col">{{ $salaryAdj != 0 ? number_format(abs($salaryAdj), 2) : '' }}</td>
                                <td class="amount-col">{{ ($allowances > 0 || $salaryAdj > 0) ? number_format($allowances + max(0, $salaryAdj), 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td class="bold">GROSS AMOUNT</td>
                                <td class="value-col"></td>
                                <td class="amount-col bold">{{ number_format($grossAmount, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="section-label">LESS DEDUCTIONS:</td>
                            </tr>
                            <tr>
                                <td>CASH ADVANCE</td>
                                <td class="value-col">{{ $cashAdvance > 0 ? number_format($cashAdvance, 2) : '' }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>PPE/safety boots</td>
                                <td class="value-col">{{ $ppeBoots > 0 ? number_format($ppeBoots, 2) : '' }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>UNDERTIME</td>
                                <td class="value-col">{{ $undertime > 0 ? number_format($undertime, 2) : '' }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>CASH BOND</td>
                                <td class="value-col">{{ $cashBond > 0 ? number_format($cashBond, 2) : '' }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>SSS Prem.</td>
                                <td class="value-col">{{ $sssPrem > 0 ? number_format($sssPrem, 2) : '' }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>PHIC Prem.</td>
                                <td class="value-col">{{ $phicPrem > 0 ? number_format($phicPrem, 2) : '' }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>HDMF Prem.</td>
                                <td class="value-col">{{ $hdmfPrem > 0 ? number_format($hdmfPrem, 2) : '' }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>SSS Loan</td>
                                <td class="value-col">{{ $sssLoan > 0 ? number_format($sssLoan, 2) : '' }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>HDMF Loan</td>
                                <td class="value-col">{{ $hdmfLoan > 0 ? number_format($hdmfLoan, 2) : '' }}</td>
                                <td class="amount-col"></td>
                            </tr>
                            <tr>
                                <td>Other Deduction</td>
                                <td class="value-col"></td>
                                <td class="amount-col">{{ $totalDeductions > 0 ? number_format($totalDeductions, 2) : '' }}</td>
                            </tr>
                            <tr>
                                <td class="bold">NET AMOUNT</td>
                                <td class="value-col"></td>
                                <td class="amount-col bold">{{ number_format($netAmount, 2) }}</td>
                            </tr>
                        </table>
                        
                        <!-- Payslip Signature -->
                        <table class="slip-signature">
                            <tr>
                                <td>Prepared by: <span class="sig-name">{{ $preparedBy }}</span></td>
                                <td style="text-align: right;">Checked by: <span class="sig-name">{{ $checkedBy }}</span></td>
                            </tr>
                            <tr>
                                <td>Recommended by: <span class="sig-name">{{ $recommendedBy }}</span></td>
                                <td style="text-align: right;">Approved by: <span class="sig-name">{{ $approvedBy }}</span></td>
                            </tr>
                            <tr>
                                <td colspan="2">Received by: <span class="received-line"></span></td>
                            </tr>
                        </table>
                    </div>
                    @endif
                </td>
                @endfor
            </tr>
            @endfor
        </table>

        <!-- Page Footer Signature Section (same as payroll register) -->
        <div class="page-footer">
            <table class="footer-sig-table">
                <tr>
                    <td style="text-align: left;">
                        <div class="footer-sig-label">PREPARED BY:</div>
                    </td>
                    <td style="text-align: center;">
                        <div class="footer-sig-label">CHECKED & VERIFIED:</div>
                    </td>
                    <td style="text-align: right;">
                        <div class="footer-sig-label">RECOMMENDED BY:</div>
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left;">
                        <div class="footer-sig-name">JERCIEL LAYASAN</div>
                    </td>
                    <td style="text-align: center;">
                        <div class="footer-sig-name">SAIRAH JENITA</div>
                    </td>
                    <td style="text-align: right;">
                        <div class="footer-sig-name">ENGR. FRANCIS GIOVANNI C. RIVERA</div>
                    </td>
                </tr>
                <tr>
                    <td style="text-align: left; padding-top: 8px;">
                        <div class="footer-sig-name">JAMAICA CRISTEL MAE SUGABO</div>
                    </td>
                    <td style="text-align: center; padding-top: 8px;">
                        <div class="footer-sig-name">ENGR. ELISA MAY PARCON</div>
                    </td>
                    <td style="text-align: right; padding-top: 8px;">
                        <div class="footer-sig-name">ENGR. OSTRIC C. RIVERA, III</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    @endforeach
</body>

</html>
