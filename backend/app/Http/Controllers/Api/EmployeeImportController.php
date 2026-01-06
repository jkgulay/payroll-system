<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\PositionRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmployeeImportController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,accountant');
    }

    /**
     * Import employees from CSV/Excel data
     * 
     * Expected format:
     * - Staff Code, Name, Department, Gender, Card No, Punch Password, 
     *   Mobile No, Email, Entry Date, Entry Status, Fingerprint/Face
     */
    public function import(Request $request)
    {
        // Increase execution time for large imports
        set_time_limit(300); // 5 minutes
        ini_set('memory_limit', '512M'); // Increase memory limit

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
                    $position = !empty($data['position']) ? trim($data['position']) : 'General Worker';

                    // Try to get salary from CSV, otherwise lookup from position rates
                    if (!empty($data['basic_salary'])) {
                        $basicSalary = floatval($data['basic_salary']);
                    } elseif (isset($positionRates[$position])) {
                        // Use position rate from database
                        $basicSalary = $positionRates[$position];
                    } else {
                        // Fallback to default rate if position not found (consistent with Add Employee dialog)
                        $basicSalary = 450;
                        Log::warning("Position '{$position}' not found in position rates table for employee {$data['staff_code']}. Add this position in Payroll > Pay Rates page.");
                    }

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
                        'project_id' => 1, // Default to first project
                        'position' => $position,
                        'basic_salary' => $basicSalary,
                        'salary_type' => 'daily', // Default for construction
                        'is_active' => true,
                        'username' => $username,
                        'created_by' => $request->user()->id,
                    ];

                    // Create employee record
                    $employee = Employee::create($employeeData);

                    $imported++;

                    // Store department info in a note (since no department table yet)
                    if (!empty($data['department'])) {
                        Log::info("Employee {$data['staff_code']} department: {$data['department']}");
                    }
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
}
