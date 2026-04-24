<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\EmployeeGovernmentInfo;
use App\Models\EmployeeLeave;
use App\Models\Attendance;
use App\Models\SalaryAdjustment;
use App\Models\User;
use App\Models\AuditLog;
use App\Helpers\DateHelper;
use App\Helpers\EmployeeFieldMapper;
use App\Validators\EmployeeValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    private const RATE_REQUEST_META_PREFIX = '[RATE_REQUEST_META]';

    public function index(Request $request)
    {
        $this->syncLeaveStatusesForToday();

        $query = Employee::query()
            ->select([
                'id',
                'user_id',
                'employee_number',
                'biometric_id',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'gender',
                'project_id',
                'position_id',
                'contract_type',
                'activity_status',
                'date_hired',
                'custom_pay_rate',
                'basic_salary',
                'department',
                'work_schedule',
                'created_at',
            ])
            ->with([
                'project:id,name',
                'positionRate:id,position_name,daily_rate',
                'user:id,employee_id,role,name,username',
            ]);

        // Search - case-insensitive search across multiple fields
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('middle_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('mobile_number', 'like', "%{$search}%")
                    // Search by full name (first + last, first + middle + last)
                    ->orWhereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", ['%' . strtolower($search) . '%'])
                    ->orWhereRaw("LOWER(CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name)) LIKE ?", ['%' . strtolower($search) . '%'])
                    // Search by position name
                    ->orWhereHas('positionRate', function ($posQuery) use ($search) {
                        $posQuery->where('position_name', 'ilike', "%{$search}%");
                    })
                    // Search by project name
                    ->orWhereHas('project', function ($projQuery) use ($search) {
                        $projQuery->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        // Filter by project
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        // Filter by department
        if ($request->has('department') && $request->department) {
            $query->where('department', $request->department);
        }

        // Filter by contract type
        if ($request->has('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }

        // Filter by activity status (supports single value, array, or comma-separated)
        if ($request->has('activity_status')) {
            $activityStatus = $request->activity_status;
            if (is_array($activityStatus)) {
                $query->whereIn('activity_status', $activityStatus);
            } elseif (str_contains($activityStatus, ',')) {
                $statuses = array_map('trim', explode(',', $activityStatus));
                $query->whereIn('activity_status', $statuses);
            } else {
                $query->where('activity_status', $activityStatus);
            }
        }

        // Filter by work schedule (new) or employment_type (legacy)
        if ($request->has('work_schedule')) {
            $query->where('work_schedule', $request->work_schedule);
        } elseif ($request->has('employment_type')) {
            // Legacy support: map old values to new
            $schedule = $request->employment_type === 'part_time' ? 'part_time' : 'full_time';
            $query->where('work_schedule', $schedule);
        }

        // Filter by position
        if ($request->has('position') && $request->position) {
            // Check if it's a position ID (numeric) or position name (string)
            if (is_numeric($request->position)) {
                $positionRate = \App\Models\PositionRate::find($request->position);

                $query->where(function ($positionQuery) use ($request, $positionRate) {
                    $positionQuery->where('position_id', $request->position);

                    if (Schema::hasColumn('employees', 'position') && $positionRate) {
                        $positionQuery->orWhere(function ($legacyQuery) use ($positionRate) {
                            $legacyQuery
                                ->whereNull('position_id')
                                ->whereRaw('LOWER(position) = ?', [strtolower($positionRate->position_name)]);
                        });
                    }
                });
            } else {
                // Convert position name to position_id
                $positionRate = \App\Models\PositionRate::where('position_name', $request->position)->first();
                if ($positionRate) {
                    $query->where(function ($positionQuery) use ($positionRate, $request) {
                        $positionQuery->where('position_id', $positionRate->id);

                        if (Schema::hasColumn('employees', 'position')) {
                            $positionQuery->orWhere(function ($legacyQuery) use ($request) {
                                $legacyQuery
                                    ->whereNull('position_id')
                                    ->whereRaw('LOWER(position) = ?', [strtolower($request->position)]);
                            });
                        }
                    });
                } elseif (Schema::hasColumn('employees', 'position')) {
                    $query->whereRaw('LOWER(position) = ?', [strtolower($request->position)]);
                }
            }
        }

        $perPage = $request->get('per_page', 50); // Increased default from 15 to 50
        $employees = $query->latest('created_at')->paginate($perPage);

        $this->appendPendingPayRateRequestAttributes($employees);

        return response()->json($employees);
    }

    private function appendPendingPayRateRequestAttributes($employees): void
    {
        if (!method_exists($employees, 'getCollection') || !method_exists($employees, 'setCollection')) {
            return;
        }

        $collection = $employees->getCollection();
        $employeeIds = $collection
            ->pluck('id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->values();

        if ($employeeIds->isEmpty()) {
            return;
        }

        $referencePeriodColumn = SalaryAdjustment::referencePeriodColumn();
        $pendingByEmployee = [];
        $pendingRequests = SalaryAdjustment::query()
            ->select(['id', 'employee_id', 'created_at'])
            ->addSelect(DB::raw($referencePeriodColumn . ' as reference_period'))
            ->whereIn('employee_id', $employeeIds->all())
            ->where('status', 'pending')
            ->where(function ($query) use ($referencePeriodColumn) {
                $query
                    ->where($referencePeriodColumn, 'like', 'APPROVAL:EMPLOYEE_CUSTOM_RATE_UPDATE:%')
                    ->orWhere($referencePeriodColumn, 'like', 'APPROVAL:EMPLOYEE_CUSTOM_RATE_CLEAR:%');
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($pendingRequests as $request) {
            $employeeId = (int) $request->employee_id;

            // Keep the latest pending request per employee.
            if (isset($pendingByEmployee[$employeeId])) {
                continue;
            }

            $type = $this->resolveEmployeePendingRequestType((string) $request->reference_period);

            $pendingByEmployee[$employeeId] = [
                'id' => (int) $request->id,
                'type' => $type,
                'label' => $this->resolveEmployeePendingRequestLabel($type),
                'requested_at' => $request->created_at?->toDateTimeString(),
            ];
        }

        $employees->setCollection($collection->map(function ($employee) use ($pendingByEmployee) {
            $pending = $pendingByEmployee[(int) $employee->id] ?? null;

            $employee->setAttribute('has_pending_pay_rate_request', $pending !== null);
            $employee->setAttribute('pending_pay_rate_request_id', $pending['id'] ?? null);
            $employee->setAttribute('pending_pay_rate_request_type', $pending['type'] ?? null);
            $employee->setAttribute('pending_pay_rate_request_label', $pending['label'] ?? null);
            $employee->setAttribute('pending_pay_rate_requested_at', $pending['requested_at'] ?? null);

            return $employee;
        }));
    }

    private function resolveEmployeePendingRequestType(string $referencePeriod): string
    {
        if (str_starts_with($referencePeriod, 'APPROVAL:EMPLOYEE_CUSTOM_RATE_CLEAR:')) {
            return 'employee_custom_pay_rate_clear';
        }

        return 'employee_custom_pay_rate_update';
    }

    private function resolveEmployeePendingRequestLabel(string $type): string
    {
        return match ($type) {
            'employee_custom_pay_rate_clear' => 'Pending custom rate clear request',
            default => 'Pending custom rate update request',
        };
    }

    private function syncLeaveStatusesForToday(): void
    {
        $today = Carbon::today()->toDateString();

        $onLeaveEmployeeIds = EmployeeLeave::where('status', 'approved')
            ->whereDate('leave_date_from', '<=', $today)
            ->whereDate('leave_date_to', '>=', $today)
            ->distinct()
            ->pluck('employee_id')
            ->filter()
            ->values();

        if ($onLeaveEmployeeIds->isNotEmpty()) {
            Employee::whereIn('id', $onLeaveEmployeeIds)
                ->where('activity_status', '!=', 'on_leave')
                ->update(['activity_status' => 'on_leave']);
        }

        $activeQuery = Employee::where('activity_status', 'on_leave');
        if ($onLeaveEmployeeIds->isNotEmpty()) {
            $activeQuery->whereNotIn('id', $onLeaveEmployeeIds);
        }

        $activeQuery->update(['activity_status' => 'active']);
    }

    public function store(StoreEmployeeRequest $request)
    {
        // Validation is handled by StoreEmployeeRequest
        $validated = $request->validated();

        // Validate role separately (it's for user account, not employee record)
        $requestedRole = $request->validate([
            'role' => 'nullable|in:admin,hr,employee,payrollist',
        ])['role'] ?? null;

        // Normalize employee data using helper
        $validated = EmployeeFieldMapper::normalizeEmployeeData($validated);

        // Determine role from position or use requested role
        $role = $requestedRole ?? EmployeeFieldMapper::determineRoleFromPosition($validated, 'employee');

        // Remove temporary helper data
        unset($validated['_position_rate']);

        DB::beginTransaction();
        try {
            // Generate employee number (EMP001, EMP002, etc.)
            $lastEmployee = Employee::orderBy('id', 'desc')->first();
            $nextNumber = $lastEmployee ? ((int) substr($lastEmployee->employee_number, 3)) + 1 : 1;
            $validated['employee_number'] = 'EMP' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // Create employee
            $employee = Employee::create($validated);

            // Generate credentials using helper
            $username = EmployeeFieldMapper::generateUsername($validated);
            $autoPassword = EmployeeFieldMapper::generateTemporaryPassword(
                $validated['last_name'],
                $validated['employee_number']
            );
            $email = $validated['email'] ?? null;

            // Always create user account
            $user = User::create([
                'username' => $username,
                'email' => $email,
                'password' => Hash::make($autoPassword),
                'role' => $role,
                'is_active' => true,
                'must_change_password' => true, // Force password change on first login
                'employee_id' => $employee->id, // Link user to employee
            ]);

            // Link employee to user (for backwards compatibility with code that uses employee->user_id)
            $employee->user_id = $user->id;
            $employee->save();

            // Sync government IDs to employee_government_info (keeps both tables consistent)
            $govIds = array_filter([
                'sss_number'        => $validated['sss_number'] ?? null,
                'philhealth_number' => $validated['philhealth_number'] ?? null,
                'pagibig_number'    => $validated['pagibig_number'] ?? null,
                'tin_number'        => $validated['tin_number'] ?? null,
            ], fn($v) => !is_null($v));
            if (!empty($govIds)) {
                EmployeeGovernmentInfo::updateOrCreate(
                    ['employee_id' => $employee->id],
                    $govIds
                );
            }

            // Store temporary password for response (in real app, send via email)
            $employee->temporary_password = $autoPassword;

            DB::commit();
            return response()->json([
                'employee' => $employee,
                'role' => $role,
                'username' => $username,
                'temporary_password' => $autoPassword,
                'message' => 'Employee created successfully. Username: ' . $username . ', Temporary password: ' . $autoPassword
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create employee: ' . $e->getMessage()], 500);
        }
    }

    public function show(Employee $employee)
    {
        $employee->load(['project', 'allowances', 'loans', 'deductions']);
        return response()->json($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $validated = $request->validated();

        // Normalize employee data (handles position mapping, gender normalization, etc.)
        $validated = EmployeeFieldMapper::normalizeEmployeeData($validated);

        // Remove temporary helper data
        unset($validated['_position_rate']);

        // Track salary changes for audit
        if (isset($validated['basic_salary']) && $validated['basic_salary'] != $employee->basic_salary) {
            AuditLog::logSalaryChange($employee, $employee->basic_salary, $validated['basic_salary']);
        }

        // Track position changes (may affect salary) - using position_id now
        if (isset($validated['position_id']) && $validated['position_id'] != $employee->position_id) {
            $oldPosition = $employee->positionRate;
            $newPosition = \App\Models\PositionRate::find($validated['position_id']);
            $oldSalary = $employee->basic_salary;
            $newSalary = $validated['basic_salary'] ?? $oldSalary;
            if ($oldPosition && $newPosition) {
                AuditLog::logPositionChange(
                    $employee,
                    $oldPosition->position_name,
                    $newPosition->position_name,
                    $oldSalary,
                    $newSalary
                );

                // Auto-update user role based on position using the same logic as employee creation
                if ($employee->user_id) {
                    $newRole = EmployeeFieldMapper::determineRoleFromPosition([
                        '_position_rate' => $newPosition
                    ], 'employee');

                    \App\Models\User::where('id', $employee->user_id)->update(['role' => $newRole]);
                }

                // Sync basic_salary to match the new position's daily_rate (prevents stale salary data)
                // Only when no custom override is active on this employee
                if (empty($employee->custom_pay_rate) && empty($validated['custom_pay_rate'])) {
                    $validated['basic_salary'] = $newPosition->daily_rate;
                }
            }
        }

        $employee->update($validated);

        // Sync any changed government IDs to employee_government_info
        $govIdFields = ['sss_number', 'philhealth_number', 'pagibig_number', 'tin_number'];
        $govIds = array_filter(
            array_intersect_key($validated, array_flip($govIdFields)),
            fn($v) => !is_null($v)
        );
        if (!empty($govIds)) {
            EmployeeGovernmentInfo::updateOrCreate(
                ['employee_id' => $employee->id],
                $govIds
            );
        }

        $employee->load(['project', 'positionRate']); // Load relationships for response

        return response()->json($employee);
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        return response()->json(['message' => 'Employee deleted successfully']);
    }

    public function allowances(Employee $employee)
    {
        return response()->json($employee->allowances);
    }

    public function loans(Employee $employee)
    {
        return response()->json($employee->loans);
    }

    public function deductions(Employee $employee)
    {
        return response()->json($employee->deductions);
    }

    /**
     * Get employee credentials (username, email, role)
     */
    public function getCredentials(Employee $employee)
    {
        // Check if user account exists, if not create one
        if (!$employee->user_id) {
            $user = $this->createUserForEmployee($employee);
        } else {
            $user = User::where('id', $employee->user_id)->first();
            if (!$user) {
                // User ID exists but user not found, create new user
                $user = $this->createUserForEmployee($employee);
            }
        }

        return response()->json([
            'has_account' => true,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
        ]);
    }

    /**
     * Reset employee password and generate new temporary password
     */
    public function resetPassword(Employee $employee)
    {
        // Check if user account exists, if not create one
        if (!$employee->user_id) {
            $user = $this->createUserForEmployee($employee);
            // Return the newly created credentials
            return response()->json([
                'message' => 'User account created and password set',
                'temporary_password' => $user->temporary_password,
            ]);
        }

        $user = User::where('id', $employee->user_id)->first();

        if (!$user) {
            // User ID exists but user not found, create new user
            $user = $this->createUserForEmployee($employee);
            return response()->json([
                'message' => 'User account created and password set',
                'temporary_password' => $user->temporary_password,
            ]);
        }

        // Generate new temporary password using helper
        $newPassword = EmployeeFieldMapper::generateTemporaryPassword(
            $employee->last_name,
            $employee->employee_number
        );

        // Update user password
        $user->password = Hash::make($newPassword);
        $user->must_change_password = true;
        $user->save();

        // Log the action
        Log::info("Password reset for employee {$employee->employee_number} by user " . auth()->id());

        return response()->json([
            'message' => 'Password reset successfully',
            'temporary_password' => $newPassword,
        ]);
    }

    /**
     * Create a user account for an employee without one
     */
    private function createUserForEmployee(Employee $employee)
    {
        // Generate username from name (firstname.lastname)
        $username = strtolower($employee->first_name . '.' . $employee->last_name);

        // Check if username exists, if so append number
        $originalUsername = $username;
        $counter = 1;
        while (User::where('username', $username)->exists()) {
            $username = $originalUsername . $counter;
            $counter++;
        }

        // Generate temporary password: LastName + EmployeeNumber + @ + 2 random digits
        $randomDigits = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);
        $temporaryPassword = $employee->last_name . $employee->employee_number . '@' . $randomDigits;

        // Determine role based on position using helper
        $data = ['position_id' => $employee->position_id];
        if ($employee->position_id) {
            $data['_position_rate'] = \App\Models\PositionRate::find($employee->position_id);
        }
        $role = EmployeeFieldMapper::determineRoleFromPosition($data, 'employee');

        // Validate employee has required fields
        EmployeeValidator::validateForUserCreation($employee);

        // Generate credentials using helper
        $username = EmployeeFieldMapper::generateUsername([
            'email' => $employee->email,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
        ]);
        $temporaryPassword = EmployeeFieldMapper::generateTemporaryPassword(
            $employee->last_name,
            $employee->employee_number
        );

        // Create user account
        $user = User::create([
            'username' => $username,
            'email' => $employee->email ?? $username . '@company.com',
            'password' => Hash::make($temporaryPassword),
            'role' => $role,
            'is_active' => true,
            'must_change_password' => true,
            'employee_id' => $employee->id,
        ]);

        // Store temporary password on user object (not saved to DB)
        $user->temporary_password = $temporaryPassword;

        // Link user to employee (bidirectional)
        $employee->user_id = $user->id;
        $employee->save();

        // Log the action
        Log::info("User account created for employee {$employee->employee_number} (ID: {$employee->id}) by user " . auth()->id());

        return $user;
    }

    /**
     * Update employee's custom pay rate
     * Allows admin to set a custom rate that overrides position-based rate
     */
    public function updatePayRate(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'custom_pay_rate' => 'required|numeric|min:0|max:999999.99',
            'reason' => 'nullable|string|max:500', // Optional reason for audit trail
        ]);

        $oldCustomRate = $employee->custom_pay_rate;
        $oldRate = (float) $employee->getBasicSalary();
        $newRate = (float) $validated['custom_pay_rate'];

        if ($oldCustomRate !== null && abs((float) $oldCustomRate - $newRate) < 0.0001) {
            return response()->json([
                'message' => 'No change detected. The requested custom pay rate is already set for this employee.',
            ], 422);
        }

        if ($this->requiresAdminApprovalForRateChange()) {
            $referencePeriod = 'APPROVAL:EMPLOYEE_CUSTOM_RATE_UPDATE:' . $employee->id;
            $existingPending = $this->findPendingRateApprovalRequest($referencePeriod);
            if ($existingPending) {
                return response()->json([
                    'message' => 'A pending admin approval request already exists for this employee pay-rate update.',
                    'request_id' => $existingPending->id,
                ], 422);
            }

            $delta = round($newRate - $oldRate, 2);
            $requestType = $delta < 0 ? 'deduction' : 'addition';
            $notes = $this->buildRateApprovalNotes([
                'request_type' => 'employee_custom_pay_rate_update',
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'old_custom_pay_rate' => $oldCustomRate !== null ? (float) $oldCustomRate : null,
                'new_custom_pay_rate' => $newRate,
                'old_effective_rate' => $oldRate,
                'new_effective_rate' => $newRate,
                'requested_by' => auth()->id(),
                'requested_by_role' => strtolower((string) (auth()->user()?->role ?? '')),
                'requested_reason' => $validated['reason'] ?? null,
                'requested_at' => now()->toDateTimeString(),
            ], $validated['reason'] ?? null);

            $approvalRequest = SalaryAdjustment::create([
                'employee_id' => $employee->id,
                'amount' => $this->normalizeApprovalRequestAmount($delta),
                'type' => $requestType,
                'reason' => 'Employee Custom Pay Rate Update Request',
                'reference_period' => $referencePeriod,
                'effective_date' => now()->toDateString(),
                'status' => 'pending',
                'created_by' => auth()->id(),
                'notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'salary_adjustments',
                'action' => 'create_adjustment',
                'description' => "Custom pay-rate update request submitted for employee {$employee->employee_number} ({$employee->full_name})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'new_values' => array_merge($approvalRequest->toArray(), [
                    'approval_required' => true,
                    'request_scope' => 'employee_custom_pay_rate_update',
                ]),
            ]);

            return response()->json([
                'message' => 'Custom pay rate update submitted for admin approval. It is now visible in Salary Exception Records.',
                'approval_required' => true,
                'request' => $approvalRequest->load(['employee', 'createdBy']),
                'old_rate' => $oldRate,
                'new_rate' => $newRate,
            ], 202);
        }

        // Update the custom pay rate
        $employee->custom_pay_rate = $newRate;
        $employee->save();

        // Log the change for audit
        $description = "Pay rate updated from ₱" . number_format((float)$oldRate, 2) . " to ₱" . number_format((float)$newRate, 2) .
            " for employee {$employee->employee_number} ({$employee->full_name})";
        if (!empty($validated['reason'])) {
            $description .= ". Reason: {$validated['reason']}";
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'employees',
            'action' => 'pay_rate_update',
            'model_type' => Employee::class,
            'model_id' => $employee->id,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => [
                'employee_id' => $employee->id,
                'custom_pay_rate' => $oldCustomRate,
                'effective_pay_rate' => $oldRate,
            ],
            'new_values' => [
                'employee_id' => $employee->id,
                'custom_pay_rate' => $newRate,
                'effective_pay_rate' => $newRate,
                'reason' => $validated['reason'] ?? null,
            ],
        ]);

        Log::info("Pay rate updated for employee {$employee->employee_number} from {$oldRate} to {$newRate} by user " . auth()->id());

        return response()->json([
            'message' => 'Pay rate updated successfully',
            'employee' => $employee->load(['project', 'positionRate']),
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
        ]);
    }

    /**
     * Clear employee's custom pay rate (revert to position-based rate)
     */
    public function clearCustomPayRate(Request $request, Employee $employee)
    {
        if ($employee->custom_pay_rate === null) {
            return response()->json([
                'message' => 'Employee does not have a custom pay rate set',
            ], 422);
        }

        $oldCustomRate = $employee->custom_pay_rate;
        $oldRate = (float) $employee->custom_pay_rate;

        if ($this->requiresAdminApprovalForRateChange()) {
            $referencePeriod = 'APPROVAL:EMPLOYEE_CUSTOM_RATE_CLEAR:' . $employee->id;
            $existingPending = $this->findPendingRateApprovalRequest($referencePeriod);
            if ($existingPending) {
                return response()->json([
                    'message' => 'A pending admin approval request already exists for clearing this employee custom pay rate.',
                    'request_id' => $existingPending->id,
                ], 422);
            }

            $newRate = $this->estimateEmployeeRateWithoutCustom($employee);
            $delta = round($newRate - $oldRate, 2);
            $requestType = $delta < 0 ? 'deduction' : 'addition';
            $reason = $request->input('reason');

            $notes = $this->buildRateApprovalNotes([
                'request_type' => 'employee_custom_pay_rate_clear',
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'old_custom_pay_rate' => $oldCustomRate !== null ? (float) $oldCustomRate : null,
                'new_custom_pay_rate' => null,
                'old_effective_rate' => $oldRate,
                'new_effective_rate' => $newRate,
                'requested_by' => auth()->id(),
                'requested_by_role' => strtolower((string) (auth()->user()?->role ?? '')),
                'requested_reason' => $reason,
                'requested_at' => now()->toDateTimeString(),
            ], $reason);

            $approvalRequest = SalaryAdjustment::create([
                'employee_id' => $employee->id,
                'amount' => $this->normalizeApprovalRequestAmount($delta),
                'type' => $requestType,
                'reason' => 'Employee Custom Pay Rate Clear Request',
                'reference_period' => $referencePeriod,
                'effective_date' => now()->toDateString(),
                'status' => 'pending',
                'created_by' => auth()->id(),
                'notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'salary_adjustments',
                'action' => 'create_adjustment',
                'description' => "Custom pay-rate clear request submitted for employee {$employee->employee_number} ({$employee->full_name})",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'new_values' => array_merge($approvalRequest->toArray(), [
                    'approval_required' => true,
                    'request_scope' => 'employee_custom_pay_rate_clear',
                ]),
            ]);

            return response()->json([
                'message' => 'Custom pay rate clear request submitted for admin approval. It is now visible in Salary Exception Records.',
                'approval_required' => true,
                'request' => $approvalRequest->load(['employee', 'createdBy']),
                'old_rate' => $oldRate,
                'new_rate' => $newRate,
            ], 202);
        }

        $employee->custom_pay_rate = null;
        $employee->save();

        // Get the rate that will now be used (position rate or basic_salary)
        $newRate = $employee->getBasicSalary();

        // Log the change for audit
        $description = "Custom pay rate cleared. Rate changed from ₱" . number_format((float)$oldRate, 2) . " to ₱" . number_format((float)$newRate, 2) .
            " (position-based rate) for employee {$employee->employee_number} ({$employee->full_name})";
        $reason = $request->input('reason');
        if (!empty($reason)) {
            $description .= ". Reason: {$reason}";
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => 'employees',
            'action' => 'pay_rate_clear',
            'model_type' => Employee::class,
            'model_id' => $employee->id,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => [
                'employee_id' => $employee->id,
                'custom_pay_rate' => $oldCustomRate,
                'effective_pay_rate' => $oldRate,
            ],
            'new_values' => [
                'employee_id' => $employee->id,
                'custom_pay_rate' => null,
                'effective_pay_rate' => $newRate,
                'reason' => $reason,
            ],
        ]);

        Log::info("Custom pay rate cleared for employee {$employee->employee_number} by user " . auth()->id());

        return response()->json([
            'message' => 'Custom pay rate cleared successfully. Reverted to position-based rate.',
            'employee' => $employee->load(['project', 'positionRate']),
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
        ]);
    }

    private function requiresAdminApprovalForRateChange(): bool
    {
        $role = strtolower((string) (auth()->user()?->role ?? ''));
        return in_array($role, ['hr', 'payrollist'], true);
    }

    private function findPendingRateApprovalRequest(string $referencePeriod): ?SalaryAdjustment
    {
        $referencePeriodColumn = SalaryAdjustment::referencePeriodColumn();

        return SalaryAdjustment::query()
            ->where($referencePeriodColumn, $referencePeriod)
            ->where('status', 'pending')
            ->latest('id')
            ->first();
    }

    private function normalizeApprovalRequestAmount(float $delta): float
    {
        $normalized = round(abs($delta), 2);
        return $normalized >= 0 ? $normalized : 0.0;
    }

    private function estimateEmployeeRateWithoutCustom(Employee $employee): float
    {
        $employee->loadMissing('positionRate');

        if ($employee->positionRate?->daily_rate !== null) {
            return (float) $employee->positionRate->daily_rate;
        }

        return $employee->basic_salary !== null
            ? (float) $employee->basic_salary
            : 0.0;
    }

    private function buildRateApprovalNotes(array $meta, ?string $reason = null): string
    {
        $lines = [
            self::RATE_REQUEST_META_PREFIX . json_encode($meta, JSON_UNESCAPED_SLASHES),
            'Requested by user #' . (string) ($meta['requested_by'] ?? auth()->id()),
            'Request type: ' . (string) ($meta['request_type'] ?? 'rate_change_request'),
        ];

        if (isset($meta['old_effective_rate']) || isset($meta['new_effective_rate'])) {
            $lines[] = 'Effective rate change: ₱' . number_format((float) ($meta['old_effective_rate'] ?? 0), 2) .
                ' -> ₱' . number_format((float) ($meta['new_effective_rate'] ?? 0), 2);
        }

        if (!empty($reason)) {
            $lines[] = 'Reason: ' . $reason;
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * List employees with effective attendance schedule for Attendance Settings UI.
     */
    public function scheduleList(Request $request)
    {
        $defaultTimeIn = config('payroll.attendance.standard_time_in', '07:30');
        $defaultTimeOut = config('payroll.attendance.standard_time_out', '17:00');
        $defaultGrace = (int) config('payroll.attendance.grace_period_minutes', 3);

        $query = Employee::query()
            ->select([
                'id',
                'employee_number',
                'first_name',
                'last_name',
                'project_id',
                'position_id',
                'activity_status',
                'attendance_time_in',
                'attendance_time_out',
                'attendance_grace_period_minutes',
            ])
            ->with([
                'project:id,name,time_in,time_out,grace_period_minutes',
                'positionRate:id,position_name',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhereRaw("LOWER(CONCAT(first_name, ' ', last_name)) LIKE ?", ['%' . strtolower($search) . '%']);
            });
        }

        if ($request->filled('activity_status')) {
            $query->where('activity_status', $request->input('activity_status'));
        }

        $employees = $query->get()->map(function (Employee $employee) use ($defaultTimeIn, $defaultTimeOut, $defaultGrace) {
            $project = $employee->project;

            $hasEmployeeOverride = $employee->attendance_time_in !== null
                || $employee->attendance_time_out !== null
                || $employee->attendance_grace_period_minutes !== null;

            $effectiveTimeIn = $hasEmployeeOverride
                ? ($employee->attendance_time_in ?: $defaultTimeIn)
                : ($project?->time_in ?: $defaultTimeIn);

            $effectiveTimeOut = $hasEmployeeOverride
                ? ($employee->attendance_time_out ?: $defaultTimeOut)
                : ($project?->time_out ?: $defaultTimeOut);

            $effectiveGrace = $hasEmployeeOverride
                ? (($employee->attendance_grace_period_minutes !== null)
                    ? (int) $employee->attendance_grace_period_minutes
                    : $defaultGrace)
                : (($project?->grace_period_minutes !== null)
                    ? (int) $project->grace_period_minutes
                    : $defaultGrace);

            return [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => trim($employee->first_name . ' ' . $employee->last_name),
                'position' => $employee->positionRate?->position_name ?? 'N/A',
                'activity_status' => $employee->activity_status,
                'project' => $project ? [
                    'id' => $project->id,
                    'name' => $project->name,
                ] : null,
                'attendance_time_in' => $employee->attendance_time_in,
                'attendance_time_out' => $employee->attendance_time_out,
                'attendance_grace_period_minutes' => $employee->attendance_grace_period_minutes,
                'schedule_source' => $hasEmployeeOverride ? 'employee' : 'project',
                'effective_schedule' => [
                    'time_in' => $effectiveTimeIn,
                    'time_out' => $effectiveTimeOut,
                    'grace_period_minutes' => $effectiveGrace,
                ],
            ];
        });

        return response()->json($employees);
    }

    /**
     * Set or clear attendance schedule override for a single employee.
     */
    public function updateSchedule(Request $request, Employee $employee)
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'message' => 'Only admin can apply attendance schedule changes directly.',
            ], 403);
        }

        $validated = $request->validate([
            'attendance_time_in' => 'nullable|date_format:H:i',
            'attendance_time_out' => 'nullable|date_format:H:i',
            'attendance_grace_period_minutes' => 'nullable|integer|min:0|max:180',
            'clear_override' => 'nullable|boolean',
        ]);

        if (($validated['clear_override'] ?? false) === true) {
            $payload = [
                'attendance_time_in' => null,
                'attendance_time_out' => null,
                'attendance_grace_period_minutes' => null,
            ];
        } else {
            $payload = [
                'attendance_time_in' => $validated['attendance_time_in'] ?? null,
                'attendance_time_out' => $validated['attendance_time_out'] ?? null,
                'attendance_grace_period_minutes' => $validated['attendance_grace_period_minutes'] ?? null,
            ];
        }

        $employee->update($payload);

        $this->recalculateEmployeeAttendances([$employee->id]);

        return response()->json([
            'message' => 'Employee attendance schedule updated successfully',
            'employee_id' => $employee->id,
            'schedule' => [
                'attendance_time_in' => $employee->attendance_time_in,
                'attendance_time_out' => $employee->attendance_time_out,
                'attendance_grace_period_minutes' => $employee->attendance_grace_period_minutes,
            ],
        ]);
    }

    /**
     * Set or clear attendance schedule override for multiple employees.
     */
    public function bulkSchedule(Request $request)
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json([
                'message' => 'Only admin can apply attendance schedule changes directly.',
            ], 403);
        }

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'attendance_time_in' => 'nullable|date_format:H:i',
            'attendance_time_out' => 'nullable|date_format:H:i',
            'attendance_grace_period_minutes' => 'nullable|integer|min:0|max:180',
            'clear_override' => 'nullable|boolean',
        ]);

        $employeeIds = $validated['employee_ids'];

        if (($validated['clear_override'] ?? false) === true) {
            $payload = [
                'attendance_time_in' => null,
                'attendance_time_out' => null,
                'attendance_grace_period_minutes' => null,
            ];
        } else {
            $payload = [
                'attendance_time_in' => $validated['attendance_time_in'] ?? null,
                'attendance_time_out' => $validated['attendance_time_out'] ?? null,
                'attendance_grace_period_minutes' => $validated['attendance_grace_period_minutes'] ?? null,
            ];
        }

        Employee::whereIn('id', $employeeIds)->update($payload);
        $this->recalculateEmployeeAttendances($employeeIds);

        return response()->json([
            'message' => 'Employee attendance schedules updated successfully',
            'updated_count' => count($employeeIds),
        ]);
    }

    /**
     * Recalculate attendance hours/status for employees after schedule changes.
     */
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

    /**
     * Get list of unique departments
     */
    public function getDepartments()
    {
        try {
            $departments = Cache::remember('employees:departments', now()->addMinutes(5), function () {
                return Employee::whereNotNull('department')
                    ->where('department', '!=', '')
                    ->distinct()
                    ->orderBy('department')
                    ->pluck('department');
            });

            return response()->json($departments);
        } catch (\Exception $e) {
            Log::error('Error fetching departments: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch departments'], 500);
        }
    }
}
