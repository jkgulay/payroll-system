<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SSSContributionTable;
use App\Models\PhilHealthContributionTable;
use App\Models\PagibigContributionTable;
use App\Models\TaxWithholdingTable;
use Illuminate\Http\Request;

class GovernmentController extends Controller
{
    public function sssTable()
    {
        $table = SSSContributionTable::orderBy('min_salary')->get();
        return response()->json($table);
    }

    public function philhealthTable()
    {
        $table = PhilHealthContributionTable::orderBy('min_salary')->get();
        return response()->json($table);
    }

    public function pagibigTable()
    {
        $table = PagibigContributionTable::orderBy('min_salary')->get();
        return response()->json($table);
    }

    public function taxTable($periodType)
    {
        $table = TaxWithholdingTable::where('period_type', $periodType)->orderBy('min_income')->get();
        return response()->json($table);
    }

    public function computeContributions(Request $request)
    {
        $salary = $request->input('salary', 0);

        // SSS Computation
        $sss = SSSContributionTable::where('min_salary', '<=', $salary)
            ->where('max_salary', '>=', $salary)
            ->first();

        // PhilHealth Computation
        $philhealth = PhilHealthContributionTable::where('min_salary', '<=', $salary)
            ->where('max_salary', '>=', $salary)
            ->first();

        // Pag-IBIG Computation
        $pagibig = PagibigContributionTable::where('min_salary', '<=', $salary)
            ->where('max_salary', '>=', $salary)
            ->first();

        $contributions = [
            'salary' => $salary,
            'sss' => [
                'employee_share' => $sss ? $sss->employee_share : 0,
                'employer_share' => $sss ? $sss->employer_share : 0,
                'total' => $sss ? $sss->total_contribution : 0,
            ],
            'philhealth' => [
                'employee_share' => $philhealth ? $philhealth->employee_share : 0,
                'employer_share' => $philhealth ? $philhealth->employer_share : 0,
                'total' => $philhealth ? $philhealth->total_contribution : 0,
            ],
            'pagibig' => [
                'employee_share' => $pagibig ? $pagibig->employee_share : 0,
                'employer_share' => $pagibig ? $pagibig->employer_share : 0,
                'total' => $pagibig ? $pagibig->total_contribution : 0,
            ],
            'total_employee_deductions' => ($sss ? $sss->employee_share : 0) +
                ($philhealth ? $philhealth->employee_share : 0) +
                ($pagibig ? $pagibig->employee_share : 0),
        ];

        return response()->json($contributions);
    }
}
