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
        Schema::table('employees', function (Blueprint $table) {
            // Add custom_pay_rate field after basic_salary
            // This allows admin to override the position-based rate with a custom rate per employee
            $table->decimal('custom_pay_rate', 12, 2)->nullable()->after('basic_salary')
                ->comment('Custom daily rate set by admin. If set, overrides position_rate and basic_salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('custom_pay_rate');
        });
    }
};
