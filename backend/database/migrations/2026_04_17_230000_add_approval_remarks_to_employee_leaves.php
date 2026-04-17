<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('employee_leaves', 'approval_remarks')) {
            Schema::table('employee_leaves', function (Blueprint $table) {
                $table->text('approval_remarks')->nullable()->after('approved_at');
            });

            // Preserve existing approved-leave remarks previously stored in rejection_reason.
            DB::statement("UPDATE employee_leaves
                SET approval_remarks = rejection_reason
                WHERE status = 'approved'
                  AND rejection_reason IS NOT NULL
                  AND approval_remarks IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('employee_leaves', 'approval_remarks')) {
            Schema::table('employee_leaves', function (Blueprint $table) {
                $table->dropColumn('approval_remarks');
            });
        }
    }
};
