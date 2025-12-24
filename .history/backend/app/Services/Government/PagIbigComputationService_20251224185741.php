<?php

namespace App\Services\Government;

/**
 * Pag-IBIG Computation Service (2025 Rates)
 * Home Development Mutual Fund
 */
class PagIbigComputationService
{
    /**
     * Compute Pag-IBIG contribution
     * Employee: 1% for monthly compensation ≤ ₱1,500, 2% for > ₱1,500
     * Employer: 2%
     * Employee share capped at ₱200
     */
    public function computeContribution(float $monthlySalary): array
    {
        $threshold = 1500.00;
        $maxEmployeeContribution = 200.00;

        // Determine employee rate
        $employeeRate = $monthlySalary <= $threshold ? 0.01 : 0.02;
        $employerRate = 0.02;

        // Calculate contributions
        $employeeContribution = $monthlySalary * $employeeRate;
        $employerContribution = $monthlySalary * $employerRate;

        // Cap employee contribution at ₱200
        if ($employeeContribution > $maxEmployeeContribution) {
            $employeeContribution = $maxEmployeeContribution;
        }

        $totalContribution = $employeeContribution + $employerContribution;

        return [
            'monthly_compensation' => $monthlySalary,
            'employee_rate' => $employeeRate,
            'employer_rate' => $employerRate,
            'employee_share' => round($employeeContribution, 2),
            'employer_share' => round($employerContribution, 2),
            'total' => round($totalContribution, 2),
        ];
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
