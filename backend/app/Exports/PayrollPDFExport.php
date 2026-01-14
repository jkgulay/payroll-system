<?php

namespace App\Exports;

use App\Models\Payroll;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PayrollPDFExport
{
    protected $payroll;
    protected $filters;

    public function __construct(Payroll $payroll, array $filters = [])
    {
        $this->payroll = $payroll;
        $this->filters = $filters;
    }

    /**
     * Generate comprehensive payroll PDF
     */
    public function generate(array $signatureData = [])
    {
        try {
            // Increase memory limit for PDF generation
            ini_set('memory_limit', '256M');
            
            Log::info('Starting PDF generation', [
                'payroll_id' => $this->payroll->id,
                'filters' => $this->filters
            ]);

            $payrollItems = $this->payroll->payrollItems()
                ->with([
                    'employee:id,employee_number,first_name,middle_name,last_name,position_id,project_id',
                    'employee.project:id,name,code',
                    'employee.positionRate:id,position_name,daily_rate'
                ])
                ->get();

            Log::info('Payroll items loaded', ['count' => $payrollItems->count()]);

            // Apply filters
            if (!empty($this->filters['employee_id'])) {
                $payrollItems = $payrollItems->where('employee_id', $this->filters['employee_id']);
            }

            if (!empty($this->filters['project_id'])) {
                $payrollItems = $payrollItems->filter(function ($item) {
                    return $item->employee->project_id == $this->filters['project_id'];
                });
            }

            if (!empty($this->filters['location'])) {
                $payrollItems = $payrollItems->filter(function ($item) {
                    return $item->employee->project
                        && stripos($item->employee->project->name, $this->filters['location']) !== false;
                });
            }

            // Calculate totals
            $totals = [
                'gross_pay' => $payrollItems->sum('gross_pay'),
                'total_deductions' => $payrollItems->sum('total_deductions'),
                'net_pay' => $payrollItems->sum('net_pay'),
                'basic_pay' => $payrollItems->sum('basic_pay'),
                'overtime_pay' => $payrollItems->sum('overtime_pay'),
                'total_allowances' => $payrollItems->sum('total_allowances'),
                'sss' => $payrollItems->sum('sss_contribution'),
                'philhealth' => $payrollItems->sum('philhealth_contribution'),
                'pagibig' => $payrollItems->sum('pagibig_contribution'),
                'tax' => $payrollItems->sum('withholding_tax'),
                'loans' => $payrollItems->sum('total_loan_deductions'),
                'other_deductions' => $payrollItems->sum('total_other_deductions'),
            ];

            // Get project info if filtering by project
            $project = null;
            if (!empty($this->filters['project_id'])) {
                $project = Project::find($this->filters['project_id']);
            }

            $data = [
                'payroll' => $this->payroll,
                'items' => $payrollItems,
                'totals' => $totals,
                'signatures' => $signatureData,
                'filter_type' => $this->getFilterType(),
                'company_name' => config('app.company_name', 'Construction Company'),
                'project' => $project,
            ];

            Log::info('Loading PDF view');
            $pdf = Pdf::loadView('pdfs.payroll-comprehensive', $data);
            $pdf->setPaper('legal', 'landscape');

            // Optimize for production environment
            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', false);
            $pdf->setOption('debugKeepTemp', false);

            Log::info('PDF generated successfully');
            return $pdf;
        } catch (\Exception $e) {
            Log::error('PayrollPDFExport Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payroll_id' => $this->payroll->id
            ]);
            throw $e;
        }
    }

    /**
     * Generate payroll by employee
     */
    public function generateByEmployee(int $employeeId, array $signatureData = [])
    {
        $this->filters['employee_id'] = $employeeId;
        return $this->generate($signatureData);
    }

    /**
     * Generate payroll by project
     */
    public function generateByProject(int $projectId, array $signatureData = [])
    {
        $this->filters['project_id'] = $projectId;
        return $this->generate($signatureData);
    }

    /**
     * Generate payroll by location
     */
    public function generateByLocation(string $location, array $signatureData = [])
    {
        $this->filters['location'] = $location;
        return $this->generate($signatureData);
    }

    /**
     * Get filter type description
     */
    protected function getFilterType(): string
    {
        if (!empty($this->filters['employee_id'])) {
            return 'Employee';
        }
        if (!empty($this->filters['project_id'])) {
            return 'Project';
        }
        if (!empty($this->filters['location'])) {
            return 'Location';
        }
        return 'All Employees';
    }
}
