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
     * Drop the old VARCHAR position column now that position_id FK is in place
     */
    public function up(): void
    {
        // Check if any employees still using VARCHAR position without position_id
        $unmatchedCount = DB::table('employees')
            ->whereNull('position_id')
            ->whereNotNull('position')
            ->count();

        if ($unmatchedCount > 0) {
            throw new \Exception(
                "Cannot drop position column: {$unmatchedCount} employees have position data but no position_id. " .
                    "Please run the previous migration (2026_01_06_000002) to fix this issue first."
            );
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('position', 100)->nullable()->after('position_id');
        });

        // Restore position names from position_rates
        DB::statement("
            UPDATE employees e
            SET position = pr.position_name
            FROM position_rates pr
            WHERE e.position_id = pr.id
        ");
    }
};
