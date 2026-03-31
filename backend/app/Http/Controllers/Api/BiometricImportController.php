<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StaffImportService;
use App\Services\PunchRecordImportService;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BiometricImportController extends Controller
{
    protected $staffImportService;
    protected $punchRecordImportService;

    public function __construct(StaffImportService $staffImportService, PunchRecordImportService $punchRecordImportService)
    {
        $this->staffImportService = $staffImportService;
        $this->punchRecordImportService = $punchRecordImportService;
        $this->middleware('role:admin,hr,payrollist');
    }

    /**
     * Send a Server-Sent Events progress line.
     */
    private function sendProgress(string $phase, int $current, int $total, string $detail = ''): void
    {
        $pct = $total > 0 ? round(($current / $total) * 100) : 0;
        $this->sendMessage([
            'type'    => 'progress',
            'phase'   => $phase,
            'current' => $current,
            'total'   => $total,
            'percent' => $pct,
            'detail'  => $detail,
        ]);
    }

    /**
     * Send one NDJSON message line and flush output buffers.
     */
    private function sendMessage(array $payload): void
    {
        echo json_encode($payload) . "\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Import staff information from biometric device export
     * Uses streaming response for real-time progress
     */
    public function importStaffInformation(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '1024M');

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'default_position' => 'nullable|string',
            'default_project_id' => 'nullable|integer|exists:projects,id',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileExtension = strtolower($file->getClientOriginalExtension());
        $fileType = $fileExtension === 'csv' ? 'csv' : 'excel';
        $filePath = $file->storeAs('biometric_imports', 'staff_import_' . time() . '.' . $file->getClientOriginalExtension());
        $fullPath = Storage::path($filePath);

        $defaults = [
            'position' => $validated['default_position'] ?? null,
            'project_id' => $validated['default_project_id'] ?? null,
        ];

        $userId = $request->user()->id;
        $ip = $request->ip();
        $ua = $request->userAgent();

        return new StreamedResponse(function () use ($fullPath, $filePath, $defaults, $originalName, $userId, $ip, $ua, $fileType) {
            try {
                // Phase 1: Parsing file
                $this->sendProgress(
                    'parsing',
                    0,
                    1,
                    $fileType === 'csv' ? 'Reading CSV file...' : 'Reading Excel file...'
                );
                $staffData = $this->parseImportFile($fullPath);
                $this->sendProgress('parsing', 1, 1, 'File parsed');

                if (empty($staffData)) {
                    $this->sendMessage([
                        'type' => 'error',
                        'message' => 'No valid data found in file',
                    ]);
                    return;
                }

                // Phase 2: Processing records with real-time progress
                $total = count($staffData);
                $this->sendProgress('processing', 0, $total, "Processing {$total} records...");

                $result = $this->staffImportService->importStaffInformationWithProgress(
                    $staffData,
                    $defaults,
                    function ($current, $total, $detail) {
                        $this->sendProgress('processing', $current, $total, $detail);
                    }
                );

                // Log the import
                AuditLog::create([
                    'user_id' => $userId,
                    'module' => 'employees',
                    'action' => 'staff_import',
                    'description' => 'Staff information imported from biometric device export',
                    'old_values' => null,
                    'new_values' => [
                        'file' => $originalName,
                        'imported' => $result['imported'],
                        'updated' => $result['updated'],
                        'skipped' => $result['skipped'],
                        'failed' => $result['failed'],
                    ],
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                ]);

                Storage::delete($filePath);

                $this->sendMessage([
                    'type' => 'complete',
                    'message' => 'Staff information imported successfully',
                    'imported' => $result['imported'],
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                    'failed' => $result['failed'],
                    'errors' => $result['error_details'] ?? [],
                ]);
            } catch (\Throwable $e) {
                Log::error('Staff import failed: ' . $e->getMessage());
                Storage::delete($filePath);
                $this->sendMessage([
                    'type' => 'error',
                    'message' => 'Failed to import staff information: ' . $e->getMessage(),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Import punch records (attendance) from biometric device export
     * Uses streaming response for real-time progress
     */
    public function importPunchRecords(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '1024M');

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $fileExtension = strtolower($file->getClientOriginalExtension());
        $fileType = $fileExtension === 'csv' ? 'csv' : 'excel';
        $filePath = $file->storeAs('biometric_imports', 'punch_import_' . time() . '.' . $file->getClientOriginalExtension());
        $fullPath = Storage::path($filePath);

        $userId = $request->user()->id;
        $ip = $request->ip();
        $ua = $request->userAgent();

        return new StreamedResponse(function () use ($fullPath, $filePath, $originalName, $userId, $ip, $ua, $fileType) {
            try {
                // Phase 1: Parsing file
                $this->sendProgress(
                    'parsing',
                    0,
                    1,
                    $fileType === 'csv' ? 'Reading CSV file...' : 'Reading Excel file...'
                );
                $punchData = $this->parseImportFile($fullPath);
                $this->sendProgress('parsing', 1, 1, 'File parsed');

                if (empty($punchData)) {
                    $this->sendMessage([
                        'type' => 'error',
                        'message' => 'No valid data found in file',
                    ]);
                    return;
                }

                // Phase 2: Processing records with real-time progress
                $total = count($punchData);
                $this->sendProgress('processing', 0, $total, "Processing {$total} punch records...");

                $result = $this->punchRecordImportService->importPunchRecordsWithProgress(
                    $punchData,
                    function ($current, $total, $detail) {
                        $this->sendProgress('processing', $current, $total, $detail);
                    }
                );

                // Log the import
                AuditLog::create([
                    'user_id' => $userId,
                    'module' => 'attendance',
                    'action' => 'punch_record_import',
                    'description' => 'Punch records imported from biometric device export',
                    'old_values' => null,
                    'new_values' => [
                        'file' => $originalName,
                        'imported' => $result['imported'],
                        'updated' => $result['updated'],
                        'skipped' => $result['skipped'],
                        'failed' => $result['failed'],
                    ],
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                ]);

                Storage::delete($filePath);

                $this->sendMessage([
                    'type' => 'complete',
                    'message' => 'Punch records imported successfully',
                    'imported' => $result['imported'],
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                    'failed' => $result['failed'],
                    'errors' => $result['error_details'] ?? [],
                ]);
            } catch (\Throwable $e) {
                Log::error('Punch record import failed: ' . $e->getMessage());
                Storage::delete($filePath);
                $this->sendMessage([
                    'type' => 'error',
                    'message' => 'Failed to import punch records: ' . $e->getMessage(),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Parse import file and return array of data.
     */
    protected function parseImportFile(string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return $this->parseCsvFile($filePath);
        }

        return $this->parseExcelFile($filePath);
    }

    /**
     * Parse CSV file quickly for import processing.
     */
    protected function parseCsvFile(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Failed to open CSV file');
        }

        try {
            $headers = null;
            $data = [];

            while (($row = fgetcsv($handle)) !== false) {
                if ($headers === null) {
                    $headers = array_map(function ($header) {
                        $header = (string) $header;
                        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
                        return trim($header);
                    }, $row);
                    continue;
                }

                $hasValue = false;
                foreach ($row as $cellValue) {
                    if (trim((string) $cellValue) !== '') {
                        $hasValue = true;
                        break;
                    }
                }

                if (!$hasValue) {
                    continue;
                }

                $rowData = [];
                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $value = $row[$index] ?? null;
                    $value = is_string($value) ? trim($value) : $value;
                    $rowData[$header] = ($value !== null && $value !== '') ? (string) $value : null;
                }

                if (!empty($rowData)) {
                    $data[] = $rowData;
                }
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to parse CSV file: ' . $e->getMessage());
            throw new \Exception('Failed to parse CSV file: ' . $e->getMessage());
        } finally {
            fclose($handle);
        }
    }

    /**
     * Parse Excel file and return array of data.
     */
    protected function parseExcelFile(string $filePath): array
    {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet   = $spreadsheet->getActiveSheet();

            // Read cells directly so we can detect and convert Excel date/time serial
            // numbers ourselves, instead of relying on toArray()'s format-mask output
            // which can produce "2026-02-03 00:00:00 06:49:00" for datetime cells.
            $highestRow    = $worksheet->getHighestDataRow();
            $highestCol    = $worksheet->getHighestDataColumn();
            $highestColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

            if ($highestRow < 2) {
                return [];
            }

            // Row 1 = headers
            $headers = [];
            for ($col = 1; $col <= $highestColIdx; $col++) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $headers[$col] = trim((string) $worksheet->getCell($coord . '1')->getValue());
            }

            $data = [];
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColIdx; $col++) {
                    $header = $headers[$col] ?? '';
                    if ($header === '') {
                        continue;
                    }
                    $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $cell  = $worksheet->getCell($coord . $row);
                    $value = $cell->getValue();

                    // Convert Excel date/time serial numbers to a clean datetime string
                    if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell) && is_numeric($value)) {
                        $dt    = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                        $value = $dt->format('Y-m-d H:i:s');
                    }

                    $rowData[$header] = ($value !== null && $value !== '') ? trim((string) $value) : null;
                }
                $data[] = $rowData;
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to parse Excel file: ' . $e->getMessage());
            throw new \Exception('Failed to parse Excel file: ' . $e->getMessage());
        }
    }

    /**
     * Get import template information
     */
    public function getTemplateInfo(Request $request)
    {
        return response()->json([
            'staff_information_template' => [
                'description' => 'Import staff/employee information from biometric device',
                'required_columns' => ['Staff Code', 'Name'],
                'optional_columns' => ['User ID', 'Email', 'Mobile No', 'Gender', 'Entry Date', 'Entry Status', 'Position', 'Project'],
                'format' => 'Excel (.xls, .xlsx) or CSV',
            ],
            'punch_records_template' => [
                'description' => 'Import attendance punch records from biometric device export',
                'required_columns' => ['Staff Code', 'Name', 'Punch Date'],
                'optional_columns' => ['Punch Type', 'Punch Address', 'Device Name', 'Punch Photo', 'Remark'],
                'date_format' => 'YYYY-MM-DD HH:MM (e.g., 2026-02-02 17:18)',
                'notes' => 'Each row is one punch event. Multiple punches for the same employee and date are grouped automatically.',
                'format' => 'Excel (.xls, .xlsx) or CSV',
            ],
        ]);
    }
}
