<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>13th Month Pay - {{ $thirteenthMonth->year }}</title>
    <style>
        @page {
            margin: 20mm 20mm 55mm 20mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header h2 {
            margin: 0;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .header .address {
            font-size: 9pt;
            margin-top: 3px;
        }

        .title {
            text-align: center;
            margin: 20px 0;
        }

        .title h3 {
            margin: 0;
            font-size: 13pt;
            font-weight: bold;
        }

        .title .subtitle {
            font-size: 10pt;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table.main-table {
            border: 2px solid #000;
        }

        table.main-table thead th {
            padding: 8px 5px;
            font-weight: bold;
            text-align: center;
            border: 1px solid #000;
            background-color: #e0e0e0;
            font-size: 10pt;
        }

        table.main-table tbody td {
            padding: 6px 5px;
            border: 1px solid #000;
            font-size: 9pt;
        }

        .department-header {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
            padding: 8px;
            font-size: 10pt;
            text-transform: uppercase;
        }

        .name-col {
            width: 40%;
            text-align: left;
            padding-left: 10px !important;
        }

        .lastname-col {
            width: 30%;
            text-align: left;
            text-transform: uppercase;
        }

        .firstname-col {
            width: 30%;
            text-align: left;
        }

        .amount-col {
            width: 25%;
            text-align: right;
            padding-right: 10px !important;
        }

        .signature-col {
            width: 35%;
            text-align: center;
        }

        .subtotal-row {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .subtotal-row td {
            padding: 8px 5px !important;
        }

        .page-break {
            page-break-after: always;
        }

        /* Fixed footer signature section on every page */
        .page-footer {
            position: fixed;
            bottom: -45mm;
            left: 0mm;
            right: 0mm;
            height: 45mm;
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
            padding: 3px 5px;
            vertical-align: top;
            font-size: 8pt;
        }

        .footer-signature-title {
            font-size: 8pt;
            margin-bottom: 15px;
        }

        .footer-signature-name {
            font-size: 9pt;
            font-weight: bold;
        }

        .footer-signature-position {
            font-size: 8pt;
        }
    </style>
</head>

<body>
    <!-- Fixed footer signature section that appears on every page -->
    <div class="page-footer">
        <table class="footer-signature-table">
            <tr>
                <td style="width: 33%;">
                    <div class="footer-signature-title">Prepared by:</div>
                    <div class="footer-signature-name">{{ strtoupper($preparedBy) }}</div>
                </td>
                <td style="width: 34%;">
                    <div class="footer-signature-title">Checked by:</div>
                    <div class="footer-signature-name">JAMAICA/GING/S3</div>
                </td>
                <td style="width: 33%;">
                    <div class="footer-signature-title">Approved by:</div>
                    <div class="footer-signature-name">ENGR. OSTRIC R. RIVERA JR.</div>
                    <div class="footer-signature-position">Proprietor/Manager</div>
                </td>
            </tr>
            <tr>
                <td colspan="3" style="height: 10px;"></td>
            </tr>
            <tr>
                <td colspan="3">
                    <div class="footer-signature-title">Recommending Approval:</div>
                    <div class="footer-signature-name">ENGR. FRANCIS GIOVANNI C. RIVERA</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Header -->
    <div class="header">
        <h2>{{ $companyInfo->company_name ?? 'GIOVANNI CONSTRUCTION' }}</h2>
        <div class="address">{{ $companyInfo->address ?? 'Imadejas Subdivision, Capitol Bonbon' }}</div>
    </div>

    <!-- Title -->
    <div class="title">
        <h3>13TH MONTH PAY</h3>
        <div class="subtitle">For the year {{ $thirteenthMonth->year }}</div>
    </div>

    @php
    $departmentCount = 0;
    $totalDepartments = count($employeesByDepartment);
    @endphp

    @foreach($employeesByDepartment as $department => $items)
    @php
    $departmentCount++;
    @endphp

    <!-- Department Table -->
    <table class="main-table">
        <thead>
            <tr>
                <th colspan="3" class="department-header">{{ strtoupper($department) }}</th>
            </tr>
            <tr>
                <th class="lastname-col">Name</th>
                <th class="amount-col">Total</th>
                <th class="signature-col">Signature</th>
            </tr>
        </thead>
        <tbody>
            @php
            $departmentTotal = 0;
            @endphp

            @foreach($items as $item)
            @php
            $employee = $item->employee;
            $lastName = strtoupper($employee->last_name);
            $firstName = ucwords(strtolower($employee->first_name));
            $middleInitial = $employee->middle_name ? strtoupper(substr($employee->middle_name, 0, 1)) . '.' : '';
            $fullName = $lastName . ', ' . $firstName . ($middleInitial ? ' ' . $middleInitial : '');
            $departmentTotal += $item->net_pay;
            @endphp
            <tr>
                <td class="name-col">{{ $fullName }}</td>
                <td class="amount-col">{{ number_format($item->net_pay, 2) }}</td>
                <td class="signature-col"></td>
            </tr>
            @endforeach

            <!-- Department Subtotal -->
            <tr class="subtotal-row">
                <td class="name-col"></td>
                <td class="amount-col">{{ number_format($departmentTotal, 2) }}</td>
                <td class="signature-col"></td>
            </tr>
        </tbody>
    </table>

    @if($departmentCount < $totalDepartments)
        <div class="page-break">
        </div>
        <!-- Repeat Header on new page -->
        <div class="header">
            <h2>{{ $companyName }}</h2>
            <div class="address">{{ $companyAddress }}</div>
        </div>
        <div class="title">
            <h3>13TH MONTH PAY</h3>
            <div class="subtitle">For the year {{ $thirteenthMonth->year }}</div>
        </div>
        @endif
        @endforeach
</body>

</html>