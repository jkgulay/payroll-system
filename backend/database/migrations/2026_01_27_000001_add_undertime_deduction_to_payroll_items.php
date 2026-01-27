<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            // Add undertime_hours if not exists (for tracking)
            if (!Schema::hasColumn('payroll_items', 'undertime_hours')) {
                $table->decimal('undertime_hours', 8, 2)->default(0)->after('gross_pay');
            }
            
            // Add undertime_deduction field for salary deductions
            $table->decimal('undertime_deduction', 12, 2)->default(0)->after('undertime_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['undertime_deduction']);
            // Only drop undertime_hours if it was added by this migration
            // Check if column exists before dropping
            if (Schema::hasColumn('payroll_items', 'undertime_hours')) {
                $table->dropColumn('undertime_hours');
            }
        });
    }
};
