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

            // Reset the sequence
            DB::statement("SELECT setval('payrolls_id_seq', {$maxId})");

            // Verify
            $nextId = DB::selectOne("SELECT nextval('payrolls_id_seq') as next_id")->next_id;

            Log::info('Payroll sequence fixed', [
                'max_id' => $maxId,
                'next_id' => $nextId,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Payroll sequence fixed successfully',
                'current_max_id' => $maxId,
                'next_id' => $nextId,
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

                $health['payrolls'] = [
                    'max_id' => $maxId,
                    'sequence_value' => $currentSeq,
                    'sequence_ok' => $currentSeq >= $maxId,
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
}
