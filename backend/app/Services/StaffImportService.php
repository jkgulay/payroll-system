<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\Project;
use App\Models\PositionRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class StaffImportService
{
    /**
     * Import staff information from biometric device export
     * Expected columns: User ID, Staff Code, Name, ID No, Card No, Mobile No, Punch Pwd,
     * Department, Staff Type, Entry Date, Entry Status, Gender, Position, Degree, Address, Email, Phone, Remark
     * 
     * @param array $staffData Array of staff records
     * @param array $defaults Optional default values ['position' => string, 'project_id' => int]
     */
    public function importStaffInformation(array $staffData, array $defaults = []): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($staffData as $index => $data) {
                try {
                    // Skip if no staff code
                    if (empty($data['Staff Code'])) {
                        $skipped++;
                        continue;
                    }

                    // Skip if no name
                    if (empty($data['Name'])) {
                        $errors[] = [
                            'row' => $index + 1,
                            'staff_code' => $data['Staff Code'] ?? 'N/A',
                            'error' => 'Name is required',
                        ];
                        $failed++;
                        continue;
                    }

                    $result = $this->processStaffRecord($data, $defaults);

                    if ($result['action'] === 'created') {
                        $imported++;
                    } elseif ($result['action'] === 'updated') {
                        $updated++;
                    } elseif ($result['action'] === 'skipped') {
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $index + 1,
                        'staff_code' => $data['Staff Code'] ?? 'N/A',
                        'error' => $e->getMessage(),
                    ];
                    $failed++;
                    Log::error('Staff import error: ' . $e->getMessage(), ['data' => $data]);
                }
            }

            DB::commit();

            return [
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => count($errors),
                'error_details' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Staff import failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a single staff record
     * 
     * @param array $data Staff record data
     * @param array $defaults Default values for missing data
     */
    protected function processStaffRecord(array $data, array $defaults = []): array
    {
        $staffCode = trim($data['Staff Code']);
        $name = trim($data['Name']);

        // Parse name into first, middle, last
        $nameParts = $this->parseName($name);

        // Check if employee already exists by staff code
        $employee = Employee::where('employee_number', $staffCode)->first();

        // Parse entry date
        $entryDate = $this->parseDate($data['Entry Date'] ?? null);

        // Determine contract type from Entry Status
        $contractType = $this->mapContractType($data['Entry Status'] ?? '');

        // Parse gender
        $gender = strtolower(trim($data['Gender'] ?? ''));
        $gender = in_array($gender, ['male', 'female']) ? $gender : null;

        // Map Staff Type to Position (this is the job position)
        // Use default if position is missing
        $position = $this->determinePosition($data, $defaults['position'] ?? null);

        // Get position_id from position rate
        $positionRate = PositionRate::where('position_name', $position)->first();
        $positionId = $positionRate ? $positionRate->id : null;

        // Map Department to Project ID (find or create project)
        // Use default if department is missing
        $projectId = $this->mapDepartmentToProject($data['Department'] ?? null, $defaults['project_id'] ?? null);

        // Employee data
        $employeeData = [
            'employee_number' => $staffCode,
            'first_name' => $nameParts['first_name'],
            'middle_name' => $nameParts['middle_name'],
            'last_name' => $nameParts['last_name'],
            'suffix' => $nameParts['suffix'],
            'biometric_id' => !empty($data['User ID']) ? $data['User ID'] : null,
            'email' => !empty($data['Email']) ? trim($data['Email']) : null,
            'mobile_number' => $this->formatMobileNumber($data['Mobile No'] ?? null),
            'phone_number' => !empty($data['Phone']) ? trim($data['Phone']) : null,
            'gender' => $gender,
            'date_hired' => $entryDate,
            'contract_type' => $contractType,
            'activity_status' => 'active',
            'work_schedule' => 'full_time',
            'project_id' => $projectId, // Mapped from Department
            'department' => !empty($data['Department']) ? trim($data['Department']) : null, // Keep for reference
            'staff_type' => !empty($data['Staff Type']) ? trim($data['Staff Type']) : null, // Keep for reference
            'position_id' => $positionId, // Link to position_rates table
            'basic_salary' => $positionRate ? $positionRate->daily_rate : 570, // Use position rate or default
            'salary_type' => 'daily',
            'is_active' => true,
            'date_of_birth' => now()->subYears(30)->format('Y-m-d'), // Default age
        ];

        if ($employee) {
            // Update existing employee
            $employee->update($employeeData);
            return ['action' => 'updated', 'employee' => $employee];
        } else {
            // Create new employee
            $employeeData['username'] = $this->generateUsername($staffCode, $nameParts['first_name']);
            $employeeData['created_by'] = auth()->id() ?? 1;

            $employee = Employee::create($employeeData);
            return ['action' => 'created', 'employee' => $employee];
        }
    }

    /**
     * Parse full name into components
     * Handles single-word names by using the name as last name
     */
    protected function parseName(string $fullName): array
    {
        $fullName = trim($fullName);
        $parts = preg_split('/\s+/', $fullName);

        $firstName = '';
        $middleName = '';
        $lastName = '';
        $suffix = '';

        // Check for suffixes
        $suffixes = ['Jr', 'Jr.', 'Sr', 'Sr.', 'II', 'III', 'IV', 'V'];
        $lastPart = end($parts);

        if (in_array($lastPart, $suffixes)) {
            $suffix = array_pop($parts);
        }

        if (count($parts) === 1) {
            // Single word name - use as last name (more common in Philippine naming)
            // Keep first name empty or use the same value
            $lastName = $parts[0];
            $firstName = $parts[0]; // Use same value for both to satisfy any requirements
        } elseif (count($parts) === 2) {
            $firstName = $parts[0];
            $lastName = $parts[1];
        } elseif (count($parts) >= 3) {
            $firstName = $parts[0];
            $middleName = implode(' ', array_slice($parts, 1, -1));
            $lastName = $parts[count($parts) - 1];
        }

        return [
            'first_name' => $firstName ?: null,
            'middle_name' => $middleName ?: null,
            'last_name' => $lastName ?: null,
            'suffix' => $suffix ?: null,
        ];
    }

    /**
     * Parse date from various formats
     */
    protected function parseDate($dateString): ?string
    {
        if (empty($dateString)) {
            return now()->format('Y-m-d');
        }

        try {
            return Carbon::parse($dateString)->format('Y-m-d');
        } catch (\Exception $e) {
            return now()->format('Y-m-d');
        }
    }

    /**
     * Map contract type from entry status
     */
    protected function mapContractType(string $entryStatus): string
    {
        $status = strtolower(trim($entryStatus));

        $mapping = [
            'official' => 'regular',
            'regular' => 'regular',
            'probationary' => 'probationary',
            'contractual' => 'contractual',
            'project-based' => 'project_based',
        ];

        return $mapping[$status] ?? 'regular';
    }

    /**
     * Format mobile number
     */
    protected function formatMobileNumber($mobileNo): ?string
    {
        if (empty($mobileNo)) {
            return null;
        }

        // Remove non-numeric characters
        $mobile = preg_replace('/[^0-9]/', '', (string)$mobileNo);

        // Ensure it's a valid length
        if (strlen($mobile) < 10) {
            return null;
        }

        return $mobile;
    }

    /**
     * Generate unique username
     */
    protected function generateUsername(string $staffCode, string $firstName): string
    {
        $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName . $staffCode));
        $username = $baseUsername;
        $counter = 1;

        while (Employee::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Determine position from Staff Type or Position column
     * Priority: Staff Type > Position column > Default > Fallback
     * Auto-creates position rate if it doesn't exist
     * 
     * @param array $data Staff record data
     * @param string|null $default Default position if none specified
     */
    protected function determinePosition(array $data, ?string $default = null): string
    {
        $positionName = null;

        // First try Staff Type (this is their job position)
        if (!empty($data['Staff Type'])) {
            $positionName = trim($data['Staff Type']);
        }
        // Fallback to Position column if available
        elseif (!empty($data['Position'])) {
            $positionName = trim($data['Position']);
        }
        // Use default if provided
        elseif ($default) {
            $positionName = $default;
        }
        // Final fallback
        else {
            $positionName = 'General Worker';
        }

        // Ensure position rate exists in the system
        $this->ensurePositionRateExists($positionName);

        return $positionName;
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

    /**
     * Map Department name to Project ID
     * Finds existing project or creates a new one
     * 
     * @param string|null $departmentName Department name from import
     * @param int|null $defaultProjectId Default project ID if department is missing
     */
    protected function mapDepartmentToProject(?string $departmentName, ?int $defaultProjectId = null): ?int
    {
        // If department is specified, use it
        if (!empty($departmentName)) {
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
                    'description' => 'Auto-created from biometric import',
                    'is_active' => true,
                ]);

                Log::info('Created new project from department: ' . $departmentName, ['project_id' => $project->id]);

                return $project->id;
            } catch (\Exception $e) {
                Log::warning('Failed to create project for department: ' . $departmentName . '. Error: ' . $e->getMessage());
                // Fall through to use default if available
            }
        }

        // Use default project if provided
        if ($defaultProjectId) {
            return $defaultProjectId;
        }

        // Return null (nullable field)
        return null;
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
}
