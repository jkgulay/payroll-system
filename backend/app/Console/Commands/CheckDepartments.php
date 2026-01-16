<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDepartments extends Command
{
    protected $signature = 'check:departments';
    protected $description = 'Check all departments for 13th month pay readiness';

    public function handle()
    {
        $this->info('Checking all departments for employees and 2025 payroll data...');
        $this->info('');

        $departments = DB::table('employees')
            ->select('department')
            ->distinct()
            ->whereNotNull('department')
            ->orderBy('department')
            ->get();

        $headers = ['Department', 'Active Employees', 'With 2025 Payroll', 'Status'];
        $rows = [];

        foreach ($departments as $dept) {
            $empCount = DB::table('employees')
                ->where('department', $dept->department)
                ->whereNotIn('activity_status', ['resigned', 'terminated'])
                ->count();

            $payrollCount = DB::table('payroll_items')
                ->join('employees', 'employees.id', '=', 'payroll_items.employee_id')
                ->join('payrolls', 'payrolls.id', '=', 'payroll_items.payroll_id')
                ->where('employees.department', $dept->department)
                ->whereIn('payrolls.status', ['paid', 'finalized'])
                ->where(function($q) {
                    $q->whereBetween('payrolls.period_start', ['2025-01-01', '2025-12-31'])
                      ->orWhereBetween('payrolls.period_end', ['2025-01-01', '2025-12-31']);
                })
                ->distinct('payroll_items.employee_id')
                ->count('payroll_items.employee_id');

            $status = $payrollCount > 0 ? '✓ Ready' : '✗ No 2025 payroll';
            
            $rows[] = [
                $dept->department,
                $empCount,
                $payrollCount,
                $status
            ];
        }

        $this->table($headers, $rows);
        
        $ready = collect($rows)->filter(fn($r) => str_contains($r[3], '✓'))->count();
        $notReady = collect($rows)->filter(fn($r) => str_contains($r[3], '✗'))->count();
        
        $this->info('');
        $this->info("Summary: {$ready} departments ready, {$notReady} departments without 2025 payroll data");

        return 0;
    }
}
