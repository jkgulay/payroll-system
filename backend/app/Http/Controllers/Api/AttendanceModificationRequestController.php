<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceModificationRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceModificationRequestController extends Controller
{
    private const ALLOWED_MODULES = [
        'attendance',
        'attendance-settings',
        'deductions',
        'government-rates',
        'allowances',
        'thirteenth-month-pay',
        'loans',
        'cash-bonds',
        'salary-adjustments',
    ];

    private const DAILY_SCOPED_MODULES = [
        'deductions',
        'government-rates',
        'allowances',
        'thirteenth-month-pay',
        'loans',
        'cash-bonds',
        'salary-adjustments',
    ];

    private function isDailyScopedModule(string $module): bool
    {
        return in_array($module, self::DAILY_SCOPED_MODULES, true);
    }

    private function resolveModule(Request $request): ?string
    {
        return $request->input('module', $request->query('module'));
    }

    private function resolveModules(Request $request): ?array
    {
        $modules = $request->input('modules', $request->query('modules'));
        if ($modules) {
            return is_array($modules) ? $modules : explode(',', $modules);
        }
        return null;
    }

    /**
     * List requests - admins see all, payrollists see their own
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $module = $this->resolveModule($request);
        $modules = $this->resolveModules($request);

        $query = AttendanceModificationRequest::with(['requester', 'reviewer']);

        if ($modules) {
            $query->whereIn('module', $modules);
        } elseif ($module) {
            $query->forModule($module);
        }

        if (in_array($user->role, ['admin', 'hr'])) {
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
        } else {
            $query->where('requested_by', $user->id);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Create a new access request
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'module' => 'required|string|in:' . implode(',', self::ALLOWED_MODULES),
            'date' => 'nullable|date',
            'reason' => 'required|string|max:500',
            'payload' => 'nullable|array',
        ]);

        $user = $request->user();
        $module = $validated['module'];
        $today = now()->toDateString();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        if (in_array($user->role, ['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Admin and HR users have direct access and do not need to request approval.',
            ], 422);
        }

        if ($module !== 'attendance-settings') {
            // Build the uniqueness check
            $existingQuery = AttendanceModificationRequest::where('requested_by', $user->id)
                ->where('module', $module)
                ->whereIn('status', ['pending', 'approved']);

            if ($this->isDailyScopedModule($module)) {
                $existingQuery->whereBetween('created_at', [$todayStart, $todayEnd]);
            } elseif (!empty($validated['date'])) {
                $existingQuery->where('date', $validated['date']);
            } else {
                $existingQuery->whereNull('date');
            }

            $existing = $existingQuery->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => $existing->status === 'pending'
                        ? ($this->isDailyScopedModule($module)
                            ? 'You already have a pending request for today.'
                            : 'You already have a pending request.')
                        : ($this->isDailyScopedModule($module)
                            ? 'You already have an approved request for today.'
                            : 'You already have an approved request.'),
                ], 422);
            }
        }

        $modRequest = AttendanceModificationRequest::create([
            'module' => $module,
            'requested_by' => $user->id,
            'date' => $validated['date'] ?? null,
            'reason' => $validated['reason'],
            'payload' => $validated['payload'] ?? null,
            'status' => 'pending',
        ]);

        $modRequest->load('requester');

        return response()->json([
            'success' => true,
            'message' => 'Access request submitted successfully.',
            'data' => $modRequest,
        ], 201);
    }

    /**
     * Check if user has access for a specific module (and optionally date)
     */
    public function checkAccess(Request $request)
    {
        $request->validate([
            'module' => 'required|string|in:' . implode(',', self::ALLOWED_MODULES),
            'date' => 'nullable|date',
        ]);

        $user = $request->user();
        $module = $request->query('module', 'attendance');
        $today = now()->toDateString();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        if (in_array($user->role, ['admin', 'hr'])) {
            return response()->json([
                'has_access' => true,
                'status' => 'admin',
            ]);
        }

        $query = AttendanceModificationRequest::where('requested_by', $user->id)
            ->where('module', $module)
            ->orderBy('created_at', 'desc');

        if ($this->isDailyScopedModule($module)) {
            $query->whereBetween('created_at', [$todayStart, $todayEnd]);
        } elseif ($request->has('date') && $request->date) {
            $query->where('date', $request->date);
        } else {
            $query->whereNull('date');
        }

        $modRequest = $query->first();

        if (!$modRequest) {
            $moduleLabels = [
                'deductions' => 'deductions',
                'government-rates' => 'government rates',
                'allowances' => 'allowances',
                'thirteenth-month-pay' => '13th month pay',
                'loans' => 'loans',
                'cash-bonds' => 'cash bonds',
                'salary-adjustments' => 'salary adjustments',
                'attendance-settings' => 'attendance settings',
            ];

            if ($this->isDailyScopedModule($module)) {
                $moduleLabel = $moduleLabels[$module] ?? 'this module';
                return response()->json([
                    'has_access' => false,
                    'status' => 'none',
                    'message' => "No request found for today. You must request access to manage {$moduleLabel}.",
                ]);
            }

            $moduleLabel = $moduleLabels[$module] ?? 'attendance for this date';
            return response()->json([
                'has_access' => false,
                'status' => 'none',
                'message' => "No request found. You must request access to manage {$moduleLabel}.",
            ]);
        }

        return response()->json([
            'has_access' => $modRequest->status === 'approved',
            'status' => $modRequest->status,
            'request' => $modRequest,
            'message' => match ($modRequest->status) {
                'pending' => 'Your request is pending admin approval.',
                'approved' => 'Access granted.',
                'rejected' => 'Your request was rejected. ' . ($modRequest->review_notes ?? ''),
            },
        ]);
    }

    /**
     * Approve a request (admin/hr only)
     */
    public function approve(Request $request, AttendanceModificationRequest $modificationRequest)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($modificationRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been ' . $modificationRequest->status . '.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($request, $user, $modificationRequest) {
                $this->applyApprovedRequestPayload($modificationRequest);

                $modificationRequest->update([
                    'status' => 'approved',
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                    'review_notes' => $request->input('notes'),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('[Module Access Request] Approval failed', [
                'request_id' => $modificationRequest->id,
                'module' => $modificationRequest->module,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve request: ' . $e->getMessage(),
            ], 422);
        }

        $modificationRequest->load(['requester', 'reviewer']);

        return response()->json([
            'success' => true,
            'message' => 'Request approved successfully.',
            'data' => $modificationRequest,
        ]);
    }

    /**
     * Reject a request (admin/hr only)
     */
    public function reject(Request $request, AttendanceModificationRequest $modificationRequest)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'hr'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($modificationRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been ' . $modificationRequest->status . '.',
            ], 422);
        }

        $validated = $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        $modificationRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['notes'],
        ]);

        $modificationRequest->load(['requester', 'reviewer']);

        return response()->json([
            'success' => true,
            'message' => 'Request rejected.',
            'data' => $modificationRequest,
        ]);
    }

    /**
     * Get count of pending requests (for admin badge)
     */
    public function pendingCount(Request $request)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'hr'])) {
            return response()->json(['count' => 0]);
        }

        $module = $this->resolveModule($request);
        $modules = $this->resolveModules($request);

        $query = AttendanceModificationRequest::pending();

        if ($modules) {
            $query->whereIn('module', $modules);
        } elseif ($module) {
            $query->forModule($module);
        }

        $count = $query->count();

        return response()->json(['count' => $count]);
    }

    private function applyApprovedRequestPayload(AttendanceModificationRequest $modificationRequest): void
    {
        if ($modificationRequest->module !== 'attendance-settings') {
            return;
        }

        $payload = $modificationRequest->payload;
        if (!is_array($payload) || empty($payload['type'])) {
            throw new \RuntimeException('Attendance settings payload is missing or invalid.');
        }

        $type = (string) $payload['type'];

        if ($type === 'project_schedule_update') {
            $projectId = isset($payload['project_id']) ? (int) $payload['project_id'] : 0;
            if ($projectId <= 0) {
                throw new \RuntimeException('Project ID is required for project schedule update.');
            }

            $project = Project::find($projectId);
            if (!$project) {
                throw new \RuntimeException('Project not found for this request.');
            }

            $schedule = $this->normalizeProjectSchedule($payload['schedule'] ?? []);
            $project->update($schedule);
            $this->recalculateProjectAttendances([$projectId]);
            return;
        }

        if ($type === 'project_bulk_schedule_update') {
            $projectIds = collect($payload['project_ids'] ?? [])
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($projectIds)) {
                throw new \RuntimeException('No target projects provided for bulk schedule update.');
            }

            $schedule = $this->normalizeProjectSchedule($payload['schedule'] ?? []);
            Project::whereIn('id', $projectIds)->update($schedule);
            $this->recalculateProjectAttendances($projectIds);
            return;
        }

        if ($type === 'employee_schedule_update') {
            $employeeIds = collect($payload['employee_ids'] ?? [])
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($employeeIds)) {
                throw new \RuntimeException('No employees provided for schedule override update.');
            }

            $schedule = $this->normalizeEmployeeSchedulePayload($payload);
            Employee::whereIn('id', $employeeIds)->update($schedule);
            $this->recalculateEmployeeAttendances($employeeIds);
            return;
        }

        throw new \RuntimeException('Unsupported attendance settings request type.');
    }

    private function normalizeProjectSchedule(array $schedule): array
    {
        $defaultTimeIn = config('payroll.attendance.standard_time_in', '07:30');
        $defaultTimeOut = config('payroll.attendance.standard_time_out', '17:00');
        $defaultGrace = (int) config('payroll.attendance.grace_period_minutes', 3);

        $timeIn = $schedule['time_in'] ?? null;
        $timeOut = $schedule['time_out'] ?? null;
        $grace = $schedule['grace_period_minutes'] ?? null;

        $timeIn = $timeIn ?: $defaultTimeIn;
        $timeOut = $timeOut ?: $defaultTimeOut;
        $grace = $grace === null || $grace === '' ? $defaultGrace : (int) $grace;

        if (!$this->isValidTime($timeIn) || !$this->isValidTime($timeOut)) {
            throw new \RuntimeException('Invalid time format in schedule payload.');
        }

        if ($grace < 0 || $grace > 180) {
            throw new \RuntimeException('Invalid grace period in schedule payload.');
        }

        return [
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'grace_period_minutes' => $grace,
        ];
    }

    private function normalizeEmployeeSchedulePayload(array $payload): array
    {
        if (($payload['clear_override'] ?? false) === true) {
            return [
                'attendance_time_in' => null,
                'attendance_time_out' => null,
                'attendance_grace_period_minutes' => null,
            ];
        }

        $timeIn = $payload['attendance_time_in'] ?? null;
        $timeOut = $payload['attendance_time_out'] ?? null;
        $grace = $payload['attendance_grace_period_minutes'] ?? null;

        if ($timeIn !== null && $timeIn !== '' && !$this->isValidTime((string) $timeIn)) {
            throw new \RuntimeException('Invalid attendance_time_in format in payload.');
        }

        if ($timeOut !== null && $timeOut !== '' && !$this->isValidTime((string) $timeOut)) {
            throw new \RuntimeException('Invalid attendance_time_out format in payload.');
        }

        if ($grace !== null && $grace !== '') {
            $grace = (int) $grace;
            if ($grace < 0 || $grace > 180) {
                throw new \RuntimeException('Invalid attendance grace period in payload.');
            }
        } else {
            $grace = null;
        }

        return [
            'attendance_time_in' => $timeIn ?: null,
            'attendance_time_out' => $timeOut ?: null,
            'attendance_grace_period_minutes' => $grace,
        ];
    }

    private function recalculateProjectAttendances(array $projectIds): void
    {
        if (empty($projectIds)) {
            return;
        }

        $employeeIds = Employee::whereIn('project_id', $projectIds)->pluck('id');
        if ($employeeIds->isEmpty()) {
            return;
        }

        $this->recalculateEmployeeAttendances($employeeIds->all());
    }

    private function recalculateEmployeeAttendances(array $employeeIds): void
    {
        if (empty($employeeIds)) {
            return;
        }

        $lookbackDays = max((int) config('payroll.attendance.schedule_recalculation_lookback_days', 60), 0);
        $recalculateFrom = now()->subDays($lookbackDays)->toDateString();

        Attendance::with(['employee.project'])
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('attendance_date', '>=', $recalculateFrom)
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->chunkById(200, function ($attendances) {
                foreach ($attendances as $attendance) {
                    $attendance->calculateHours();
                }
            });
    }

    private function isValidTime(string $time): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }
}
