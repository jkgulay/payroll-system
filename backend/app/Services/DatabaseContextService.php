<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Attendance;
use App\Models\EmployeeLeave;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseContextService
{
    /**
     * Get relevant database context based on query intent
     */
    public function getContextForQuery(string $query, array $intent): array
    {
        $context = [];
        
        switch ($intent['intent']) {
            case 'employee_count':
            case 'employee_search':
                $context = $this->getEmployeeContext($query);
                break;
                
            case 'payroll_expense':
            case 'salary_info':
                $context = $this->getPayrollContext($query);
                break;
                
            case 'attendance':
                $context = $this->getAttendanceContext($query);
                break;
                
            case 'leave':
                $context = $this->getLeaveContext($query);
                break;
                
            case 'overtime':
                $context = $this->getOvertimeContext($query);
                break;
                
            case 'tax_compliance':
                $context = $this->getTaxComplianceContext($query);
                break;
                
            case 'documents':
                $context = $this->getDocumentContext($query);
                break;
                
            case 'system_help':
                $context = $this->getSystemHelpContext();
                break;
                
            default:
                $context = $this->getGeneralContext();
        }
        
        return $context;
    }

    /**
     * Get employee-related context
     */
    protected function getEmployeeContext(string $query): array
    {
        $context = [
            'total_employees' => Employee::count(),
            'active_employees' => Employee::where('is_active', true)->count(),
            'inactive_employees' => Employee::where('is_active', false)->count(),
        ];

        // Recent hires (last 3 months)
        if (strpos($query, 'joined') !== false || strpos($query, 'new') !== false || strpos($query, 'hired') !== false) {
            $context['recent_hires'] = Employee::where('date_hired', '>=', Carbon::now()->subMonths(3))
                ->with('positionRate:id,position_name')
                ->select('id', 'first_name', 'last_name', 'position_id', 'date_hired')
                ->get()
                ->map(function ($emp) {
                    return [
                        'name' => $emp->first_name . ' ' . $emp->last_name,
                        'position' => $emp->position,
                        'date_hired' => $emp->date_hired->format('Y-m-d'),
                    ];
                });
        }

        // Projects summary
        $context['projects_summary'] = DB::table('employees')
            ->join('projects', 'employees.project_id', '=', 'projects.id')
            ->where('employees.is_active', true)
            ->whereNull('employees.deleted_at')
            ->select('projects.name', DB::raw('count(*) as employee_count'))
            ->groupBy('projects.name')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'employee_count' => $item->employee_count
                ];
            })
            ->toArray();

        return $context;
    }

    /**
     * Get payroll-related context
     */
    protected function getPayrollContext(string $query): array
    {
        $context = [];
        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;

        // Extract specific month/year from query
        if (preg_match('/\b(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{4})/i', $query, $matches)) {
            $month = Carbon::parse($matches[1])->month;
            $year = $matches[2];
            
            $payrollData = DB::table('payrolls')
                ->join('payroll_items', 'payrolls.id', '=', 'payroll_items.payroll_id')
                ->whereYear('payrolls.period_start', $year)
                ->whereMonth('payrolls.period_start', $month)
                ->whereNull('payrolls.deleted_at')
                ->selectRaw('SUM(payrolls.total_gross) as total_gross, SUM(payrolls.total_net) as total_net, COUNT(DISTINCT payroll_items.employee_id) as employee_count')
                ->first();
                
            $context['period_payroll'] = [
                'period' => Carbon::create($year, $month)->format('F Y'),
                'total_gross_pay' => $payrollData->total_gross ?? 0,
                'total_net_pay' => $payrollData->total_net ?? 0,
                'employee_count' => $payrollData->employee_count ?? 0,
            ];
        }

        // Quarterly comparison
        if (strpos($query, 'q3') !== false || strpos($query, 'q4') !== false || strpos($query, 'quarter') !== false) {
            $q3Data = Payroll::whereYear('period_start', $currentYear)
                ->whereMonth('period_start', '>=', 7)
                ->whereMonth('period_start', '<=', 9)
                ->selectRaw('SUM(total_gross) as total_gross, SUM(total_net) as total_net')
                ->first();
                
            $q4Data = Payroll::whereYear('period_start', $currentYear)
                ->whereMonth('period_start', '>=', 10)
                ->whereMonth('period_start', '<=', 12)
                ->selectRaw('SUM(total_gross) as total_gross, SUM(total_net) as total_net')
                ->first();
                
            $context['quarterly_comparison'] = [
                'q3' => [
                    'total_gross' => $q3Data->total_gross ?? 0,
                    'total_net' => $q3Data->total_net ?? 0,
                ],
                'q4' => [
                    'total_gross' => $q4Data->total_gross ?? 0,
                    'total_net' => $q4Data->total_net ?? 0,
                ],
                'difference' => [
                    'gross' => ($q4Data->total_gross ?? 0) - ($q3Data->total_gross ?? 0),
                    'net' => ($q4Data->total_net ?? 0) - ($q3Data->total_net ?? 0),
                ]
            ];
        }

        // Top earners
        if (strpos($query, 'top') !== false || strpos($query, 'highest') !== false) {
            $context['top_earners'] = Employee::with('positionRate:id,position_name')
                ->orderBy('basic_salary', 'desc')
                ->take(5)
                ->select('id', 'first_name', 'last_name', 'position_id', 'basic_salary')
                ->get()
                ->map(function ($emp) {
                    return [
                        'name' => $emp->first_name . ' ' . $emp->last_name,
                        'position' => $emp->position,
                        'basic_salary' => $emp->basic_salary,
                    ];
                });
        }

        // Average salary by project
        if (strpos($query, 'average') !== false) {
            $context['average_salaries'] = DB::table('employees')
                ->join('projects', 'employees.project_id', '=', 'projects.id')
                ->select('projects.name', DB::raw('AVG(employees.basic_salary) as avg_salary'), DB::raw('COUNT(*) as emp_count'))
                ->whereNull('employees.deleted_at')
                ->groupBy('projects.name')
                ->get()
                ->map(function ($item) {
                    return [
                        'project' => $item->name,
                        'average_salary' => round($item->avg_salary, 2),
                        'employee_count' => $item->emp_count,
                    ];
                })
                ->toArray();
        }

        // Current month overtime
        if (strpos($query, 'overtime') !== false) {
            $overtimeTotal = DB::table('payroll_items')
                ->join('payroll', 'payroll_items.payroll_id', '=', 'payroll.id')
                ->whereYear('payroll.period_start', $currentYear)
                ->whereMonth('payroll.period_start', $currentMonth)
                ->whereNull('payroll.deleted_at')
                ->sum('payroll_items.overtime_pay');
                
            $context['overtime'] = [
                'period' => Carbon::create($currentYear, $currentMonth)->format('F Y'),
                'total_overtime_pay' => $overtimeTotal ?? 0,
            ];
        }

        return $context;
    }

    /**
     * Get attendance-related context
     */
    protected function getAttendanceContext(string $query): array
    {
        $context = [];

        // Yesterday's absences
        if (strpos($query, 'yesterday') !== false || strpos($query, 'absent') !== false) {
            $yesterday = Carbon::yesterday();
            $absences = Attendance::whereDate('attendance_date', $yesterday)
                ->where('status', 'absent')
                ->with('employee:id,first_name,last_name')
                ->select('id', 'employee_id', 'attendance_date', 'status')
                ->get()
                ->map(function ($att) {
                    return [
                        'name' => $att->employee ? ($att->employee->first_name . ' ' . $att->employee->last_name) : 'Unknown',
                        'date' => $att->attendance_date->format('Y-m-d'),
                    ];
                });
                
            $context['yesterday_absences'] = [
                'date' => $yesterday->format('F d, Y'),
                'count' => $absences->count(),
                'employees' => $absences,
            ];
        }

        // Low attendance this month
        if (strpos($query, 'low attendance') !== false) {
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();
            
            $lowAttendance = DB::table('attendance')
                ->select('employee_id')
                ->selectRaw('COUNT(CASE WHEN status = \'present\' THEN 1 END) as present_days')
                ->selectRaw('COUNT(CASE WHEN status = \'absent\' THEN 1 END) as absent_days')
                ->selectRaw('COUNT(*) as total_days')
                ->whereBetween('attendance_date', [$startOfMonth, $endOfMonth])
                ->whereNull('deleted_at')
                ->groupBy('employee_id')
                ->havingRaw('(CAST(present_days AS FLOAT) / total_days) < 0.8')
                ->get();
                
            $employeeIds = $lowAttendance->pluck('employee_id');
            $employees = Employee::whereIn('id', $employeeIds)
                ->select('id', 'first_name', 'last_name')
                ->get();
                
            $context['low_attendance'] = $lowAttendance->map(function ($att) use ($employees) {
                $employee = $employees->firstWhere('id', $att->employee_id);
                return [
                    'name' => $employee ? $employee->first_name . ' ' . $employee->last_name : 'Unknown',
                    'present_days' => $att->present_days,
                    'absent_days' => $att->absent_days,
                    'attendance_rate' => round(($att->present_days / $att->total_days) * 100, 2) . '%',
                ];
            });
        }

        return $context;
    }

    /**
     * Get leave-related context
     */
    protected function getLeaveContext(string $query): array
    {
        $context = [];

        // Pending leave requests
        if (strpos($query, 'pending') !== false) {
            $pending = EmployeeLeave::where('status', 'pending')
                ->with(['employee:id,first_name,last_name', 'leaveType:id,name'])
                ->get()
                ->map(function ($leave) {
                    return [
                        'employee' => $leave->employee->first_name . ' ' . $leave->employee->last_name,
                        'type' => $leave->leaveType->name ?? 'Unknown',
                        'start_date' => $leave->leave_date_from->format('Y-m-d'),
                        'end_date' => $leave->leave_date_to->format('Y-m-d'),
                        'days' => $leave->number_of_days,
                    ];
                });
                
            $context['pending_leave_requests'] = [
                'count' => $pending->count(),
                'requests' => $pending,
            ];
        }

        return $context;
    }

    /**
     * Get overtime-related context
     */
    protected function getOvertimeContext(string $query): array
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
        
        $overtimeData = DB::table('payroll_items')
            ->join('payroll', 'payroll_items.payroll_id', '=', 'payroll.id')
            ->whereYear('payroll.period_start', $currentYear)
            ->whereMonth('payroll.period_start', $currentMonth)
            ->whereNull('payroll.deleted_at')
            ->selectRaw('SUM(payroll_items.overtime_hours) as total_hours, SUM(payroll_items.overtime_pay) as total_pay, COUNT(DISTINCT payroll_items.employee_id) as employee_count')
            ->first();

        return [
            'period' => Carbon::now()->format('F Y'),
            'total_overtime_hours' => $overtimeData->total_hours ?? 0,
            'total_overtime_pay' => $overtimeData->total_pay ?? 0,
            'employees_with_overtime' => $overtimeData->employee_count ?? 0,
        ];
    }

    /**
     * Get tax and compliance context
     */
    protected function getTaxComplianceContext(string $query): array
    {
        $currentYear = Carbon::now()->year;
        
        $taxData = DB::table('payroll_items')
            ->join('payroll', 'payroll_items.payroll_id', '=', 'payroll.id')
            ->whereYear('payroll.period_start', $currentYear)
            ->whereNull('payroll.deleted_at')
            ->selectRaw('
                SUM(payroll_items.sss_contribution) as total_sss,
                SUM(payroll_items.philhealth_contribution) as total_philhealth,
                SUM(payroll_items.pagibig_contribution) as total_hdmf,
                SUM(payroll_items.withholding_tax) as total_tax,
                SUM(payroll_items.total_deductions) as total_deductions
            ')
            ->first();

        return [
            'year' => $currentYear,
            'contributions' => [
                'sss' => $taxData->total_sss ?? 0,
                'philhealth' => $taxData->total_philhealth ?? 0,
                'hdmf' => $taxData->total_hdmf ?? 0,
                'withholding_tax' => $taxData->total_tax ?? 0,
                'total_deductions' => $taxData->total_deductions ?? 0,
            ],
        ];
    }

    /**
     * Get document context
     */
    protected function getDocumentContext(string $query): array
    {
        // Count employees missing required documents
        $missingDocs = Employee::where(function ($q) {
            $q->whereNull('tin_number')
              ->orWhereNull('sss_number')
              ->orWhereNull('philhealth_number')
              ->orWhereNull('pagibig_number');
        })->select('id', 'first_name', 'last_name', 'tin_number', 'sss_number', 'philhealth_number', 'pagibig_number')
        ->get()
        ->map(function ($emp) {
            $missing = [];
            if (!$emp->tin_number) $missing[] = 'TIN';
            if (!$emp->sss_number) $missing[] = 'SSS';
            if (!$emp->philhealth_number) $missing[] = 'PhilHealth';
            if (!$emp->pagibig_number) $missing[] = 'Pag-IBIG';
            
            return [
                'name' => $emp->first_name . ' ' . $emp->last_name,
                'missing_documents' => $missing,
            ];
        });

        return [
            'employees_with_missing_documents' => $missingDocs->count(),
            'details' => $missingDocs,
        ];
    }

    /**
     * Get system help context
     */
    protected function getSystemHelpContext(): array
    {
        return [
            'system_info' => [
                'name' => env('APP_NAME'),
                'payroll_frequency' => env('PAYROLL_FREQUENCY'),
                'standard_work_hours' => env('STANDARD_WORK_HOURS'),
                'minimum_wage' => env('MINIMUM_WAGE_NCR'),
            ],
            'help_topics' => [
                'Payroll Processing' => 'Process payroll by navigating to Payroll > Process Payroll. Select the pay period and review employee data before generating.',
                'Overtime Calculation' => 'Overtime is calculated at 1.25x for regular OT and 1.3x for special holidays based on hourly rate.',
                'Leave Accrual' => 'Employees accrue leave based on tenure. Regular employees get 1.25 days per month.',
                'Tax Computation' => 'Withholding tax is computed based on the BIR tax table for the current year.',
            ],
        ];
    }

    /**
     * Get general context
     */
    protected function getGeneralContext(): array
    {
        return [
            'total_employees' => Employee::count(),
            'active_employees' => Employee::where('is_active', true)->count(),
            'current_date' => Carbon::now()->format('F d, Y'),
        ];
    }
}
