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
            $table->boolean('has_water_allowance')->default(false)->after('basic_salary');
            $table->decimal('water_allowance', 10, 2)->default(0)->after('has_water_allowance');
            $table->boolean('has_cola')->default(false)->after('water_allowance');
            $table->decimal('cola', 10, 2)->default(0)->after('has_cola');
            $table->boolean('has_incentives')->default(false)->after('cola');
            $table->decimal('incentives', 10, 2)->default(0)->after('has_incentives');
            $table->boolean('has_ppe')->default(false)->after('incentives');
            $table->decimal('ppe', 10, 2)->default(0)->after('has_ppe');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'has_water_allowance',
                'water_allowance',
                'has_cola',
                'cola',
                'has_incentives',
                'incentives',
                'has_ppe',
                'ppe',
            ]);
        });
    }
};
