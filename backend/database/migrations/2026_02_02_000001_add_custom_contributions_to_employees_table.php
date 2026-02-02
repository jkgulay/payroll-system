<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds custom/override contribution fields to employees table
     * When set, these override the computed government contributions
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Custom SSS contribution (overrides computed value when not null)
            $table->decimal('custom_sss', 10, 2)->nullable()->after('has_pagibig');
            
            // Custom PhilHealth contribution (overrides computed value when not null)
            $table->decimal('custom_philhealth', 10, 2)->nullable()->after('custom_sss');
            
            // Custom Pag-IBIG contribution (overrides computed value when not null)
            $table->decimal('custom_pagibig', 10, 2)->nullable()->after('custom_philhealth');
            
            // Notes for contribution adjustments
            $table->text('contribution_notes')->nullable()->after('custom_pagibig');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'custom_sss',
                'custom_philhealth',
                'custom_pagibig',
                'contribution_notes'
            ]);
        });
    }
};
