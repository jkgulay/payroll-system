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
            
            // If there are no payrolls, reset to 1, otherwise set to max
            // The second parameter (true/false) tells setval whether to increment on next call
            if ($maxId == 0) {
                // No payrolls exist, start from 1
                DB::statement("SELECT setval('payrolls_id_seq', 1, false)");
            } else {
                // Set to max ID, next call will increment
                DB::statement("SELECT setval('payrolls_id_seq', {$maxId}, true)");
            }

            // Verify - this will get the NEXT value that will be used
            $nextIdResult = DB::selectOne("SELECT nextval('payrolls_id_seq') as next_id");
            $nextId = $nextIdResult->next_id;
            
            // Reset back so we don't skip a number
            DB::statement("SELECT setval('payrolls_id_seq', " . ($nextId - 1) . ", true)");

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
            ];

            if ($driver === 'pgsql') {
                // Check payroll sequence
                $maxId = DB::table('payrolls')->max('id') ?? 0;
                $currentSeq = DB::selectOne("SELECT last_value FROM payrolls_id_seq")?->last_value ?? 0;
                $payrollCount = DB::table('payrolls')->count();
                $activeEmployees = DB::table('employees')->where('activity_status', 'active')->count();

                $health['payrolls'] = [
                    'max_id' => $maxId,
                    'sequence_value' => $currentSeq,
                    'sequence_ok' => ($maxId == 0 && $currentSeq <= 1) || ($currentSeq >= $maxId && $currentSeq - $maxId <= 1),
                    'total_payrolls' => $payrollCount,
                ];
                
                $health['employees'] = [
                    'active_count' => $activeEmployees,
                ];
            }

            return response()->json($health);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error checking database health',
                'error' => $e->getMessage()
            ], 500);
        }
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
