<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\PositionRate;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmployeeImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,hr');
    }

    /**
     * Import employees from uploaded Excel file (FASTER - like biometric import)
     * 
     * Accepts multipart/form-data with 'file' field
     * Optional: default_position, default_project_id
     */
    public function importFromFile(Request $request)
    {
        // Increase execution time for large imports
        set_time_limit(300); // 5 minutes
        ini_set('memory_limit', '1024M');

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
            'default_position' => 'nullable|string',
            'default_project_id' => 'nullable|integer|exists:projects,id',
        ]);

        try {
            $file = $request->file('file');

            // Parse Excel file directly
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                return response()->json([
                    'message' => 'No data found in file',
                ], 422);
            }

            // First row is header
            $headers = array_shift($rows);
            $headers = array_map('trim', $headers);

            // Convert rows to associative arrays
            $employeeData = [];
            foreach ($rows as $row) {
                $rowData = [];
                foreach ($headers as $index => $header) {
                    $rowData[$header] = $row[$index] ?? null;
                }
                $employeeData[] = $rowData;
            }

            if (empty($employeeData)) {
                return response()->json([
                    'message' => 'No employee data found in file',
                ], 422);
            }

            // Load all position rates once
            $positionRates = PositionRate::where('is_active', true)
                ->pluck('daily_rate', 'position_name')
                ->toArray();

            // Load all existing employee numbers once
            $existingEmployeeNumbers = Employee::pluck('employee_number')->toArray();

            // Load all existing usernames once
            $existingUsernames = User::pluck('username')->toArray();

            $imported = 0;
            $failed = 0;
            $errors = [];
            $defaults = [
                'position' => $validated['default_position'] ?? null,
                'project_id' => $validated['default_project_id'] ?? null,
            ];

            DB::beginTransaction();

            try {
                foreach ($employeeData as $index => $data) {
                    try {
                        // Process each employee
                        $result = $this->processEmployeeFast($data, $positionRates, $existingEmployeeNumbers, $existingUsernames, $defaults, $request->user()->id);

                        if ($result['success']) {
                            $imported++;
                            // Add to tracking arrays
                            if (isset($result['employee_number'])) {
                                $existingEmployeeNumbers[] = $result['employee_number'];
                            }
                            if (isset($result['username'])) {
                                $existingUsernames[] = $result['username'];
                            }
                        } else {
                            $errors[] = [
                                'row' => $index + 2, // +2 because of header and 0-index
                                'staff_code' => $data['Staff Code'] ?? $data['staff_code'] ?? 'Unknown',
                                'error' => $result['error'],
                            ];
                            $failed++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = [
                            'row' => $index + 2,
                            'staff_code' => $data['Staff Code'] ?? $data['staff_code'] ?? 'Unknown',
                            'error' => $e->getMessage(),
                        ];
                        $failed++;
                    }
                }

                DB::commit();

                return response()->json([
                    'message' => "Import completed. {$imported} employees imported, {$failed} failed.",
                    'imported' => $imported,
                    'failed' => $failed,
                    'errors' => $errors,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Employee import from file failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Import failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process single employee FAST (optimized - no repeated queries)
     */
    private function processEmployeeFast(array $data, array $positionRates, array &$existingEmployeeNumbers, array &$existingUsernames, array $defaults, int $userId): array
    {
        // Validate required fields
        $staffCode = $data['Staff Code'] ?? $data['staff_code'] ?? null;
        $name = $data['Name'] ?? $data['name'] ?? null;
        $entryDate = $data['Entry Date'] ?? $data['entry_date'] ?? null;

        if (empty($staffCode)) {
            return ['success' => false, 'error' => 'Staff code is required'];
        }
        if (empty($name)) {
            return ['success' => false, 'error' => 'Name is required'];
        }
        if (empty($entryDate)) {
            return ['success' => false, 'error' => 'Entry date is required'];
        }

        // Check if employee already exists (using in-memory array - FAST)
        if (in_array($staffCode, $existingEmployeeNumbers)) {
            return ['success' => false, 'error' => 'Employee already exists'];
        }

        // Parse full name
        $nameParts = $this->parseFullName($name);

        // Map entry status to contract_type
        $entryStatus = $data['Entry Status'] ?? $data['entry_status'] ?? 'Probationary';
        $contractType = $this->mapEntryStatus($entryStatus);

        // Set gender
        $gender = strtolower(trim($data['Gender'] ?? $data['gender'] ?? 'male'));

        // Determine position
        $position = $data['Position'] ?? $data['position'] ?? $data['Staff Type'] ?? $data['staff_type'] ?? $defaults['position'] ?? 'General Worker';

        // Get position rate (from pre-loaded array)
        $basicSalary = 570; // Default
        $positionId = null;

        if (isset($positionRates[$position])) {
            $basicSalary = $positionRates[$position];
            // Get position_id
            $positionRate = PositionRate::where('position_name', $position)->first();
            $positionId = $positionRate ? $positionRate->id : null;
        } else {
            // Auto-create position if not exists
            $this->ensurePositionRateExists($position);
            $positionRate = PositionRate::where('position_name', $position)->first();
            if ($positionRate) {
                $positionId = $positionRate->id;
                $basicSalary = $positionRate->daily_rate;
                $positionRates[$position] = $basicSalary; // Cache it
            }
        }

        // Override with explicit salary if provided
        if (!empty($data['Basic Salary']) || !empty($data['basic_salary'])) {
            $basicSalary = floatval($data['Basic Salary'] ?? $data['basic_salary']);
        }

        // Map Department to Project (this might create a project - can't fully optimize)
        $projectId = !empty($data['Department']) || !empty($data['department'])
            ? $this->mapDepartmentToProject($data['Department'] ?? $data['department'])
            : $defaults['project_id'];

        // Generate username (check in-memory array - FAST)
        $baseUsername = strtolower($nameParts['first_name'] . '.' . $nameParts['last_name']);
        $baseUsername = preg_replace('/[^a-z0-9.]/', '', $baseUsername);

        $username = $baseUsername;
        $counter = 1;
        while (in_array($username, $existingUsernames)) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        // Generate password
        $password = $this->generatePassword($nameParts['last_name'], $staffCode);

        try {
            // Create user account
            $user = User::create([
                'name' => $name,
                'username' => $username,
                'email' => !empty($data['Email']) || !empty($data['email']) ? ($data['Email'] ?? $data['email']) : $username . '@company.com',
                'password' => Hash::make($password),
                'role' => 'employee',
                'is_active' => true,
                'must_change_password' => true,
            ]);

            // Create employee record
            $employee = Employee::create([
                'user_id' => $user->id,
                'employee_number' => $staffCode,
                'biometric_id' => $data['Punch Password'] ?? $data['punch_password'] ?? $data['Card No'] ?? $data['card_no'] ?? null,
                'first_name' => $nameParts['first_name'],
                'middle_name' => $nameParts['middle_name'],
                'last_name' => $nameParts['last_name'],
                'gender' => $gender,
                'date_of_birth' => now()->subYears(30)->format('Y-m-d'),
                'mobile_number' => $data['Mobile No'] ?? $data['mobile_no'] ?? null,
                'email' => $data['Email'] ?? $data['email'] ?? null,
                'date_hired' => $entryDate,
                'contract_type' => $contractType,
                'activity_status' => 'active',
                'work_schedule' => 'full_time',
                'project_id' => $projectId,
                'department' => $data['Department'] ?? $data['department'] ?? null,
                'staff_type' => $data['Staff Type'] ?? $data['staff_type'] ?? null,
                'position_id' => $positionId,
                'basic_salary' => $basicSalary,
                'salary_type' => 'daily',
                'is_active' => true,
                'username' => $username,
                'created_by' => $userId,
            ]);

            return [
                'success' => true,
                'employee_number' => $staffCode,
                'username' => $username,
            ];
        } catch (\Exception $e) {
            // Delete user if employee creation failed
            if (isset($user) && $user->id) {
                User::find($user->id)?->delete();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process single employee (extracted for reuse)
     */
    private function processEmployee(array $data, array $positionRates, array $defaults, int $userId): array
    {
        // Validate required fields
        $staffCode = $data['Staff Code'] ?? $data['staff_code'] ?? null;
        $name = $data['Name'] ?? $data['name'] ?? null;
        $entryDate = $data['Entry Date'] ?? $data['entry_date'] ?? null;

        if (empty($staffCode)) {
            return ['success' => false, 'error' => 'Staff code is required'];
        }
        if (empty($name)) {
            return ['success' => false, 'error' => 'Name is required'];
        }
        if (empty($entryDate)) {
            return ['success' => false, 'error' => 'Entry date is required'];
        }

        // Check if employee already exists
        $existingEmployee = Employee::where('employee_number', $staffCode)->first();
        if ($existingEmployee) {
            return ['success' => false, 'error' => 'Employee already exists'];
        }

        // Parse full name
        $nameParts = $this->parseFullName($name);

        // Map entry status to contract_type
        $entryStatus = $data['Entry Status'] ?? $data['entry_status'] ?? 'Probationary';
        $contractType = $this->mapEntryStatus($entryStatus);

        // Set gender
        $gender = strtolower(trim($data['Gender'] ?? $data['gender'] ?? 'male'));

        // Determine position
        $position = $data['Position'] ?? $data['position'] ?? $data['Staff Type'] ?? $data['staff_type'] ?? $defaults['position'] ?? 'General Worker';

        // Ensure position rate exists
        $this->ensurePositionRateExists($position);

        // Get position rate
        $positionRate = PositionRate::where('position_name', $position)->first();
        $positionId = $positionRate ? $positionRate->id : null;

        // Determine salary
        $basicSalary = !empty($data['Basic Salary']) || !empty($data['basic_salary'])
            ? floatval($data['Basic Salary'] ?? $data['basic_salary'])
            : ($positionRate ? $positionRate->daily_rate : 570);

        // Map Department to Project
        $projectId = !empty($data['Department']) || !empty($data['department'])
            ? $this->mapDepartmentToProject($data['Department'] ?? $data['department'])
            : $defaults['project_id'];

        // Create user account
        $username = $this->generateUsername($nameParts['first_name'], $nameParts['last_name']);
        $password = $this->generatePassword($nameParts['last_name'], $staffCode);

        try {
            $user = User::create([
                'name' => $name,
                'username' => $username,
                'email' => !empty($data['Email']) || !empty($data['email']) ? ($data['Email'] ?? $data['email']) : $username . '@company.com',
                'password' => Hash::make($password),
                'role' => 'employee',
                'is_active' => true,
                'must_change_password' => true,
            ]);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to create user account: ' . $e->getMessage()];
        }

        // Create employee record
        $employeeData = [
            'user_id' => $user->id,
            'employee_number' => $staffCode,
            'biometric_id' => $data['Punch Password'] ?? $data['punch_password'] ?? $data['Card No'] ?? $data['card_no'] ?? null,
            'first_name' => $nameParts['first_name'],
            'middle_name' => $nameParts['middle_name'],
            'last_name' => $nameParts['last_name'],
            'gender' => $gender,
            'date_of_birth' => now()->subYears(30)->format('Y-m-d'),
            'mobile_number' => $data['Mobile No'] ?? $data['mobile_no'] ?? null,
            'email' => $data['Email'] ?? $data['email'] ?? null,
            'date_hired' => $entryDate,
            'contract_type' => $contractType,
            'activity_status' => 'active',
            'work_schedule' => 'full_time',
            'project_id' => $projectId,
            'department' => $data['Department'] ?? $data['department'] ?? null,
            'staff_type' => $data['Staff Type'] ?? $data['staff_type'] ?? null,
            'position_id' => $positionId,
            'basic_salary' => $basicSalary,
            'salary_type' => 'daily',
            'is_active' => true,
            'username' => $username,
            'created_by' => $userId,
        ];

        try {
            Employee::create($employeeData);
            return ['success' => true];
        } catch (\Exception $e) {
            // Delete user if employee creation failed
            User::find($user->id)?->delete();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Import employees from CSV/Excel data (OLD METHOD - kept for backwards compatibility)
     * 
     * Expected format:
     * - Staff Code, Name, Department, Gender, Card No, Punch Password, 
     *   Mobile No, Email, Entry Date, Entry Status, Fingerprint/Face
     */
    public function import(Request $request)
    {
        // Increase execution time for large imports
        set_time_limit(300); // 5 minutes
        ini_set('memory_limit', '1024M'); // Increase memory limit

        // Load position rates for salary lookup
        $positionRates = PositionRate::where('is_active', true)
            ->pluck('daily_rate', 'position_name')
            ->toArray();

        $validator = Validator::make($request->all(), [
            'employees' => 'required|array',
            'employees.*.staff_code' => 'required|string',
            'employees.*.name' => 'required|string',
            'employees.*.department' => 'nullable|string',
            'employees.*.gender' => 'nullable|in:Male,Female,male,female',
            'employees.*.card_no' => 'nullable|string',
            'employees.*.punch_password' => 'nullable|string',
            'employees.*.mobile_no' => 'nullable|string',
            'employees.*.email' => 'nullable|email',
            'employees.*.entry_date' => 'required|date',
            'employees.*.entry_status' => 'required|string',
            'employees.*.fingerprint_face' => 'nullable|string',
            // Optional fields for better import
            'employees.*.position' => 'nullable|string',
            'employees.*.basic_salary' => 'nullable|numeric',
            'employees.*.work_schedule' => 'nullable|string',
            'employees.*.employment_type' => 'nullable|string', // Legacy support
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $imported = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($request->employees as $index => $data) {
                try {
                    // Validate required fields
                    if (empty($data['staff_code'])) {
                        $errors[] = [
                            'row' => $index + 1,
                            'staff_code' => 'N/A',
                            'error' => 'Staff code is required',
                        ];
                        $failed++;
                        continue;
                    }

                    if (empty($data['name'])) {
                        $errors[] = [
                            'row' => $index + 1,
                            'staff_code' => $data['staff_code'],
                            'error' => 'Name is required',
                        ];
                        $failed++;
                        continue;
                    }

                    if (empty($data['entry_date'])) {
                        $errors[] = [
                            'row' => $index + 1,
                            'staff_code' => $data['staff_code'],
                            'error' => 'Entry date is required',
                        ];
                        $failed++;
                        continue;
                    }

                    // Parse full name into first, middle, last
                    $nameParts = $this->parseFullName($data['name']);

                    // Map entry status to contract_type
                    $contractType = $this->mapEntryStatus($data['entry_status']);

                    // Set default gender if not provided
                    $gender = !empty($data['gender']) ? strtolower(trim($data['gender'])) : 'male';

                    // Normalize work_schedule to match database constraint
                    // Support both old employment_type and new work_schedule fields
                    $workSchedule = $data['work_schedule'] ?? $data['employment_type'] ?? 'full_time';
                    $workSchedule = str_replace('-', '_', strtolower(trim($workSchedule))); // full-time -> full_time

                    // Map common variations
                    $scheduleMap = [
                        'full_time' => 'full_time',
                        'fulltime' => 'full_time',
                        'regular' => 'full_time',
                        'contractual' => 'full_time',
                        'part_time' => 'part_time',
                        'parttime' => 'part_time',
                    ];

                    $workSchedule = $scheduleMap[$workSchedule] ?? 'full_time';

                    // Check if employee already exists
                    $existingEmployee = Employee::where('employee_number', $data['staff_code'])->first();

                    if ($existingEmployee) {
                        $errors[] = [
                            'row' => $index + 1,
                            'staff_code' => $data['staff_code'],
                            'error' => 'Employee already exists',
                        ];
                        $failed++;
                        continue;
                    }

                    // Create user account
                    $username = $this->generateUsername($nameParts['first_name'], $nameParts['last_name']);
                    $password = $this->generatePassword($nameParts['last_name'], $data['staff_code']);

                    try {
                        $user = User::create([
                            'name' => $data['name'],
                            'username' => $username,
                            'email' => !empty($data['email']) ? $data['email'] : $username . '@company.com',
                            'password' => Hash::make($password),
                            'role' => 'employee',
                            'is_active' => true,
                            'must_change_password' => true, // Force password change on first login
                        ]);
                    } catch (\Exception $e) {
                        $errors[] = [
                            'row' => $index + 1,
                            'staff_code' => $data['staff_code'],
                            'error' => 'Failed to create user account: ' . $e->getMessage(),
                        ];
                        $failed++;
                        continue;
                    }

                    // Determine position and salary
                    // Priority: position > staff_type > default
                    $position = !empty($data['position']) ? trim($data['position']) : (!empty($data['staff_type']) ? trim($data['staff_type']) : 'General Worker');

                    // Ensure position rate exists (auto-create if not found)
                    $this->ensurePositionRateExists($position);

                    // Get position rate
                    $positionRate = PositionRate::where('position_name', $position)->first();
                    $positionId = $positionRate ? $positionRate->id : null;

                    // Try to get salary from CSV, otherwise use position rate
                    if (!empty($data['basic_salary'])) {
                        $basicSalary = floatval($data['basic_salary']);
                    } else {
                        $basicSalary = $positionRate ? $positionRate->daily_rate : 570; // Default rate
                    }

                    // Map Department to Project ID
                    $projectId = $this->mapDepartmentToProject($data['department'] ?? null);

                    // Clean and prepare employee data
                    $employeeData = [
                        'user_id' => $user->id,
                        'employee_number' => $data['staff_code'],
                        'biometric_id' => !empty($data['punch_password']) ? $data['punch_password'] : (!empty($data['card_no']) ? $data['card_no'] : null),
                        'first_name' => $nameParts['first_name'],
                        'middle_name' => $nameParts['middle_name'],
                        'last_name' => $nameParts['last_name'],
                        'gender' => $gender,
                        'date_of_birth' => now()->subYears(30)->format('Y-m-d'), // Default: 30 years old
                        'mobile_number' => !empty($data['mobile_no']) ? $data['mobile_no'] : null,
                        'email' => !empty($data['email']) ? $data['email'] : null,
                        'date_hired' => $data['entry_date'],
                        'contract_type' => $contractType,
                        'activity_status' => 'active', // All imported employees are active
                        'work_schedule' => $workSchedule,
                        'project_id' => $projectId, // Mapped from department
                        'department' => !empty($data['department']) ? $data['department'] : null, // Keep for reference
                        'staff_type' => !empty($data['staff_type']) ? $data['staff_type'] : null, // Keep for reference
                        'position_id' => $positionId, // Link to position_rates table
                        'basic_salary' => $basicSalary,
                        'salary_type' => 'daily', // Default for construction
                        'is_active' => true,
                        'username' => $username,
                        'created_by' => $request->user()->id,
                    ];

                    // Create employee record
                    $employee = Employee::create($employeeData);

                    $imported++;
                } catch (\Exception $e) {
                    // Delete created user if employee creation failed
                    if (isset($user) && $user->id) {
                        User::find($user->id)?->delete();
                    }

                    // Get more specific error message
                    $errorMessage = $e->getMessage();
                    if (strpos($errorMessage, 'work_schedule_check') !== false || strpos($errorMessage, 'employment_type_check') !== false) {
                        $errorMessage = "Invalid work schedule. Must be: full_time or part_time";
                    } elseif (strpos($errorMessage, 'contract_type') !== false || strpos($errorMessage, 'activity_status') !== false) {
                        $errorMessage = "Invalid contract type or activity status";
                    } elseif (strpos($errorMessage, 'gender') !== false) {
                        $errorMessage = "Invalid gender. Must be: male, female, or other";
                    } elseif (strpos($errorMessage, 'Duplicate entry') !== false || strpos($errorMessage, 'unique') !== false) {
                        $errorMessage = "Duplicate employee number or email";
                    }

                    $errors[] = [
                        'row' => $index + 1,
                        'staff_code' => $data['staff_code'] ?? 'Unknown',
                        'name' => $data['name'] ?? 'Unknown',
                        'error' => $errorMessage,
                    ];
                    $failed++;

                    // Log detailed error for debugging
                    Log::error("Employee import failed for row " . ($index + 1), [
                        'staff_code' => $data['staff_code'] ?? 'Unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => "Import completed. {$imported} employees imported, {$failed} failed.",
                'imported' => $imported,
                'failed' => $failed,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Employee import failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Import failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse full name into first, middle, last
     */
    private function parseFullName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName));
        $count = count($parts);

        if ($count === 1) {
            return [
                'first_name' => $parts[0],
                'middle_name' => null,
                'last_name' => '',
            ];
        } elseif ($count === 2) {
            return [
                'first_name' => $parts[0],
                'middle_name' => null,
                'last_name' => $parts[1],
            ];
        } else {
            return [
                'first_name' => $parts[0],
                'middle_name' => $parts[1],
                'last_name' => implode(' ', array_slice($parts, 2)),
            ];
        }
    }

    /**
     * Map entry status to contract_type
     */
    private function mapEntryStatus(string $entryStatus): string
    {
        $statusMap = [
            'official' => 'regular',
            'regular' => 'regular',
            'probation' => 'probationary',
            'probationary' => 'probationary',
            'contract' => 'contractual',
            'contractual' => 'contractual',
        ];

        $status = strtolower(trim($entryStatus));
        return $statusMap[$status] ?? 'probationary';
    }

    /**
     * Generate username from name
     * Uses firstname.lastname pattern (consistent with EmployeeController)
     */
    private function generateUsername(string $firstName, string $lastName): string
    {
        // Use firstname.lastname pattern for consistency
        $base = strtolower($firstName . '.' . $lastName);
        // Remove special characters except period
        $base = preg_replace('/[^a-z0-9.]/', '', $base);

        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Generate temporary password
     * Uses LastName + StaffCode + @ + 2RandomDigits pattern (consistent with EmployeeController)
     */
    private function generatePassword(string $lastName, string $staffCode): string
    {
        $randomDigits = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
        return $lastName . $staffCode . '@' . $randomDigits;
    }

    /**
     * Download import template
     */
    public function downloadTemplate()
    {
        $template = [
            [
                'staff_code' => '00001',
                'name' => 'Juan Dela Cruz',
                'department' => 'Construction',
                'gender' => 'Male',
                'card_no' => '',
                'punch_password' => '123456',
                'mobile_no' => '09171234567',
                'email' => 'juan@example.com',
                'entry_date' => '2025-01-01',
                'entry_status' => 'Official',
                'position' => 'Mason',
                'basic_salary' => '650',
            ]
        ];

        return response()->json([
            'template' => $template,
            'instructions' => [
                'staff_code' => 'Employee number (required, unique)',
                'name' => 'Full name (required)',
                'department' => 'Department name (optional, for reference only)',
                'gender' => 'Male or Female (required)',
                'card_no' => 'Alternative biometric ID (optional)',
                'punch_password' => 'Biometric device PIN (optional)',
                'mobile_no' => 'Mobile number (optional)',
                'email' => 'Email address (optional)',
                'entry_date' => 'Date hired in YYYY-MM-DD format (required)',
                'entry_status' => 'Official/Regular/Probationary/Contractual (required)',
                'position' => 'Job position (optional, defaults to General Worker)',
                'basic_salary' => 'Daily rate (optional, defaults to 570)',
            ],
        ]);
    }

    /**
     * Map Department name to Project ID
     * Finds existing project or creates a new one
     */
    protected function mapDepartmentToProject(?string $departmentName): ?int
    {
        // If no department specified, return null (nullable field)
        if (empty($departmentName)) {
            return null;
        }

        $departmentName = trim($departmentName);

        // Try to find existing project with this name
        $project = Project::where('name', $departmentName)->first();

        if ($project) {
            return $project->id;
        }

        // Create new project from department name
        try {
            // Generate unique project code from name
            $code = $this->generateProjectCode($departmentName);

            $project = Project::create([
                'code' => $code,
                'name' => $departmentName,
                'description' => 'Auto-created from employee import',
                'is_active' => true,
            ]);

            Log::info('Created new project from department: ' . $departmentName, ['project_id' => $project->id]);

            return $project->id;
        } catch (\Exception $e) {
            Log::warning('Failed to create project for department: ' . $departmentName . '. Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate unique project code from department name
     */
    protected function generateProjectCode(string $name): string
    {
        // Take first 3 letters of name and convert to uppercase
        $baseCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));

        // If less than 3 letters, pad with 'X'
        if (strlen($baseCode) < 3) {
            $baseCode = str_pad($baseCode, 3, 'X');
        }

        $code = $baseCode;
        $counter = 1;

        // Ensure uniqueness
        while (Project::where('code', $code)->exists()) {
            $code = $baseCode . $counter;
            $counter++;
        }

        return $code;
    }

    /**
     * Ensure position rate exists in database, create if not found
     * 
     * @param string $positionName Position name to check/create
     */
    protected function ensurePositionRateExists(string $positionName): void
    {
        // Check if position rate already exists
        $exists = PositionRate::where('position_name', $positionName)->exists();

        if (!$exists) {
            // Create new position rate with default values
            PositionRate::create([
                'position_name' => $positionName,
                'daily_rate' => 570, // Default daily rate
                'is_active' => true,
                'created_by' => auth()->id() ?? 1,
            ]);

            Log::info("Auto-created position rate: {$positionName}");
        }
    }
}
