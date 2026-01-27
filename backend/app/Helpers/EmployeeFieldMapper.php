<?php

namespace App\Helpers;

use App\Models\PositionRate;

/**
 * Helper class to handle employee field mapping and normalization
 * Consolidates logic for mapping legacy field names to current schema
 */
class EmployeeFieldMapper
{
    /**
     * Map and normalize employee data fields
     * Handles legacy field names, defaults, and position mapping
     * 
     * @param array $data Input data with potentially mixed field names
     * @return array Normalized data ready for database
     */
    public static function normalizeEmployeeData(array $data): array
    {
        $normalized = $data;

        // Set defaults for required database fields if not provided
        $normalized['date_of_birth'] = $normalized['date_of_birth'] ?? DateHelper::yearsAgo(25);
        $normalized['project_id'] = $normalized['project_id'] ?? 1;
        $normalized['contract_type'] = $normalized['contract_type'] ?? 'regular';
        $normalized['activity_status'] = $normalized['activity_status'] ?? 'active';
        $normalized['date_hired'] = $normalized['date_hired'] ?? DateHelper::today();
        $normalized['basic_salary'] = $normalized['basic_salary'] ?? 450; // Minimum wage default
        $normalized['salary_type'] = $normalized['salary_type'] ?? 'daily';

        // Handle work_schedule (new) or employment_type (legacy)
        $normalized = self::mapWorkSchedule($normalized);

        // Normalize gender to lowercase for consistency
        $normalized = self::normalizeGender($normalized);

        // Map position string to position_id if needed
        $normalized = self::mapPosition($normalized);

        // Remove position string from data (only position_id is stored)
        unset($normalized['position']);

        return $normalized;
    }

    /**
     * Map legacy employment_type to work_schedule
     * 
     * @param array $data
     * @return array
     */
    protected static function mapWorkSchedule(array $data): array
    {
        if (isset($data['work_schedule'])) {
            // Already set, keep it
            return $data;
        }

        if (isset($data['employment_type'])) {
            // Legacy: map old values to new
            $data['work_schedule'] = $data['employment_type'] === 'part_time' ? 'part_time' : 'full_time';
            unset($data['employment_type']);
        } else {
            $data['work_schedule'] = 'full_time'; // Default
        }

        return $data;
    }

    /**
     * Normalize gender field to lowercase
     * 
     * @param array $data
     * @return array
     */
    protected static function normalizeGender(array $data): array
    {
        if (!empty($data['gender'])) {
            $data['gender'] = strtolower($data['gender']);
        } else {
            $data['gender'] = 'male'; // Default
        }

        return $data;
    }

    /**
     * Map position string to position_id and set basic_salary from position rate
     * 
     * @param array $data
     * @return array
     */
    protected static function mapPosition(array $data): array
    {
        // Map position string to position_id if position is provided but position_id is not
        if (!empty($data['position']) && empty($data['position_id'])) {
            $positionRate = PositionRate::where('position_name', $data['position'])->first();
            if ($positionRate) {
                $data['position_id'] = $positionRate->id;
                // Always set basic_salary from position rate (ensures consistency)
                $data['basic_salary'] = $positionRate->daily_rate;
                $data['_position_rate'] = $positionRate; // Store for role mapping
            }
        }

        // If position_id is provided, sync basic_salary with position rate
        if (!empty($data['position_id']) && empty($data['basic_salary'])) {
            $positionRate = PositionRate::find($data['position_id']);
            if ($positionRate) {
                $data['basic_salary'] = $positionRate->daily_rate;
                $data['_position_rate'] = $positionRate; // Store for role mapping
            }
        }

        return $data;
    }

    /**
     * Determine user role based on position
     * 
     * @param array $data Normalized employee data with _position_rate
     * @param string $defaultRole Default role if no special position
     * @return string Role name
     */
    public static function determineRoleFromPosition(array $data, string $defaultRole = 'employee'): string
    {
        if (isset($data['_position_rate'])) {
            $positionRate = $data['_position_rate'];
            $positionName = strtolower($positionRate->position_name);

            if ($positionName === 'accountant') {
                return 'accountant';
            } elseif ($positionName === 'payrollist') {
                return 'payrollist';
            }
        }

        // Check by position string as fallback
        if (!empty($data['position'])) {
            $positionName = strtolower($data['position']);

            if ($positionName === 'accountant') {
                return 'accountant';
            } elseif ($positionName === 'payrollist') {
                return 'payrollist';
            }
        }

        return $defaultRole;
    }

    /**
     * Generate username from employee data
     * 
     * @param array $data Employee data
     * @return string Unique username
     */
    public static function generateUsername(array $data): string
    {
        // Use email if provided
        if (!empty($data['email'])) {
            return $data['email'];
        }

        // Generate from firstname.lastname
        $baseUsername = strtolower($data['first_name'] . '.' . $data['last_name']);
        // Remove special characters and spaces
        $baseUsername = preg_replace('/[^a-z0-9.]/', '', $baseUsername);

        // Ensure username is unique
        $username = $baseUsername;
        $counter = 1;
        while (\App\Models\User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Generate temporary password for employee
     * Ensures password is at least 8 characters to meet validation requirements
     * 
     * @param string $lastName Employee last name
     * @param string $employeeNumber Employee number (e.g., EMP001)
     * @return string Generated password (minimum 8 characters)
     */
    public static function generateTemporaryPassword(string $lastName, string $employeeNumber): string
    {
        // Generate password: LastName + EmployeeNumber + @ + random digits
        $randomDigits = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
        $password = $lastName . $employeeNumber . '@' . $randomDigits;

        // Ensure minimum 8 characters (for login validation)
        // If password is too short, add random suffix
        if (strlen($password) < 8) {
            $additionalChars = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $password .= $additionalChars;
        }

        return $password;
    }
}
