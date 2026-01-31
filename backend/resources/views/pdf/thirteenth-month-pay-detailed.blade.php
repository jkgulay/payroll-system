<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>13th Month Pay Detailed - {{ $thirteenthMonth->year }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 10mm 50mm 10mm;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .company-name {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .company-address {
            font-size: 9px;
            margin-top: 2px;
        }
        
        .title {
            text-align: center;
            margin: 15px 0 5px 0;
        }
        
        .title h3 {
            margin: 0;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 3px;
        }
        
        .subtitle {
            font-size: 10px;
            margin-top: 3px;
        }
        
        table.main-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        table.main-table th,
        table.main-table td {
            border: 1px solid #000;
            padding: 4px 3px;
            font-size: 8px;
        }
        
        table.main-table thead th {
            background-color: #f5deb3;
            font-weight: bold;
            text-align: center;
        }
        
        table.main-table tbody td {
            text-align: center;
        }
        
        .department-header {
            background-color: #87ceeb !important;
            font-weight: bold;
            text-align: center;
            padding: 6px;
            font-size: 9px;
            text-transform: uppercase;
        }
        
        .text-left {
            text-align: left !important;
            padding-left: 5px !important;
        }
        
        .text-right {
            text-align: right !important;
            padding-right: 5px !important;
        }
        
        .subtotal-row {
            background-color: #ffcc80;
            font-weight: bold;
        }
        
        .subtotal-row td {
            padding: 6px 3px !important;
        }
        
        .page-break {
            page-break-after: always;
        }

        /* Fixed footer signature section on every page */
        .page-footer {
            position: fixed;
            bottom: -40mm;
            left: 0mm;
            right: 0mm;
            height: 40mm;
            text-align: center;
            background-color: white;
        }

        .footer-signature-table {
            width: 100%;
            border: none;
            border-collapse: collapse;
        }

        .footer-signature-table td {
            border: none;
            text-align: center;
            padding: 3px 8px;
            vertical-align: top;
            font-size: 8px;
        }

        .footer-signature-title {
            font-size: 7px;
            font-style: italic;
        }

        .footer-signature-name {
            font-size: 8px;
            font-weight: bold;
            margin-top: 8px;
        }

        .footer-signature-position {
            font-size: 7px;
        }

        .name-col { width: 18%; }
        .rate-col { width: 7%; }
        .days-col { width: 7%; }
        .div12-col { width: 5%; }
        .savings-col { width: 8%; }
        .ca-balance-col { width: 8%; }
        .gross-col { width: 10%; }
        .less-ca-col { width: 9%; }
        .net-col { width: 10%; }
        .signature-col { width: 18%; }
    </style>
</head>
<body>
    <!-- Fixed footer signature section that appears on every page -->
    <div class="page-footer">
        <table class="footer-signature-table">
            <tr>
                <td style="width: 25%;">
                    <div class="footer-signature-title">Prepared by:</div>
                    <div class="footer-signature-name">JERCIEL LAYASAN</div>
                </td>
                <td style="width: 25%;">
                    <div class="footer-signature-title">Checked by:</div>
                    <div class="footer-signature-name">JAMAICA/GING/S3</div>
                </td>
                <td style="width: 25%;">
                    <div class="footer-signature-title">Recommending Approval:</div>
                    <div class="footer-signature-name">ENGR. FRANCIS GIOVANNI C. RIVERA</div>
                </td>
                <td style="width: 25%;">
                    <div class="footer-signature-title">Approved by:</div>
                    <div class="footer-signature-name">ENGR. OSTRIC R. RIVERA JR.</div>
                    <div class="footer-signature-position">Proprietor/Manager</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="company-name">{{ $companyName }}</div>
        <div class="company-address">{{ $companyAddress }}</div>
    </div>
    
    <!-- Title -->
    <div class="title">
        <h3>13TH MONTH PAY</h3>
        <div class="subtitle">For the year {{ $thirteenthMonth->year }}</div>
    </div>
    
    @php
        $departmentCount = 0;
        $totalDepartments = count($employeesByDepartment);
        $grandTotalGross = 0;
        $grandTotalNet = 0;
    @endphp
    
    @foreach($employeesByDepartment as $department => $items)
        @php
            $departmentCount++;
            $departmentTotalGross = 0;
            $departmentTotalNet = 0;
            $rowNumber = 1;
        @endphp
        
        @if($departmentCount > 1)
            <div class="page-break"></div>
            <!-- Repeat Header -->
            <div class="header">
                <div class="company-name">{{ $companyName }}</div>
                <div class="company-address">{{ $companyAddress }}</div>
            </div>
            <div class="title">
                <h3>13TH MONTH PAY</h3>
                <div class="subtitle">For the year {{ $thirteenthMonth->year }}</div>
            </div>
        @endif
        
        <!-- Department Table -->
        <table class="main-table">
            <thead>
                <tr>
                    <th colspan="10" class="department-header">{{ strtoupper($department) }}</th>
                </tr>
                <tr>
                    <th class="name-col">Name</th>
                    <th class="rate-col">Rate</th>
                    <th class="days-col">No. of Days<br>Worked</th>
                    <th class="div12-col">/12</th>
                    <th class="savings-col">Employee's<br>Savings</th>
                    <th class="ca-balance-col">C/A<br>Balance</th>
                    <th class="gross-col">Gross<br>Amount</th>
                    <th class="less-ca-col">Less Cash<br>Advance</th>
                    <th class="net-col">Total Net<br>Amount</th>
                    <th class="signature-col">Signature</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    @php
                        $employee = $item->employee;
                        $lastName = strtoupper($employee->last_name);
                        $firstName = ucwords(strtolower($employee->first_name));
                        $middleInitial = $employee->middle_name ? strtoupper(substr($employee->middle_name, 0, 1)) . '.' : '';
                        $fullName = $lastName . ', ' . $firstName . ($middleInitial ? ' ' . $middleInitial : '');
                        
                        // Get employee data from payroll summaries
                        $employeePayrollData = $payrollSummary[$employee->id] ?? null;
                        
                        // Get rate - from payroll summary, or employee's position rate
                        $rate = $employeePayrollData['rate'] ?? 0;
                        if ($rate <= 0) {
                            $rate = $employee->getBasicSalary() ?? 0;
                        }
                        
                        // Get total days worked from payroll summary
                        $totalDaysWorked = $employeePayrollData['total_days_worked'] ?? 0;
                        
                        // If no days worked found but we have rate and basic salary, calculate estimated days
                        if ($totalDaysWorked <= 0 && $rate > 0 && $item->total_basic_salary > 0) {
                            $totalDaysWorked = $item->total_basic_salary / $rate;
                        }
                        
                        $divBy12 = 12;
                        $employeeSavings = $employeePayrollData['total_savings'] ?? 0;
                        $caBalance = $employeePayrollData['ca_balance'] ?? 0;
                        $grossAmount = $item->total_basic_salary / 12;
                        $lessCashAdvance = $employeePayrollData['total_cash_advance'] ?? 0;
                        $totalNetAmount = $item->net_pay;
                        
                        $departmentTotalGross += $grossAmount;
                        $departmentTotalNet += $totalNetAmount;
                    @endphp
                    <tr>
                        <td class="text-left">{{ $rowNumber }}. {{ $fullName }}</td>
                        <td class="text-right">{{ number_format($rate, 2) }}</td>
                        <td class="text-right">{{ number_format($totalDaysWorked, 1) }}</td>
                        <td>12</td>
                        <td class="text-right">{{ $employeeSavings > 0 ? number_format($employeeSavings, 2) : '0' }}</td>
                        <td class="text-right">{{ $caBalance > 0 ? number_format($caBalance, 2) : '0' }}</td>
                        <td class="text-right">{{ number_format($grossAmount, 2) }}</td>
                        <td class="text-right">{{ $lessCashAdvance > 0 ? number_format($lessCashAdvance, 2) : '-' }}</td>
                        <td class="text-right">{{ number_format($totalNetAmount, 2) }}</td>
                        <td></td>
                    </tr>
                    @php $rowNumber++; @endphp
                @endforeach
                
                <!-- Department Subtotal -->
                <tr class="subtotal-row">
                    <td class="text-left" colspan="6"></td>
                    <td class="text-right">{{ number_format($departmentTotalGross, 2) }}</td>
                    <td class="text-right"></td>
                    <td class="text-right">{{ number_format($departmentTotalNet, 2) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
        
        @php
            $grandTotalGross += $departmentTotalGross;
            $grandTotalNet += $departmentTotalNet;
        @endphp
    @endforeach
</body>
</html>
