<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>13th Month Pay - {{ $thirteenthMonth->year }}</title>
    <style>
        @page {
            margin: 20mm;
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
        
        .footer {
            margin-top: 40px;
        }
        
        .signature-section {
            display: table;
            width: 100%;
            margin-top: 30px;
        }
        
        .signature-box {
            display: table-cell;
            width: 33%;
            text-align: center;
            padding: 10px;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
            font-weight: bold;
        }
        
        .signature-title {
            font-size: 9pt;
            margin-top: 3px;
        }
        
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h2>{{ $companyName }}</h2>
        <div class="address">{{ $companyAddress }}</div>
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
            <div class="page-break"></div>
        @endif
    @endforeach
    
    <!-- Footer with Signatures -->
    <div class="footer">
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-line">{{ strtoupper($preparedBy) }}</div>
                <div class="signature-title">Prepared by:</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">JAMAICA/GING/S3</div>
                <div class="signature-title">Checked by:</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">ENGR. OSTRIC R. RIVERA JR.</div>
                <div class="signature-title">Approved by:</div>
                <div class="signature-title" style="margin-top: 0;">Proprietor/Manager</div>
            </div>
        </div>
        
        <div style="margin-top: 30px; text-align: left;">
            <div style="display: inline-block; width: 48%;">
                <strong>Recommending Approval:</strong><br>
                <div style="margin-top: 40px; border-top: 1px solid #000; width: 250px; text-align: center; padding-top: 5px;">
                    <strong>ENGR. FRANCIS GIOVANNI C. RIVERA</strong>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
