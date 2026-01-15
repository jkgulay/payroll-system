<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
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
     */
    public function importStaffInformation(array $staffData): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
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
                        continue;
                    }

                    $result = $this->processStaffRecord($data);
                    
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
     */
    protected function processStaffRecord(array $data): array
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
            'project_id' => null, // Project assignment is optional
            'department' => !empty($data['Department']) ? trim($data['Department']) : null,
            'staff_type' => !empty($data['Staff Type']) ? trim($data['Staff Type']) : null,
            'position' => !empty($data['Position']) ? trim($data['Position']) : 'General Worker',
            'basic_salary' => 570, // Default daily rate
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
}
