<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add position_id foreign key to employees table for better data integrity
     * This maintains backward compatibility by keeping position VARCHAR as fallback
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Add position_id as nullable first (to allow migration of existing data)
            $table->foreignId('position_id')->nullable()->after('position')->constrained('position_rates')->onDelete('restrict');
            $table->index('position_id');
        });

        // Migrate existing position names to position_ids (PostgreSQL syntax)
        DB::statement("
            UPDATE employees e
            SET position_id = pr.id
            FROM position_rates pr
            WHERE e.position = pr.position_name
            AND e.position_id IS NULL
        ");

        // Log employees with positions not found in position_rates
        $unmatchedPositions = DB::select("
            SELECT DISTINCT e.position, COUNT(*) as count
            FROM employees e
            LEFT JOIN position_rates pr ON e.position = pr.position_name
            WHERE e.position_id IS NULL AND e.position IS NOT NULL
            GROUP BY e.position
        ");

        if (!empty($unmatchedPositions)) {
            Log::warning('Employees with positions not in position_rates table:', [
                'positions' => $unmatchedPositions,
                'action' => 'These employees will keep VARCHAR position. Add missing positions to position_rates table and re-run migration data update.'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropIndex(['position_id']);
            $table->dropColumn('position_id');
        });
    }
};
