<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\GovernmentRate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EmployeeContributionController extends Controller
{
    /**
     * Get all employees with their contribution details
     * Includes computed contributions and any custom overrides
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['project', 'positionRate', 'governmentInfo'])
            ->whereIn('activity_status', ['active', 'on_leave']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Filter by project
        if ($request->has('project_id') && $request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by department
        if ($request->has('department') && $request->department) {
            $query->where('department', $request->department);
        }

        $employees = $query->orderBy('last_name')->orderBy('first_name')->get();

        // Calculate contributions for each employee
        $employeesWithContributions = $employees->map(function ($employee) {
            $monthlyRate = $employee->getMonthlyRate();

            // Get computed contributions based on government rates
            $computedSss = $this->computeSSS($monthlyRate);
            $computedPhilhealth = $this->computePhilHealth($monthlyRate);
            $computedPagibig = $this->computePagibig($monthlyRate);

            return [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'position' => $employee->position,
                'department' => $employee->department,
                'project' => $employee->project?->name,
                'project_id' => $employee->project_id,
                'basic_salary' => (float) $employee->getBasicSalary(),
                'monthly_rate' => $monthlyRate,
                'salary_type' => $employee->salary_type,
                // Contribution flags
                'has_sss' => $employee->has_sss,
                'has_philhealth' => $employee->has_philhealth,
                'has_pagibig' => $employee->has_pagibig,
                // Custom contributions (null means use computed)
                'custom_sss' => $employee->custom_sss,
                'custom_philhealth' => $employee->custom_philhealth,
                'custom_pagibig' => $employee->custom_pagibig,
                // Computed contributions based on salary
                'computed_sss' => $computedSss,
                'computed_philhealth' => $computedPhilhealth,
                'computed_pagibig' => $computedPagibig,
                // Effective contributions (custom if set, otherwise computed)
                'effective_sss' => $employee->has_sss ? ($employee->custom_sss ?? $computedSss) : 0,
                'effective_philhealth' => $employee->has_philhealth ? ($employee->custom_philhealth ?? $computedPhilhealth) : 0,
                'effective_pagibig' => $employee->has_pagibig ? ($employee->custom_pagibig ?? $computedPagibig) : 0,
                // Notes
                'contribution_notes' => $employee->contribution_notes,
                // Government IDs (from employee_government_info — canonical store)
                'sss_number' => $employee->governmentInfo?->sss_number,
                'philhealth_number' => $employee->governmentInfo?->philhealth_number,
                'pagibig_number' => $employee->governmentInfo?->pagibig_number,
            ];
        });

        return response()->json([
            'data' => $employeesWithContributions,
            'total' => $employeesWithContributions->count(),
        ]);
    }

    /**
     * Update contribution settings for a single employee
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'has_sss' => 'sometimes|boolean',
            'has_philhealth' => 'sometimes|boolean',
            'has_pagibig' => 'sometimes|boolean',
            'custom_sss' => 'nullable|numeric|min:0|max:99999.99',
            'custom_philhealth' => 'nullable|numeric|min:0|max:99999.99',
            'custom_pagibig' => 'nullable|numeric|min:0|max:99999.99',
            'contribution_notes' => 'nullable|string|max:500',
        ]);

        $employee->update($validated);

        // Recalculate effective contributions
        $monthlyRate = $employee->getMonthlyRate();
        $computedSss = $this->computeSSS($monthlyRate);
        $computedPhilhealth = $this->computePhilHealth($monthlyRate);
        $computedPagibig = $this->computePagibig($monthlyRate);

        return response()->json([
            'message' => 'Employee contributions updated successfully',
            'data' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'has_sss' => $employee->has_sss,
                'has_philhealth' => $employee->has_philhealth,
                'has_pagibig' => $employee->has_pagibig,
                'custom_sss' => $employee->custom_sss,
                'custom_philhealth' => $employee->custom_philhealth,
                'custom_pagibig' => $employee->custom_pagibig,
                'computed_sss' => $computedSss,
                'computed_philhealth' => $computedPhilhealth,
                'computed_pagibig' => $computedPagibig,
                'effective_sss' => $employee->has_sss ? ($employee->custom_sss ?? $computedSss) : 0,
                'effective_philhealth' => $employee->has_philhealth ? ($employee->custom_philhealth ?? $computedPhilhealth) : 0,
                'effective_pagibig' => $employee->has_pagibig ? ($employee->custom_pagibig ?? $computedPagibig) : 0,
                'contribution_notes' => $employee->contribution_notes,
            ],
        ]);
    }

    /**
     * Bulk update contributions for multiple employees
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employees' => 'required|array',
            'employees.*.id' => 'required|exists:employees,id',
            'employees.*.has_sss' => 'sometimes|boolean',
            'employees.*.has_philhealth' => 'sometimes|boolean',
            'employees.*.has_pagibig' => 'sometimes|boolean',
            'employees.*.custom_sss' => 'nullable|numeric|min:0|max:99999.99',
            'employees.*.custom_philhealth' => 'nullable|numeric|min:0|max:99999.99',
            'employees.*.custom_pagibig' => 'nullable|numeric|min:0|max:99999.99',
            'employees.*.contribution_notes' => 'nullable|string|max:500',
        ]);

        $updatedCount = 0;
        foreach ($validated['employees'] as $employeeData) {
            $employee = Employee::find($employeeData['id']);
            if ($employee) {
                unset($employeeData['id']);
                $employee->update($employeeData);
                $updatedCount++;
            }
        }

        return response()->json([
            'message' => "Successfully updated contributions for {$updatedCount} employee(s)",
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Reset contributions to computed values for an employee
     */
    public function reset(Employee $employee): JsonResponse
    {
        $employee->update([
            'custom_sss' => null,
            'custom_philhealth' => null,
            'custom_pagibig' => null,
            'contribution_notes' => null,
        ]);

        $monthlyRate = $employee->getMonthlyRate();

        return response()->json([
            'message' => 'Employee contributions reset to default values',
            'data' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'computed_sss' => $this->computeSSS($monthlyRate),
                'computed_philhealth' => $this->computePhilHealth($monthlyRate),
                'computed_pagibig' => $this->computePagibig($monthlyRate),
            ],
        ]);
    }

    /**
     * Get contribution summary statistics
     */
    public function summary(): JsonResponse
    {
        $employees = Employee::whereIn('activity_status', ['active', 'on_leave'])->get();

        $totalSss = 0;
        $totalPhilhealth = 0;
        $totalPagibig = 0;
        $customCount = 0;

        foreach ($employees as $employee) {
            $monthlyRate = $employee->getMonthlyRate();

            if ($employee->has_sss) {
                $totalSss += $employee->custom_sss ?? $this->computeSSS($monthlyRate);
            }
            if ($employee->has_philhealth) {
                $totalPhilhealth += $employee->custom_philhealth ?? $this->computePhilHealth($monthlyRate);
            }
            if ($employee->has_pagibig) {
                $totalPagibig += $employee->custom_pagibig ?? $this->computePagibig($monthlyRate);
            }

            if ($employee->custom_sss !== null || $employee->custom_philhealth !== null || $employee->custom_pagibig !== null) {
                $customCount++;
            }
        }

        return response()->json([
            'total_employees' => $employees->count(),
            'employees_with_sss' => $employees->where('has_sss', true)->count(),
            'employees_with_philhealth' => $employees->where('has_philhealth', true)->count(),
            'employees_with_pagibig' => $employees->where('has_pagibig', true)->count(),
            'employees_with_custom' => $customCount,
            'total_sss' => round($totalSss, 2),
            'total_philhealth' => round($totalPhilhealth, 2),
            'total_pagibig' => round($totalPagibig, 2),
            'total_contributions' => round($totalSss + $totalPhilhealth + $totalPagibig, 2),
        ]);
    }

    /**
     * Compute SSS contribution based on 2025 table
     * Returns semi-monthly employee share (per cutoff amount)
     */
    private function computeSSS(float $monthlySalary): float
    {
        $monthly = 0;
        if ($monthlySalary < 4250) $monthly = 180.00;
        elseif ($monthlySalary < 4750) $monthly = 202.50;
        elseif ($monthlySalary < 5250) $monthly = 225.00;
        elseif ($monthlySalary < 5750) $monthly = 247.50;
        elseif ($monthlySalary < 6250) $monthly = 270.00;
        elseif ($monthlySalary < 6750) $monthly = 292.50;
        elseif ($monthlySalary < 7250) $monthly = 315.00;
        elseif ($monthlySalary < 7750) $monthly = 337.50;
        elseif ($monthlySalary < 8250) $monthly = 360.00;
        elseif ($monthlySalary < 8750) $monthly = 382.50;
        elseif ($monthlySalary < 9250) $monthly = 405.00;
        elseif ($monthlySalary < 9750) $monthly = 427.50;
        elseif ($monthlySalary < 10250) $monthly = 450.00;
        elseif ($monthlySalary < 10750) $monthly = 472.50;
        elseif ($monthlySalary < 11250) $monthly = 495.00;
        elseif ($monthlySalary < 11750) $monthly = 517.50;
        elseif ($monthlySalary < 12250) $monthly = 540.00;
        elseif ($monthlySalary < 12750) $monthly = 562.50;
        elseif ($monthlySalary < 13250) $monthly = 585.00;
        elseif ($monthlySalary < 13750) $monthly = 607.50;
        elseif ($monthlySalary < 14250) $monthly = 630.00;
        elseif ($monthlySalary < 14750) $monthly = 652.50;
        elseif ($monthlySalary < 15250) $monthly = 675.00;
        elseif ($monthlySalary < 15750) $monthly = 697.50;
        elseif ($monthlySalary < 16250) $monthly = 720.00;
        elseif ($monthlySalary < 16750) $monthly = 742.50;
        elseif ($monthlySalary < 17250) $monthly = 765.00;
        elseif ($monthlySalary < 17750) $monthly = 787.50;
        elseif ($monthlySalary < 18250) $monthly = 810.00;
        elseif ($monthlySalary < 18750) $monthly = 832.50;
        elseif ($monthlySalary < 19250) $monthly = 855.00;
        elseif ($monthlySalary < 19750) $monthly = 877.50;
        elseif ($monthlySalary < 20250) $monthly = 900.00;
        elseif ($monthlySalary < 20750) $monthly = 922.50;
        elseif ($monthlySalary < 21250) $monthly = 945.00;
        elseif ($monthlySalary < 21750) $monthly = 967.50;
        elseif ($monthlySalary < 22250) $monthly = 990.00;
        elseif ($monthlySalary < 22750) $monthly = 1012.50;
        elseif ($monthlySalary < 23250) $monthly = 1035.00;
        elseif ($monthlySalary < 23750) $monthly = 1057.50;
        elseif ($monthlySalary < 24250) $monthly = 1080.00;
        elseif ($monthlySalary < 24750) $monthly = 1102.50;
        elseif ($monthlySalary >= 25000) $monthly = 1125.00;
        else $monthly = 1125.00;

        // Return semi-monthly (per cutoff) amount
        return round($monthly / 2, 2);
    }

    /**
     * Compute PhilHealth contribution
     * Returns semi-monthly employee share (per cutoff amount)
     */
    private function computePhilHealth(float $monthlySalary): float
    {
        // PhilHealth 2024-2026: 4.5% of monthly salary (2.25% employee share)
        $contribution = $monthlySalary * 0.045;
        $employeeShare = $contribution / 2;
        $semiMonthlyShare = $employeeShare / 2;

        // Minimum: ₱112.50, Maximum: ₱900 per semi-month
        return round(min(max($semiMonthlyShare, 112.50), 900), 2);
    }

    /**
     * Compute Pag-IBIG contribution
     * Returns semi-monthly employee share (per cutoff amount)
     */
    private function computePagibig(float $monthlySalary): float
    {
        // Pag-IBIG: Employee share is 2% of monthly salary
        $contribution = $monthlySalary * 0.02;
        $semiMonthlyContribution = $contribution / 2;

        // Per HDMF 2024 rules:
        // Maximum monthly employee contribution = ₱200 → semi-monthly = ₱100
        // Minimum semi-monthly = ₱25 (₱50/month floor)
        return round(min(max($semiMonthlyContribution, 25), 100), 2);
    }
}
