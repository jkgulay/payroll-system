<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $mealAllowance->title }}</title>
    <style>
        @page {
            margin: 15mm;
        }
        
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #000;
        }
        
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .header h2 {
            margin: 0;
            font-size: 12pt;
            font-weight: bold;
        }
        
        .header .date {
            font-size: 8pt;
            margin-top: 2px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        
        table thead {
            background-color: #d4a574;
        }
        
        table thead th {
            padding: 6px 4px;
            font-weight: bold;
            text-align: center;
            border: 1px solid #000;
            font-size: 9pt;
        }
        
        table tbody td {
            padding: 4px;
            border: 1px solid #ccc;
            font-size: 8pt;
        }
        
        .group-header {
            background-color: #3d4f5c;
            color: #fff;
            font-weight: bold;
            text-align: center;
            padding: 5px;
            font-size: 9pt;
        }
        
        .number-col {
            width: 30px;
            text-align: center;
        }
        
        .name-col {
            width: 200px;
        }
        
        .days-col {
            width: 60px;
            text-align: center;
        }
        
        .amount-col {
            width: 80px;
            text-align: right;
        }
        
        .total-col {
            width: 100px;
            text-align: right;
        }
        
        .signature-col {
            width: 120px;
        }
        
        .sub-info {
            font-size: 7pt;
            color: #666;
            font-style: italic;
        }
        
        .grand-total-row {
            background-color: #d4a574;
            font-weight: bold;
        }
        
        .grand-total-row td {
            padding: 8px 4px;
            border: 1px solid #000;
            font-size: 10pt;
        }
        
        .signatures {
            margin-top: 30px;
            width: 100%;
        }
        
        .signatures table {
            width: 100%;
            border: none;
        }
        
        .signatures td {
            border: none;
            padding: 20px 10px 0;
            text-align: center;
            vertical-align: top;
            font-size: 8pt;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 30px;
            padding-top: 2px;
            font-weight: bold;
        }
        
        .signature-title {
            font-size: 7pt;
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>{{ $mealAllowance->title }}</h2>
        <div class="date">{{ $mealAllowance->period_start->format('F d, Y') }}</div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th class="name-col">Name</th>
                <th class="days-col">No. of Days</th>
                <th class="amount-col">Amount per day</th>
                <th class="total-col">Total</th>
                <th class="signature-col">Signature</th>
            </tr>
        </thead>
        <tbody>
            @php $counter = 1; @endphp
            @foreach($groupedItems as $position => $items)
                <tr>
                    <td colspan="5" class="group-header">{{ strtoupper($position) }}</td>
                </tr>
                @foreach($items as $item)
                    <tr>
                        <td class="name-col">
                            <strong>{{ $counter }}. {{ $item->employee_name }}</strong>
                            @if($item->position_code)
                                <span style="margin-left: 10px;">{{ $item->position_code }}</span>
                            @endif
                            @if($item->employee && $item->employee->date_hired)
                                <div class="sub-info">less: absent {{ $item->employee->date_hired->format('m/d/y') }}</div>
                            @endif
                        </td>
                        <td class="days-col">{{ $item->no_of_days }}</td>
                        <td class="amount-col">{{ number_format($item->amount_per_day, 2) }} /day</td>
                        <td class="total-col">{{ number_format($item->total_amount, 2) }}</td>
                        <td class="signature-col"></td>
                    </tr>
                    @php $counter++; @endphp
                @endforeach
                <tr>
                    <td colspan="5" style="height: 5px; border: none;"></td>
                </tr>
            @endforeach
            
            <tr class="grand-total-row">
                <td colspan="3" style="text-align: right; padding-right: 20px;">Grand Total</td>
                <td class="total-col">{{ number_format($grandTotal, 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    
    <div class="signatures">
        <table>
            <tr>
                <td style="width: 33%;">
                    <div class="signature-line">{{ $mealAllowance->prepared_by_name ?? '' }}</div>
                    <div class="signature-title">Prepared by:</div>
                </td>
                <td style="width: 34%;">
                    <div class="signature-line">{{ $mealAllowance->checked_by_name ?? '' }}</div>
                    <div class="signature-title">Checked by:</div>
                </td>
                <td style="width: 33%;">
                    <div class="signature-line">{{ $mealAllowance->verified_by_name ?? '' }}</div>
                    <div class="signature-title">Checked & Verified by:</div>
                </td>
            </tr>
            <tr>
                <td style="width: 50%;">
                    <div class="signature-line">{{ $mealAllowance->recommended_by_name ?? '' }}</div>
                    <div class="signature-title">Recommended by:</div>
                </td>
                <td colspan="2" style="width: 50%;">
                    <div class="signature-line">{{ $mealAllowance->approved_by_name ?? '' }}</div>
                    <div class="signature-title">Approved by:</div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
