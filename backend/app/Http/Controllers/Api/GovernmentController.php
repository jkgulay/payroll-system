<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GovernmentRate;
use Illuminate\Http\Request;

class GovernmentController extends Controller
{
    public function sssTable()
    {
        $table = GovernmentRate::where('type', 'sss')
            ->where('is_active', true)
            ->orderBy('min_salary')
            ->get()
            ->map(fn($rate) => [
                'id' => $rate->id,
                'min_salary' => $rate->min_salary,
                'max_salary' => $rate->max_salary,
                'employee_share' => $rate->employee_fixed ?? 0,
                'employer_share' => $rate->employer_fixed ?? 0,
                'total_contribution' => $rate->total_contribution ?? 0,
            ]);
        return response()->json($table);
    }

    public function philhealthTable()
    {
        $table = GovernmentRate::where('type', 'philhealth')
            ->where('is_active', true)
            ->orderBy('min_salary')
            ->get()
            ->map(fn($rate) => [
                'id' => $rate->id,
                'min_salary' => $rate->min_salary,
                'max_salary' => $rate->max_salary,
                'employee_share' => $rate->employee_rate ?? 0,
                'employer_share' => $rate->employer_rate ?? 0,
                'contribution_rate' => ($rate->employee_rate ?? 0) + ($rate->employer_rate ?? 0),
            ]);
        return response()->json($table);
    }

    public function pagibigTable()
    {
        $table = GovernmentRate::where('type', 'pagibig')
            ->where('is_active', true)
            ->orderBy('min_salary')
            ->get()
            ->map(fn($rate) => [
                'id' => $rate->id,
                'min_salary' => $rate->min_salary,
                'max_salary' => $rate->max_salary,
                'employee_rate' => $rate->employee_rate ?? 0,
                'employer_rate' => $rate->employer_rate ?? 0,
            ]);
        return response()->json($table);
    }

    public function taxTable($periodType)
    {
        $table = GovernmentRate::where('type', 'tax')
            ->where('is_active', true)
            ->orderBy('min_salary')
            ->get()
            ->map(fn($rate) => [
                'id' => $rate->id,
                'min_income' => $rate->min_salary,
                'max_income' => $rate->max_salary,
                'tax_rate' => $rate->employee_rate ?? 0,
                'fixed_tax' => $rate->employee_fixed ?? 0,
                'period_type' => $periodType,
            ]);
        return response()->json($table);
    }

    public function computeContributions(Request $request)
    {
        $salary = $request->input('salary', 0);

        // SSS Computation using new GovernmentRate model
        $sss = GovernmentRate::getContributionForSalary('sss', $salary);

        // PhilHealth Computation
        $philhealth = GovernmentRate::getContributionForSalary('philhealth', $salary);

        // Pag-IBIG Computation
        $pagibig = GovernmentRate::getContributionForSalary('pagibig', $salary);

        $contributions = [
            'salary' => $salary,
            'sss' => [
                'employee_share' => $sss['employee'] ?? 0,
                'employer_share' => $sss['employer'] ?? 0,
                'total' => ($sss['employee'] ?? 0) + ($sss['employer'] ?? 0),
            ],
            'philhealth' => [
                'employee_share' => $philhealth['employee'] ?? 0,
                'employer_share' => $philhealth['employer'] ?? 0,
                'total' => ($philhealth['employee'] ?? 0) + ($philhealth['employer'] ?? 0),
            ],
            'pagibig' => [
                'employee_share' => $pagibig['employee'] ?? 0,
                'employer_share' => $pagibig['employer'] ?? 0,
                'total' => ($pagibig['employee'] ?? 0) + ($pagibig['employer'] ?? 0),
            ],
            'total_employee_deductions' => ($sss['employee'] ?? 0) +
                ($philhealth['employee'] ?? 0) +
                ($pagibig['employee'] ?? 0),
        ];

        return response()->json($contributions);
    }
}
