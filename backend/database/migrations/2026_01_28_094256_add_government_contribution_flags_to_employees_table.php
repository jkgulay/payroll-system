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
            // Add flags to determine which government contributions apply to each employee
            $table->boolean('has_sss')->default(true)->after('tin_number');
            $table->boolean('has_philhealth')->default(true)->after('has_sss');
            $table->boolean('has_pagibig')->default(true)->after('has_philhealth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['has_sss', 'has_philhealth', 'has_pagibig']);
        });
    }
};
