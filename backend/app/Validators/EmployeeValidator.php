<?php

namespace App\Validators;

/**
 * Centralized validation logic for employee operations
 * Extracts reusable validation rules from controllers
 */
class EmployeeValidator
{
    /**
     * Validate that employee has required fields for user account creation
     * 
     * @param \App\Models\Employee $employee
     * @throws \InvalidArgumentException
     */
    public static function validateForUserCreation(\App\Models\Employee $employee): void
    {
        $missingFields = [];

        if (empty($employee->first_name)) {
            $missingFields[] = 'first_name';
        }

        if (empty($employee->last_name)) {
            $missingFields[] = 'last_name';
        }

        if (empty($employee->employee_number)) {
            $missingFields[] = 'employee_number';
        }

        if (!empty($missingFields)) {
            throw new \InvalidArgumentException(
                'Employee missing required fields for user creation: ' . implode(', ', $missingFields)
            );
        }
    }

    /**
     * Validate employee data has minimum required fields
     * 
     * @param array $data
     * @return bool
     */
    public static function hasMinimumFields(array $data): bool
    {
        $requiredFields = ['first_name', 'last_name'];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate salary change is within acceptable range
     * 
     * @param float $oldSalary
     * @param float $newSalary
     * @param float $maxIncreasePercent Maximum increase percentage (default 50%)
     * @return bool
     */
    public static function isSalaryChangeValid(float $oldSalary, float $newSalary, float $maxIncreasePercent = 50): bool
    {
        if ($newSalary < 0) {
            return false; // Negative salary not allowed
        }

        // Allow any decrease, but limit increases
        if ($newSalary > $oldSalary) {
            $increasePercent = (($newSalary - $oldSalary) / $oldSalary) * 100;
            return $increasePercent <= $maxIncreasePercent;
        }

        return true;
    }

    /**
     * Validate position change is allowed
     * 
     * @param int|null $oldPositionId
     * @param int $newPositionId
     * @return bool
     */
    public static function isPositionChangeValid(?int $oldPositionId, int $newPositionId): bool
    {
        // Allow any position change if old position is null
        if ($oldPositionId === null) {
            return true;
        }

        // Ensure new position exists
        return \App\Models\PositionRate::where('id', $newPositionId)->exists();
    }

    /**
     * Validate email format if provided
     * 
     * @param string|null $email
     * @return bool
     */
    public static function isValidEmail(?string $email): bool
    {
        if ($email === null || $email === '') {
            return true; // Email is optional
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
