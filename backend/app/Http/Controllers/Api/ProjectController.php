<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Payroll;
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
            'head_employee_id' => 'nullable|exists:employees,id',
            'is_active' => 'boolean',
        ]);

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
            'head_employee_id' => 'nullable|exists:employees,id',
            'is_active' => 'boolean',
        ]);

        $project->update($validated);
        $project->load(['headEmployee', 'employees']);

        return response()->json($project);
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
     * Generate payroll for all employees in this project
     */
    public function generatePayroll(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'period_start_date' => 'required|date',
            'period_end_date' => 'required|date|after:period_start_date',
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
