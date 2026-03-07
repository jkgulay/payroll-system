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
            // Add Sunday hours column after special_ot_pay
            if (!Schema::hasColumn('payroll_items', 'sunday_hours')) {
                $table->decimal('sunday_hours', 5, 2)->default(0)->after('special_ot_pay');
            }
            if (!Schema::hasColumn('payroll_items', 'sunday_pay')) {
                $table->decimal('sunday_pay', 12, 2)->default(0)->after('sunday_hours');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_items', 'sunday_hours')) {
                $table->dropColumn('sunday_hours');
            }
            if (Schema::hasColumn('payroll_items', 'sunday_pay')) {
                $table->dropColumn('sunday_pay');
            }
        });
    }
};
