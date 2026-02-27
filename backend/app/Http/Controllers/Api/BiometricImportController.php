<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StaffImportService;
use App\Services\PunchRecordImportService;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
     * Import staff information from biometric device export
     * Accepts Excel/CSV files with staff data
     */
    public function importStaffInformation(Request $request)
    {
        // Increase execution time for large imports
        set_time_limit(300);
        ini_set('memory_limit', '1024M');

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'default_position' => 'nullable|string',
            'default_project_id' => 'nullable|integer|exists:projects,id',
        ]);

        try {
            $file = $request->file('file');
            $filePath = $file->storeAs('biometric_imports', 'staff_import_' . time() . '.' . $file->getClientOriginalExtension());
            $fullPath = Storage::path($filePath);

            // Parse Excel file
            $staffData = $this->parseExcelFile($fullPath);

            if (empty($staffData)) {
                return response()->json([
                    'message' => 'No valid data found in file',
                ], 422);
            }

            // Import staff data with optional defaults
            $defaults = [
                'position' => $validated['default_position'] ?? null,
                'project_id' => $validated['default_project_id'] ?? null,
            ];

            $result = $this->staffImportService->importStaffInformation($staffData, $defaults);

            // Log the import
            AuditLog::create([
                'user_id' => $request->user()->id,
                'module' => 'employees',
                'action' => 'staff_import',
                'description' => 'Staff information imported from biometric device export',
                'old_values' => null,
                'new_values' => [
                    'file' => $file->getClientOriginalName(),
                    'imported' => $result['imported'],
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                    'failed' => $result['failed'],
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Clean up temp file
            Storage::delete($filePath);

            return response()->json([
                'message' => 'Staff information imported successfully',
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'errors' => $result['error_details'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error('Staff import failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to import staff information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Import punch records (attendance) from biometric device export
     * Accepts Excel/CSV files with date-based columns
     */
    public function importPunchRecords(Request $request)
    {
        // Increase execution time for large imports
        set_time_limit(300);
        ini_set('memory_limit', '1024M');

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            $file = $request->file('file');
            $filePath = $file->storeAs('biometric_imports', 'punch_import_' . time() . '.' . $file->getClientOriginalExtension());
            $fullPath = Storage::path($filePath);

            // Parse Excel file
            $punchData = $this->parseExcelFile($fullPath);

            if (empty($punchData)) {
                return response()->json([
                    'message' => 'No valid data found in file',
                ], 422);
            }

            $result = $this->punchRecordImportService->importPunchRecords($punchData);

            // Log the import
            AuditLog::create([
                'user_id' => $request->user()->id,
                'module' => 'attendance',
                'action' => 'punch_record_import',
                'description' => 'Punch records imported from biometric device export',
                'old_values' => null,
                'new_values' => [
                    'file' => $file->getClientOriginalName(),
                    'imported' => $result['imported'],
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                    'failed' => $result['failed'],
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Clean up temp file
            Storage::delete($filePath);

            return response()->json([
                'message' => 'Punch records imported successfully',
                'imported' => $result['imported'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'errors' => $result['error_details'] ?? [],
            ]);
        } catch (\Exception $e) {
            Log::error('Punch record import failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to import punch records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse Excel/CSV file and return array of data
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
                'optional_columns' => ['User ID', 'Email', 'Mobile No', 'Gender', 'Entry Date', 'Entry Status', 'Position', 'Department'],
                'format' => 'Excel (.xls, .xlsx) or CSV',
            ],
            'punch_records_template' => [
                'description' => 'Import attendance punch records from biometric device export',
                'required_columns' => ['Staff Code', 'Name', 'Punch Date'],
                'optional_columns' => ['Punch Type', 'Punch Address', 'Device Name', 'Punch Photo', 'Remark'],
                'date_format' => 'YYYY-MM-DD HH:MM (e.g., 2026-02-02 17:18)',
                'notes' => 'Each row is one punch event. Multiple punches for the same employee and date are grouped automatically.',
                'format' => 'Excel (.xls, .xlsx)',
            ],
        ]);
    }
}
