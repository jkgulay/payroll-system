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
        $query = Employee::with(['project', 'positionRate'])
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
                // Government IDs
                'sss_number' => $employee->sss_number,
                'philhealth_number' => $employee->philhealth_number,
                'pagibig_number' => $employee->pagibig_number,
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
     * Returns monthly employee share
     */
    private function computeSSS(float $monthlySalary): float
    {
        if ($monthlySalary < 4250) return 180.00;
        if ($monthlySalary < 4750) return 202.50;
        if ($monthlySalary < 5250) return 225.00;
        if ($monthlySalary < 5750) return 247.50;
        if ($monthlySalary < 6250) return 270.00;
        if ($monthlySalary < 6750) return 292.50;
        if ($monthlySalary < 7250) return 315.00;
        if ($monthlySalary < 7750) return 337.50;
        if ($monthlySalary < 8250) return 360.00;
        if ($monthlySalary < 8750) return 382.50;
        if ($monthlySalary < 9250) return 405.00;
        if ($monthlySalary < 9750) return 427.50;
        if ($monthlySalary < 10250) return 450.00;
        if ($monthlySalary < 10750) return 472.50;
        if ($monthlySalary < 11250) return 495.00;
        if ($monthlySalary < 11750) return 517.50;
        if ($monthlySalary < 12250) return 540.00;
        if ($monthlySalary < 12750) return 562.50;
        if ($monthlySalary < 13250) return 585.00;
        if ($monthlySalary < 13750) return 607.50;
        if ($monthlySalary < 14250) return 630.00;
        if ($monthlySalary < 14750) return 652.50;
        if ($monthlySalary < 15250) return 675.00;
        if ($monthlySalary < 15750) return 697.50;
        if ($monthlySalary < 16250) return 720.00;
        if ($monthlySalary < 16750) return 742.50;
        if ($monthlySalary < 17250) return 765.00;
        if ($monthlySalary < 17750) return 787.50;
        if ($monthlySalary < 18250) return 810.00;
        if ($monthlySalary < 18750) return 832.50;
        if ($monthlySalary < 19250) return 855.00;
        if ($monthlySalary < 19750) return 877.50;
        if ($monthlySalary < 20250) return 900.00;
        if ($monthlySalary < 20750) return 922.50;
        if ($monthlySalary < 21250) return 945.00;
        if ($monthlySalary < 21750) return 967.50;
        if ($monthlySalary < 22250) return 990.00;
        if ($monthlySalary < 22750) return 1012.50;
        if ($monthlySalary < 23250) return 1035.00;
        if ($monthlySalary < 23750) return 1057.50;
        if ($monthlySalary < 24250) return 1080.00;
        if ($monthlySalary < 24750) return 1102.50;
        if ($monthlySalary >= 25000) return 1125.00;

        return 1125.00;
    }

    /**
     * Compute PhilHealth contribution
     * Returns monthly employee share (2.25% of salary)
     */
    private function computePhilHealth(float $monthlySalary): float
    {
        // PhilHealth 2024-2026: 4.5% of monthly salary (2.25% employee share)
        $contribution = $monthlySalary * 0.045;
        $employeeShare = $contribution / 2;

        // Minimum: ₱225, Maximum: ₱1,800 per month
        return round(min(max($employeeShare, 225), 1800), 2);
    }

    /**
     * Compute Pag-IBIG contribution
     * Returns monthly employee share (2% of salary, max ₱200)
     */
    private function computePagibig(float $monthlySalary): float
    {
        // Pag-IBIG: Employee share is 2% of monthly salary
        $contribution = $monthlySalary * 0.02;

        // Minimum: ₱50/month, Maximum: ₱200/month
        return round(min(max($contribution, 50), 200), 2);
    }
}
