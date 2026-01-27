<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    /**
     * Fix PostgreSQL sequence for payrolls table
     * This endpoint should be called once if foreign key violations occur
     */
    public function fixPayrollSequence(Request $request)
    {
        try {
            // Only allow admin users
            if (!auth()->check() || !in_array(auth()->user()->role, ['admin'])) {
                return response()->json([
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $driver = DB::connection()->getDriverName();

            if ($driver !== 'pgsql') {
                return response()->json([
                    'message' => 'This fix is only for PostgreSQL databases.',
                    'driver' => $driver
                ], 400);
            }

            // Get the max ID from payrolls table
            $maxId = DB::table('payrolls')->max('id') ?? 0;

            // Check current sequence value
            $currentSeq = DB::selectOne("SELECT last_value FROM payrolls_id_seq")?->last_value ?? 0;

            // Check if sequence is already correct
            $isCorrect = ($maxId == 0 && $currentSeq <= 1) || ($currentSeq >= $maxId && $currentSeq - $maxId <= 1);

            if ($isCorrect) {
                return response()->json([
                    'message' => 'Sequence is already correct, no fix needed',
                    'current_max_id' => $maxId,
                    'current_sequence' => $currentSeq,
                    'next_id_will_be' => $maxId + 1,
                    'success' => true
                ]);
            }

            // If there are no payrolls, reset to 1, otherwise set to max
            // The second parameter (true/false) tells setval whether to increment on next call
            if ($maxId == 0) {
                // No payrolls exist, start from 1
                DB::statement("SELECT setval('payrolls_id_seq', 1, false)");
                $nextId = 1;
            } else {
                // Set to max ID, next call will increment
                DB::statement("SELECT setval('payrolls_id_seq', {$maxId}, true)");
                $nextId = $maxId + 1;
            }

            Log::info('Payroll sequence fixed', [
                'max_id' => $maxId,
                'next_id' => $nextId,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Payroll sequence fixed successfully',
                'current_max_id' => $maxId,
                'next_id_will_be' => $nextId,
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Error fixing payroll sequence: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error fixing sequence',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get database health status
     */
    public function databaseHealth(Request $request)
    {
        try {
            // Only allow admin users
            if (!auth()->check() || !in_array(auth()->user()->role, ['admin'])) {
                return response()->json([
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $driver = DB::connection()->getDriverName();
            $health = [
                'database_driver' => $driver,
                'connection' => 'ok',
                'timestamp' => now()->toISOString(),
            ];

            // Database Size & Performance
            if ($driver === 'pgsql') {
                $dbSize = DB::selectOne("SELECT pg_database_size(current_database()) as size")->size ?? 0;
                $health['database'] = [
                    'size_mb' => round($dbSize / 1024 / 1024, 2),
                    'size_formatted' => $this->formatBytes($dbSize),
                ];
            }

            // Payroll Health Check
            $maxPayrollId = DB::table('payrolls')->max('id') ?? 0;
            $payrollCount = DB::table('payrolls')->count();
            $draftPayrolls = DB::table('payrolls')->where('status', 'draft')->count();
            $finalizedPayrolls = DB::table('payrolls')->where('status', 'finalized')->count();
            $orphanedPayrollItems = DB::table('payroll_items')
                ->whereNotIn('payroll_id', function ($query) {
                    $query->select('id')->from('payrolls');
                })
                ->count();

            if ($driver === 'pgsql') {
                $currentSeq = DB::selectOne("SELECT last_value FROM payrolls_id_seq")?->last_value ?? 0;
                $sequenceOk = ($maxPayrollId == 0 && $currentSeq <= 1) || ($currentSeq >= $maxPayrollId && $currentSeq - $maxPayrollId <= 1);
            } else {
                $currentSeq = null;
                $sequenceOk = true;
            }

            $health['payrolls'] = [
                'total' => $payrollCount,
                'draft' => $draftPayrolls,
                'finalized' => $finalizedPayrolls,
                'max_id' => $maxPayrollId,
                'sequence_value' => $currentSeq,
                'sequence_ok' => $sequenceOk,
                'orphaned_items' => $orphanedPayrollItems,
                'has_issues' => !$sequenceOk || $orphanedPayrollItems > 0,
            ];

            // Employee Health Check
            $totalEmployees = DB::table('employees')->count();
            $activeEmployees = DB::table('employees')->where('activity_status', 'active')->count();
            $inactiveEmployees = $totalEmployees - $activeEmployees;
            $employeesWithoutPosition = DB::table('employees')->whereNull('position_id')->count();
            $employeesWithoutProject = DB::table('employees')->whereNull('project_id')->count();
            $employeesWithoutUser = DB::table('employees')
                ->leftJoin('users', 'employees.id', '=', 'users.employee_id')
                ->whereNull('users.id')
                ->count();

            $health['employees'] = [
                'total' => $totalEmployees,
                'active' => $activeEmployees,
                'inactive' => $inactiveEmployees,
                'without_position' => $employeesWithoutPosition,
                'without_project' => $employeesWithoutProject,
                'without_user_account' => $employeesWithoutUser,
                'has_issues' => $employeesWithoutPosition > 0 || $activeEmployees == 0,
            ];

            // Attendance Health Check
            $totalAttendance = DB::table('attendance')->count();
            $thisMonthAttendance = DB::table('attendance')
                ->whereYear('attendance_date', now()->year)
                ->whereMonth('attendance_date', now()->month)
                ->count();
            $pendingCorrections = DB::table('attendance_corrections')
                ->where('status', 'pending')
                ->count();

            $health['attendance'] = [
                'total_records' => $totalAttendance,
                'this_month' => $thisMonthAttendance,
                'pending_corrections' => $pendingCorrections,
                'has_issues' => $pendingCorrections > 10,
            ];

            // User Accounts Health Check
            $totalUsers = DB::table('users')->count();
            $adminUsers = DB::table('users')->where('role', 'admin')->count();
            $usersWithoutEmployees = DB::table('users')->whereNull('employee_id')->count();
            $inactiveUsers = DB::table('users')->where('is_active', false)->count();

            $health['users'] = [
                'total' => $totalUsers,
                'admins' => $adminUsers,
                'without_employee' => $usersWithoutEmployees,
                'inactive' => $inactiveUsers,
                'has_issues' => $adminUsers == 0,
            ];

            // Pending Approvals
            $pendingLeaves = DB::table('employee_leaves')->where('status', 'pending')->count();
            $pendingLoans = DB::table('employee_loans')->where('status', 'pending')->count();
            $pendingResignations = DB::table('resignations')->where('status', 'pending')->count();

            $health['pending_approvals'] = [
                'leaves' => $pendingLeaves,
                'loans' => $pendingLoans,
                'resignations' => $pendingResignations,
                'total' => $pendingLeaves + $pendingLoans + $pendingResignations,
            ];

            // Overall System Health
            $criticalIssues = 0;
            $warnings = 0;

            if (!$sequenceOk || $orphanedPayrollItems > 0) $criticalIssues++;
            if ($activeEmployees == 0) $criticalIssues++;
            if ($adminUsers == 0) $criticalIssues++;
            if ($employeesWithoutPosition > 0) $warnings++;
            if ($pendingCorrections > 10) $warnings++;

            $health['overall'] = [
                'status' => $criticalIssues > 0 ? 'critical' : ($warnings > 0 ? 'warning' : 'healthy'),
                'critical_issues' => $criticalIssues,
                'warnings' => $warnings,
            ];

            return response()->json($health);
        } catch (\Exception $e) {
            Log::error('Error checking database health: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error checking database health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Clear all orphaned data and reset sequences (DANGER - use with caution)
     */
    public function cleanDatabase(Request $request)
    {
        try {
            // Only allow admin users
            if (!auth()->check() || !in_array(auth()->user()->role, ['admin'])) {
                return response()->json([
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            // Require confirmation parameter
            if ($request->input('confirm') !== 'yes-delete-orphaned-data') {
                return response()->json([
                    'message' => 'Confirmation required. Send confirm=yes-delete-orphaned-data'
                ], 400);
            }

            $driver = DB::connection()->getDriverName();

            if ($driver !== 'pgsql') {
                return response()->json([
                    'message' => 'This operation is only for PostgreSQL databases.'
                ], 400);
            }

            DB::beginTransaction();

            // Delete any orphaned payroll items (where payroll doesn't exist)
            $orphanedItems = DB::statement("
                DELETE FROM payroll_items 
                WHERE payroll_id NOT IN (SELECT id FROM payrolls)
            ");

            // Reset sequences for all tables
            $tables = ['payrolls', 'payroll_items'];
            foreach ($tables as $table) {
                $maxId = DB::table($table)->max('id') ?? 0;
                $seqName = $table . '_id_seq';

                if ($maxId == 0) {
                    DB::statement("SELECT setval('{$seqName}', 1, false)");
                } else {
                    DB::statement("SELECT setval('{$seqName}', {$maxId}, true)");
                }
            }

            DB::commit();

            Log::info('Database cleaned and sequences reset', [
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Database cleaned successfully',
                'orphaned_items_removed' => $orphanedItems ?? 0,
                'success' => true
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cleaning database: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error cleaning database',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
