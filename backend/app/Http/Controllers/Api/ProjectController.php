<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Payroll;
use App\Models\Attendance;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function index()
    {
        $projects = Project::withCount('employees')
            ->with(['headEmployee:id,first_name,last_name'])
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($projects);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:projects',
            'name' => 'required|string|max:100|unique:projects',
            'description' => 'nullable|string',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'grace_period_minutes' => 'nullable|integer|min:0|max:180',
            'head_employee_id' => 'nullable|exists:employees,id',
            'is_active' => 'boolean',
        ]);

        $validated = $this->applyDefaultSchedule($validated);

        // Generate code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = 'PRJ-' . str_pad(Project::count() + 1, 4, '0', STR_PAD_LEFT);
        }

        $validated['is_active'] = $validated['is_active'] ?? true;

        $project = Project::create($validated);
        $project->load(['headEmployee', 'employees']);

        return response()->json($project, 201);
    }

    public function show(Project $project)
    {
        $project->load([
            'employees' => function ($query) {
                $query->select('employees.*')
                    ->orderBy('position')
                    ->orderBy('last_name');
            },
            'headEmployee:id,first_name,last_name,position'
        ]);

        return response()->json($project);
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:projects,code,' . $project->id,
            'name' => 'required|string|max:100|unique:projects,name,' . $project->id,
            'description' => 'nullable|string',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'grace_period_minutes' => 'nullable|integer|min:0|max:180',
            'head_employee_id' => 'nullable|exists:employees,id',
            'is_active' => 'boolean',
        ]);

        $validated = $this->applyDefaultSchedule($validated);

        $project->update($validated);
        if ($project->wasChanged(['time_in', 'time_out', 'grace_period_minutes'])) {
            $this->recalculateProjectAttendances($project);
        }
        $project->load(['headEmployee', 'employees']);

        return response()->json($project);
    }

    private function recalculateProjectAttendances(Project $project): void
    {
        $employeeIds = $project->employees()->pluck('id');
        if ($employeeIds->isEmpty()) {
            return;
        }

        Attendance::with(['employee.project'])
            ->whereIn('employee_id', $employeeIds)
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->chunkById(200, function ($attendances) {
                foreach ($attendances as $attendance) {
                    $attendance->calculateHours();
                }
            });
    }

    private function applyDefaultSchedule(array $validated): array
    {
        $defaultTimeIn = config('payroll.attendance.standard_time_in', '08:00');
        $defaultTimeOut = config('payroll.attendance.standard_time_out', '17:00');
        $defaultGrace = (int) config('payroll.attendance.grace_period_minutes', 0);

        if (!array_key_exists('time_in', $validated) || $validated['time_in'] === null) {
            $validated['time_in'] = $defaultTimeIn;
        }

        if (!array_key_exists('time_out', $validated) || $validated['time_out'] === null) {
            $validated['time_out'] = $defaultTimeOut;
        }

        if (!array_key_exists('grace_period_minutes', $validated) || $validated['grace_period_minutes'] === null) {
            $validated['grace_period_minutes'] = $defaultGrace;
        }

        return $validated;
    }

    public function destroy(Project $project)
    {
        // Check if project has employees
        if ($project->employees()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete project with assigned employees'
            ], 422);
        }

        $project->delete();
        return response()->json(['message' => 'Project deleted successfully']);
    }

    /**
     * Get project employees with their positions
     */
    public function employees(Project $project): JsonResponse
    {
        $employees = $project->employees()
            ->with('positionRate')
            ->select('employees.*')
            ->orderBy('position_id')
            ->orderBy('last_name')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->first_name . ' ' . $employee->last_name,
                    'position' => $employee->positionRate?->position_name ?? 'N/A',
                    'position_id' => $employee->position_id,
                    'basic_salary' => $employee->basic_salary,
                    'salary_type' => $employee->salary_type,
                    'contract_type' => $employee->contract_type,
                    'activity_status' => $employee->activity_status,
                    'date_hired' => $employee->date_hired,
                ];
            });

        return response()->json($employees);
    }

    /**
     * Mark project as complete/inactive
     */
    public function markComplete(Project $project): JsonResponse
    {
        $project->update(['is_active' => false]);

        return response()->json([
            'message' => 'Project marked as complete',
            'project' => $project
        ]);
    }

    /**
     * Reactivate project
     */
    public function reactivate(Project $project): JsonResponse
    {
        $project->update(['is_active' => true]);

        return response()->json([
            'message' => 'Project reactivated',
            'project' => $project
        ]);
    }

    /**
     * Bulk update schedules for all projects except excluded IDs
     */
    public function bulkSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'grace_period_minutes' => 'nullable|integer|min:0|max:180',
            'exclude_project_ids' => 'array',
            'exclude_project_ids.*' => 'integer|exists:projects,id',
        ]);

        $validated = $this->applyDefaultSchedule($validated);

        $excludeIds = $validated['exclude_project_ids'] ?? [];
        $payload = [
            'time_in' => $validated['time_in'],
            'time_out' => $validated['time_out'],
            'grace_period_minutes' => $validated['grace_period_minutes'],
        ];

        $projectIds = Project::query()
            ->when(!empty($excludeIds), fn($q) => $q->whereNotIn('id', $excludeIds))
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return response()->json([
                'message' => 'No departments to update',
            ], 200);
        }

        Project::whereIn('id', $projectIds)->update($payload);

        $projectIds->each(function ($projectId) {
            $project = Project::find($projectId);
            if ($project) {
                $this->recalculateProjectAttendances($project);
            }
        });

        return response()->json([
            'message' => 'Schedules updated successfully',
            'updated_count' => $projectIds->count(),
        ]);
    }

    /**
     * Generate payroll for all employees in this project
     */
    public function generatePayroll(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'period_start_date' => 'required|date',
            'period_end_date' => 'required|date|after_or_equal:period_start_date',
            'payment_date' => 'required|date',
        ]);

        // Get all active employees in this project
        $employeeIds = $project->employees()
            ->where('activity_status', 'active')
            ->pluck('id')
            ->toArray();

        if (empty($employeeIds)) {
            return response()->json([
                'message' => 'No active employees found in this project'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Transform the validated data to match PayrollService expectations
            $payrollData = [
                'period_name' => $project->name . ' - ' . date('M d, Y', strtotime($validated['period_start_date'])) . ' to ' . date('M d, Y', strtotime($validated['period_end_date'])),
                'period_start' => $validated['period_start_date'],
                'period_end' => $validated['period_end_date'],
                'payment_date' => $validated['payment_date'],
            ];

            // Create payroll period
            $payroll = $this->payrollService->createPayroll($payrollData);

            // Verify the payroll was saved and has an ID
            if (!$payroll || !$payroll->id) {
                throw new \Exception('Failed to create payroll record');
            }

            // Process payroll only for employees in this project
            $this->payrollService->processPayroll($payroll, $employeeIds);

            DB::commit();

            // Reload with relationships
            $payroll->load(['items.employee', 'creator']);

            return response()->json([
                'message' => 'Payroll generated successfully for ' . count($employeeIds) . ' employees',
                'payroll' => $payroll,
                'employee_count' => count($employeeIds)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generating project payroll: ' . $e->getMessage(), [
                'project_id' => $project->id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to generate payroll: ' . $e->getMessage()
            ], 500);
        }
    }
}
