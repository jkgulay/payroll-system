<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AuditLogExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $logs;

    public function __construct($logs)
    {
        $this->logs = $logs;
    }

    public function collection()
    {
        return $this->logs;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Date & Time',
            'User',
            'Email',
            'Module',
            'Action',
            'Description',
            'IP Address',
            'User Agent',
            'Old Values',
            'New Values',
        ];
    }

    public function map($log): array
    {
        return [
            $log->id,
            $log->created_at->format('Y-m-d H:i:s'),
            $log->user ? $log->user->name : 'System',
            $log->user ? $log->user->email : 'N/A',
            ucfirst(str_replace('_', ' ', $log->module)),
            ucfirst(str_replace('_', ' ', $log->action)),
            $log->description,
            $log->ip_address ?? 'N/A',
            $log->user_agent ?? 'N/A',
            $log->old_values ? json_encode($log->old_values) : 'N/A',
            $log->new_values ? json_encode($log->new_values) : 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4CAF50'],
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF']],
            ],
        ];
    }

    public function title(): string
    {
        return 'Audit Logs';
    }
}
