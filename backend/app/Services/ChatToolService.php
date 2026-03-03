<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\Attendance;
use App\Models\EmployeeLeave;
use App\Models\EmployeeLeaveCredit;
use App\Models\Project;
use App\Models\PositionRate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ChatToolService
{
    /**
     * Define all available tools for the AI
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_employee_statistics',
                    'description' => 'Get overall employee statistics including total count, active/inactive breakdown, and recent hires',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'include_recent_hires' => [
                                'type' => 'boolean',
                                'description' => 'Whether to include list of employees hired in the last 3 months',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_employees',
                    'description' => 'Search for employees by name, project, position, or status',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'description' => 'Employee name to search for (partial match)',
                            ],
                            'project_name' => [
                                'type' => 'string',
                                'description' => 'Filter by project name',
                            ],
                            'position' => [
                                'type' => 'string',
                                'description' => 'Filter by position/job title',
                            ],
                            'is_active' => [
                                'type' => 'boolean',
                                'description' => 'Filter by active status (true for active, false for inactive)',
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Maximum number of results to return (default: 20)',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_employee_details',
                    'description' => 'Get detailed information about a specific employee including salary, position, and employment info',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'employee_id' => [
                                'type' => 'integer',
                                'description' => 'The employee ID',
                            ],
                            'employee_name' => [
                                'type' => 'string',
                                'description' => 'Full or partial employee name to search',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_payroll_summary',
                    'description' => 'Get payroll summary statistics including totals, counts, and breakdowns by period',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'year' => [
                                'type' => 'integer',
                                'description' => 'Year to filter payroll records (e.g., 2026)',
                            ],
                            'month' => [
                                'type' => 'integer',
                                'description' => 'Month to filter payroll records (1-12)',
                            ],
                            'include_breakdown' => [
                                'type' => 'boolean',
                                'description' => 'Include monthly breakdown of payroll',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_recent_payrolls',
                    'description' => 'Get the most recent payroll records with details',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'description' => 'Number of recent payrolls to return (default: 10)',
                            ],
                            'status' => [
                                'type' => 'string',
                                'description' => 'Filter by status (draft, pending, approved, paid)',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_payroll_details',
                    'description' => 'Get detailed information about a specific payroll including all items and employee payments',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'payroll_id' => [
                                'type' => 'integer',
                                'description' => 'The payroll ID to get details for',
                            ],
                        ],
                        'required' => ['payroll_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_attendance_summary',
                    'description' => 'Get attendance summary for employees within a date range',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'employee_id' => [
                                'type' => 'integer',
                                'description' => 'Filter by specific employee ID',
                            ],
                            'date_from' => [
                                'type' => 'string',
                                'description' => 'Start date (YYYY-MM-DD format)',
                            ],
                            'date_to' => [
                                'type' => 'string',
                                'description' => 'End date (YYYY-MM-DD format)',
                            ],
                            'include_late' => [
                                'type' => 'boolean',
                                'description' => 'Include late arrivals in the summary',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_leave_information',
                    'description' => 'Get leave balances, pending requests, or leave history for employees',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'employee_id' => [
                                'type' => 'integer',
                                'description' => 'Filter by specific employee ID',
                            ],
                            'status' => [
                                'type' => 'string',
                                'description' => 'Filter by leave status (pending, approved, rejected)',
                            ],
                            'include_balances' => [
                                'type' => 'boolean',
                                'description' => 'Include leave credit balances',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_project_summary',
                    'description' => 'Get list of projects with employee counts and details',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_name' => [
                                'type' => 'string',
                                'description' => 'Filter by specific project name',
                            ],
                            'include_employees' => [
                                'type' => 'boolean',
                                'description' => 'Include list of employees in each project',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'calculate_payroll_totals',
                    'description' => 'Calculate total payroll expenses including gross pay, deductions, and net pay for a period',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'year' => [
                                'type' => 'integer',
                                'description' => 'Year for calculation',
                            ],
                            'month' => [
                                'type' => 'integer',
                                'description' => 'Month for calculation (1-12)',
                            ],
                            'quarter' => [
                                'type' => 'integer',
                                'description' => 'Quarter for calculation (1-4, use instead of month)',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_salary_information',
                    'description' => 'Get salary statistics including average, highest, and lowest salaries',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'by_position' => [
                                'type' => 'boolean',
                                'description' => 'Break down salaries by position',
                            ],
                            'by_project' => [
                                'type' => 'boolean',
                                'description' => 'Break down salaries by project',
                            ],
                            'top_earners' => [
                                'type' => 'integer',
                                'description' => 'Number of top earners to list',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_government_contributions',
                    'description' => 'Get government contribution information (SSS, PhilHealth, Pag-IBIG)',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'employee_id' => [
                                'type' => 'integer',
                                'description' => 'Filter by specific employee',
                            ],
                            'year' => [
                                'type' => 'integer',
                                'description' => 'Year for contribution summary',
                            ],
                            'month' => [
                                'type' => 'integer',
                                'description' => 'Month for contribution summary',
                            ],
                        ],
                        'required' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call and return the result
     */
    public function executeTool(string $toolName, array $arguments): array
    {
        Log::info('Executing Chat Tool', [
            'tool' => $toolName,
            'arguments' => $arguments,
        ]);

        try {
            switch ($toolName) {
                case 'get_employee_statistics':
                    return $this->getEmployeeStatistics($arguments);
                case 'search_employees':
                    return $this->searchEmployees($arguments);
                case 'get_employee_details':
                    return $this->getEmployeeDetails($arguments);
                case 'get_payroll_summary':
                    return $this->getPayrollSummary($arguments);
                case 'get_recent_payrolls':
                    return $this->getRecentPayrolls($arguments);
                case 'get_payroll_details':
                    return $this->getPayrollDetails($arguments);
                case 'get_attendance_summary':
                    return $this->getAttendanceSummary($arguments);
                case 'get_leave_information':
                    return $this->getLeaveInformation($arguments);
                case 'get_project_summary':
                    return $this->getProjectSummary($arguments);
                case 'calculate_payroll_totals':
                    return $this->calculatePayrollTotals($arguments);
                case 'get_salary_information':
                    return $this->getSalaryInformation($arguments);
                case 'get_government_contributions':
                    return $this->getGovernmentContributions($arguments);
                default:
                    return ['error' => 'Unknown tool: ' . $toolName];
            }
        } catch (\Exception $e) {
            Log::error('Chat Tool Execution Error', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
            ]);
            return ['error' => 'Failed to execute tool: ' . $e->getMessage()];
        }
    }

    // ==================== Tool Implementations ====================

    protected function getEmployeeStatistics(array $args): array
    {
        $stats = [
            'total_employees' => Employee::count(),
            'active_employees' => Employee::where('is_active', true)->count(),
            'inactive_employees' => Employee::where('is_active', false)->count(),
        ];

        // By employment type if available
        $stats['by_status'] = Employee::selectRaw('is_active, COUNT(*) as count')
            ->groupBy('is_active')
            ->get()
            ->map(fn($item) => [
                'status' => $item->is_active ? 'Active' : 'Inactive',
                'count' => $item->count,
            ])
            ->toArray();

        if ($args['include_recent_hires'] ?? false) {
            $stats['recent_hires'] = Employee::where('date_hired', '>=', Carbon::now()->subMonths(3))
                ->with(['project:id,name', 'positionRate:id,position_name'])
                ->select('id', 'first_name', 'last_name', 'position_id', 'project_id', 'date_hired')
                ->orderBy('date_hired', 'desc')
                ->limit(20)
                ->get()
                ->map(fn($emp) => [
                    'id' => $emp->id,
                    'name' => $emp->first_name . ' ' . $emp->last_name,
                    'position' => $emp->positionRate->position_name ?? 'N/A',
                    'project' => $emp->project->name ?? 'N/A',
                    'date_hired' => $emp->date_hired ? $emp->date_hired->format('M d, Y') : 'N/A',
                ])
                ->toArray();
        }

        return $stats;
    }

    protected function searchEmployees(array $args): array
    {
        $query = Employee::query()->with(['project:id,name', 'positionRate:id,position_name,daily_rate']);
        
        if (isset($args['name'])) {
            $name = $args['name'];
            $query->where(function ($q) use ($name) {
                $q->where('first_name', 'ILIKE', "%{$name}%")
                  ->orWhere('last_name', 'ILIKE', "%{$name}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$name}%"]);
            });
        }

        if (isset($args['project_name'])) {
            $query->whereHas('project', function ($q) use ($args) {
                $q->where('name', 'ILIKE', "%{$args['project_name']}%");
            });
        }

        if (isset($args['position'])) {
            $query->whereHas('positionRate', function ($q) use ($args) {
                $q->where('position_name', 'ILIKE', "%{$args['position']}%");
            });
        }

        if (isset($args['is_active'])) {
            $query->where('is_active', $args['is_active']);
        }

        $limit = min($args['limit'] ?? 20, 50);
        
        $employees = $query->select('id', 'first_name', 'last_name', 'position_id', 'project_id', 'is_active', 'date_hired')
            ->limit($limit)
            ->get()
            ->map(fn($emp) => [
                'id' => $emp->id,
                'name' => $emp->first_name . ' ' . $emp->last_name,
                'position' => $emp->positionRate->position_name ?? 'N/A',
                'project' => $emp->project->name ?? 'N/A',
                'daily_rate' => $emp->positionRate->daily_rate ?? 0,
                'is_active' => $emp->is_active,
                'date_hired' => $emp->date_hired ? $emp->date_hired->format('M d, Y') : 'N/A',
            ])
            ->toArray();

        return [
            'count' => count($employees),
            'employees' => $employees,
        ];
    }

    protected function getEmployeeDetails(array $args): array
    {
        $employee = null;

        if (isset($args['employee_id'])) {
            $employee = Employee::with(['project', 'positionRate', 'governmentInfo'])
                ->find($args['employee_id']);
        } elseif (isset($args['employee_name'])) {
            $name = $args['employee_name'];
            $employee = Employee::with(['project', 'positionRate', 'governmentInfo'])
                ->where(function ($q) use ($name) {
                    $q->where('first_name', 'ILIKE', "%{$name}%")
                      ->orWhere('last_name', 'ILIKE', "%{$name}%")
                      ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$name}%"]);
                })
                ->first();
        }

        if (!$employee) {
            return ['error' => 'Employee not found'];
        }

        return [
            'id' => $employee->id,
            'name' => $employee->first_name . ' ' . $employee->last_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'position' => $employee->positionRate->position_name ?? 'N/A',
            'daily_rate' => $employee->positionRate->daily_rate ?? 0,
            'project' => $employee->project->name ?? 'N/A',
            'date_hired' => $employee->date_hired ? $employee->date_hired->format('M d, Y') : 'N/A',
            'is_active' => $employee->is_active,
            'employment_status' => $employee->employment_status ?? 'N/A',
            'government_info' => $employee->governmentInfo ? [
                'sss' => $employee->governmentInfo->sss_number ?? 'N/A',
                'philhealth' => $employee->governmentInfo->philhealth_number ?? 'N/A',
                'pagibig' => $employee->governmentInfo->pagibig_number ?? 'N/A',
                'tin' => $employee->governmentInfo->tin_number ?? 'N/A',
            ] : null,
        ];
    }

    protected function getPayrollSummary(array $args): array
    {
        $query = Payroll::query();

        $year = $args['year'] ?? Carbon::now()->year;
        $query->whereYear('period_start', $year);

        if (isset($args['month'])) {
            $query->whereMonth('period_start', $args['month']);
        }

        $summary = [
            'year' => $year,
            'month' => $args['month'] ?? 'All',
            'total_payrolls' => $query->count(),
            'total_gross_pay' => $query->sum('total_gross'),
            'total_net_pay' => $query->sum('total_net'),
            'total_deductions' => $query->sum('total_deductions'),
        ];

        // Status breakdown
        $summary['by_status'] = Payroll::whereYear('period_start', $year)
            ->when(isset($args['month']), fn($q) => $q->whereMonth('period_start', $args['month']))
            ->selectRaw('status, COUNT(*) as count, SUM(total_gross) as total_gross')
            ->groupBy('status')
            ->get()
            ->toArray();

        if ($args['include_breakdown'] ?? false) {
            $summary['monthly_breakdown'] = Payroll::whereYear('period_start', $year)
                ->selectRaw("EXTRACT(MONTH FROM period_start)::integer as month, COUNT(*) as count, SUM(total_gross) as gross, SUM(total_net) as net")
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn($item) => [
                    'month' => Carbon::create()->month($item->month)->format('F'),
                    'payroll_count' => $item->count,
                    'total_gross' => $item->gross,
                    'total_net' => $item->net,
                ])
                ->toArray();
        }

        return $summary;
    }

    protected function getRecentPayrolls(array $args): array
    {
        $query = Payroll::query()->orderBy('period_start', 'desc');

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        $limit = min($args['limit'] ?? 10, 30);

        $payrolls = $query->limit($limit)
            ->get(['id', 'period_start', 'period_end', 'total_gross', 'total_net', 'total_deductions', 'status', 'created_at'])
            ->map(fn($p) => [
                'id' => $p->id,
                'period' => $p->period_start->format('M d') . ' - ' . $p->period_end->format('M d, Y'),
                'total_gross' => number_format($p->total_gross, 2),
                'total_net' => number_format($p->total_net, 2),
                'total_deductions' => number_format($p->total_deductions, 2),
                'status' => $p->status,
                'created_at' => $p->created_at->format('M d, Y'),
            ])
            ->toArray();

        return [
            'count' => count($payrolls),
            'payrolls' => $payrolls,
        ];
    }

    protected function getPayrollDetails(array $args): array
    {
        $payroll = Payroll::with(['payrollItems.employee:id,first_name,last_name'])
            ->find($args['payroll_id']);

        if (!$payroll) {
            return ['error' => 'Payroll not found'];
        }

        return [
            'id' => $payroll->id,
            'period' => $payroll->period_start->format('M d') . ' - ' . $payroll->period_end->format('M d, Y'),
            'status' => $payroll->status,
            'total_gross' => $payroll->total_gross,
            'total_net' => $payroll->total_net,
            'total_deductions' => $payroll->total_deductions,
            'employee_count' => $payroll->payrollItems->count(),
            'employees' => $payroll->payrollItems->take(20)->map(fn($item) => [
                'name' => $item->employee->first_name . ' ' . $item->employee->last_name,
                'gross_pay' => $item->gross_pay,
                'net_pay' => $item->net_pay,
                'days_worked' => $item->days_worked,
            ])->toArray(),
        ];
    }

    protected function getAttendanceSummary(array $args): array
    {
        $dateFrom = isset($args['date_from']) ? Carbon::parse($args['date_from']) : Carbon::now()->startOfMonth();
        $dateTo = isset($args['date_to']) ? Carbon::parse($args['date_to']) : Carbon::now();

        $query = Attendance::whereBetween('date', [$dateFrom, $dateTo]);

        if (isset($args['employee_id'])) {
            $query->where('employee_id', $args['employee_id']);
        }

        $totalRecords = $query->count();
        $presentCount = (clone $query)->where('status', 'present')->count();
        $absentCount = (clone $query)->where('status', 'absent')->count();
        $lateCount = (clone $query)->where('is_late', true)->count();

        $summary = [
            'period' => $dateFrom->format('M d, Y') . ' - ' . $dateTo->format('M d, Y'),
            'total_records' => $totalRecords,
            'present' => $presentCount,
            'absent' => $absentCount,
            'attendance_rate' => $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 2) . '%' : 'N/A',
        ];

        if ($args['include_late'] ?? false) {
            $summary['late_arrivals'] = $lateCount;
        }

        return $summary;
    }

    protected function getLeaveInformation(array $args): array
    {
        $query = EmployeeLeave::with(['employee:id,first_name,last_name', 'leaveType:id,name']);

        if (isset($args['employee_id'])) {
            $query->where('employee_id', $args['employee_id']);
        }

        if (isset($args['status'])) {
            $query->where('status', $args['status']);
        }

        $leaves = $query->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn($leave) => [
                'employee' => $leave->employee->first_name . ' ' . $leave->employee->last_name,
                'leave_type' => $leave->leaveType->name ?? 'N/A',
                'start_date' => Carbon::parse($leave->start_date)->format('M d, Y'),
                'end_date' => Carbon::parse($leave->end_date)->format('M d, Y'),
                'days' => $leave->days_count,
                'status' => $leave->status,
                'reason' => $leave->reason,
            ])
            ->toArray();

        $result = [
            'count' => count($leaves),
            'leaves' => $leaves,
        ];

        // Include leave balances if requested
        if ($args['include_balances'] ?? false) {
            $balanceQuery = EmployeeLeaveCredit::with(['employee:id,first_name,last_name', 'leaveType:id,name']);
            
            if (isset($args['employee_id'])) {
                $balanceQuery->where('employee_id', $args['employee_id']);
            }

            $result['balances'] = $balanceQuery->limit(30)->get()
                ->map(fn($credit) => [
                    'employee' => $credit->employee->first_name . ' ' . $credit->employee->last_name,
                    'leave_type' => $credit->leaveType->name ?? 'N/A',
                    'total_credits' => $credit->total_credits,
                    'used_credits' => $credit->used_credits,
                    'remaining' => $credit->total_credits - $credit->used_credits,
                ])
                ->toArray();
        }

        return $result;
    }

    protected function getProjectSummary(array $args): array
    {
        $query = Project::withCount(['employees' => fn($q) => $q->where('is_active', true)]);

        if (isset($args['project_name'])) {
            $query->where('name', 'ILIKE', "%{$args['project_name']}%");
        }

        $projects = $query->get()->map(function ($project) use ($args) {
            $data = [
                'id' => $project->id,
                'name' => $project->name,
                'location' => $project->location ?? 'N/A',
                'active_employees' => $project->employees_count,
            ];

            if ($args['include_employees'] ?? false) {
                $data['employees'] = Employee::where('project_id', $project->id)
                    ->where('is_active', true)
                    ->select('id', 'first_name', 'last_name')
                    ->limit(20)
                    ->get()
                    ->map(fn($e) => $e->first_name . ' ' . $e->last_name)
                    ->toArray();
            }

            return $data;
        })->toArray();

        return [
            'total_projects' => count($projects),
            'projects' => $projects,
        ];
    }

    protected function calculatePayrollTotals(array $args): array
    {
        $year = $args['year'] ?? Carbon::now()->year;
        $query = Payroll::whereYear('period_start', $year);

        if (isset($args['quarter'])) {
            $startMonth = (($args['quarter'] - 1) * 3) + 1;
            $endMonth = $startMonth + 2;
            $query->whereMonth('period_start', '>=', $startMonth)
                  ->whereMonth('period_start', '<=', $endMonth);
            $periodLabel = "Q{$args['quarter']} {$year}";
        } elseif (isset($args['month'])) {
            $query->whereMonth('period_start', $args['month']);
            $periodLabel = Carbon::create($year, $args['month'])->format('F Y');
        } else {
            $periodLabel = "Year {$year}";
        }

        return [
            'period' => $periodLabel,
            'total_payrolls' => $query->count(),
            'total_gross_pay' => '₱' . number_format($query->sum('total_gross'), 2),
            'total_net_pay' => '₱' . number_format($query->sum('total_net'), 2),
            'total_deductions' => '₱' . number_format($query->sum('total_deductions'), 2),
            'average_gross_per_payroll' => '₱' . number_format($query->avg('total_gross') ?? 0, 2),
        ];
    }

    protected function getSalaryInformation(array $args): array
    {
        $employees = Employee::where('is_active', true)
            ->with('positionRate:id,position_name,daily_rate')
            ->get();

        $dailyRates = $employees->map(fn($e) => $e->positionRate->daily_rate ?? 0)->filter(fn($r) => $r > 0);

        $result = [
            'total_active_employees' => $employees->count(),
            'average_daily_rate' => '₱' . number_format($dailyRates->avg() ?? 0, 2),
            'highest_daily_rate' => '₱' . number_format($dailyRates->max() ?? 0, 2),
            'lowest_daily_rate' => '₱' . number_format($dailyRates->min() ?? 0, 2),
        ];

        if ($args['by_position'] ?? false) {
            $result['by_position'] = PositionRate::withCount('employees')
                ->orderBy('daily_rate', 'desc')
                ->get()
                ->map(fn($p) => [
                    'position' => $p->position_name,
                    'daily_rate' => '₱' . number_format($p->daily_rate, 2),
                    'employee_count' => $p->employees_count,
                ])
                ->toArray();
        }

        if ($args['top_earners'] ?? false) {
            $result['top_earners'] = Employee::where('is_active', true)
                ->with('positionRate:id,position_name,daily_rate')
                ->get()
                ->sortByDesc(fn($e) => $e->positionRate->daily_rate ?? 0)
                ->take($args['top_earners'])
                ->values()
                ->map(fn($e) => [
                    'name' => $e->first_name . ' ' . $e->last_name,
                    'position' => $e->positionRate->position_name ?? 'N/A',
                    'daily_rate' => '₱' . number_format($e->positionRate->daily_rate ?? 0, 2),
                ])
                ->toArray();
        }

        return $result;
    }

    protected function getGovernmentContributions(array $args): array
    {
        $year = $args['year'] ?? Carbon::now()->year;
        
        $query = PayrollItem::whereHas('payroll', function ($q) use ($year, $args) {
            $q->whereYear('period_start', $year);
            if (isset($args['month'])) {
                $q->whereMonth('period_start', $args['month']);
            }
        });

        if (isset($args['employee_id'])) {
            $query->where('employee_id', $args['employee_id']);
        }

        $totals = $query->selectRaw('
            SUM(sss_contribution) as total_sss,
            SUM(philhealth_contribution) as total_philhealth,
            SUM(pagibig_contribution) as total_pagibig,
            SUM(tax_withheld) as total_tax
        ')->first();

        return [
            'period' => isset($args['month']) 
                ? Carbon::create($year, $args['month'])->format('F Y') 
                : "Year {$year}",
            'sss_contributions' => '₱' . number_format($totals->total_sss ?? 0, 2),
            'philhealth_contributions' => '₱' . number_format($totals->total_philhealth ?? 0, 2),
            'pagibig_contributions' => '₱' . number_format($totals->total_pagibig ?? 0, 2),
            'tax_withheld' => '₱' . number_format($totals->total_tax ?? 0, 2),
            'total_government_remittances' => '₱' . number_format(
                ($totals->total_sss ?? 0) + ($totals->total_philhealth ?? 0) + ($totals->total_pagibig ?? 0) + ($totals->total_tax ?? 0),
                2
            ),
        ];
    }
}
