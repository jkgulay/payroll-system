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
        $this->middleware('role:admin,accountant');
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

            // Import staff data
            $result = $this->staffImportService->importStaffInformation($staffData);

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
            'year' => 'nullable|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
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

            // Import punch records
            $year = $validated['year'] ?? now()->year;
            $month = $validated['month'] ?? null;
            
            $result = $this->punchRecordImportService->importPunchRecords($punchData, $year, $month);

            // Log the import
            AuditLog::create([
                'user_id' => $request->user()->id,
                'module' => 'attendance',
                'action' => 'punch_record_import',
                'description' => 'Punch records imported from biometric device export',
                'old_values' => null,
                'new_values' => [
                    'file' => $file->getClientOriginalName(),
                    'year' => $year,
                    'month' => $month,
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
            // Use PhpSpreadsheet to read the file
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                return [];
            }

            // First row is header
            $headers = array_shift($rows);
            
            // Clean headers
            $headers = array_map(function($header) {
                return trim($header);
            }, $headers);

            // Convert rows to associative arrays
            $data = [];
            foreach ($rows as $row) {
                $rowData = [];
                foreach ($headers as $index => $header) {
                    $rowData[$header] = $row[$index] ?? null;
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
                'description' => 'Import attendance punch records with date-based columns',
                'required_columns' => ['Staff Code', 'Name'],
                'date_columns' => 'Columns with format MM-DD (e.g., 12-01, 12-02) containing time entries',
                'time_format' => 'HH:MM (e.g., 08:30, 17:00), multiple entries separated by newlines',
                'format' => 'Excel (.xls, .xlsx) or CSV',
                'parameters' => [
                    'year' => 'Year of the records (default: current year)',
                    'month' => 'Optional: Override the month from date columns',
                ],
            ],
        ]);
    }
}
