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
            // Add holiday-related columns after days_worked with checks to prevent duplicates
            if (!Schema::hasColumn('payroll_items', 'regular_days')) {
                $table->decimal('regular_days', 5, 2)->default(0)->after('days_worked');
            }
            if (!Schema::hasColumn('payroll_items', 'holiday_days')) {
                $table->decimal('holiday_days', 5, 2)->default(0)->after('regular_days');
            }
            if (!Schema::hasColumn('payroll_items', 'holiday_pay')) {
                $table->decimal('holiday_pay', 12, 2)->default(0)->after('holiday_days');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['regular_days', 'holiday_days', 'holiday_pay']);
        });
    }
};
