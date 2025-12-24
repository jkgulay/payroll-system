<?php

namespace App\Services\Government;

/**
 * PhilHealth Computation Service (2025 Rates)
 * Philippine Health Insurance Corporation
 */
class PhilHealthComputationService
{
    /**
     * Compute PhilHealth contribution
     * Current rate: 4% (2% employee, 2% employer)
     * Based on basic salary
     */
    public function computeContribution(float $monthlySalary): array
    {
        // 2025 PhilHealth rates
        $premiumRate = 0.04; // 4% total (2% each)
        $minimumSalary = 10000.00;
        $maximumSalary = 80000.00; // Updated for 2025

        // Use salary within bounds
        $baseSalary = max($minimumSalary, min($monthlySalary, $maximumSalary));

        // Calculate total premium
        $totalPremium = $baseSalary * $premiumRate;

        // Split evenly between employee and employer
        $employeeShare = $totalPremium / 2;
        $employerShare = $totalPremium / 2;

        return [
            'base_salary' => $baseSalary,
            'premium_rate' => $premiumRate,
            'employee_share' => round($employeeShare, 2),
            'employer_share' => round($employerShare, 2),
            'total' => round($totalPremium, 2),
        ];
    }

    /**
     * Get monthly premium for specific salary
     */
    public function getMonthlyPremium(float $monthlySalary): float
    {
        $contribution = $this->computeContribution($monthlySalary);
        return $contribution['total'];
    }

    /**
     * Get employee share only
     */
    public function getEmployeeShare(float $monthlySalary): float
    {
        $contribution = $this->computeContribution($monthlySalary);
        return $contribution['employee_share'];
    }

    /**
     * Get employer share only
     */
    public function getEmployerShare(float $monthlySalary): float
    {
        $contribution = $this->computeContribution($monthlySalary);
        return $contribution['employer_share'];
    }
}
