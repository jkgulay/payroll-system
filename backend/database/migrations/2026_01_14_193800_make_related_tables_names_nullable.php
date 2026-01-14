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
        // Update employee_applications table
        if (Schema::hasTable('employee_applications')) {
            Schema::table('employee_applications', function (Blueprint $table) {
                $table->string('last_name')->nullable()->change();
                $table->string('first_name')->nullable()->change();
            });
        }
        
        // Update applicants table (if exists from recruitment module)
        if (Schema::hasTable('applicants')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->string('last_name')->nullable()->change();
                $table->string('first_name')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('employee_applications')) {
            Schema::table('employee_applications', function (Blueprint $table) {
                $table->string('last_name')->nullable(false)->change();
                $table->string('first_name')->nullable(false)->change();
            });
        }
        
        if (Schema::hasTable('applicants')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->string('last_name')->nullable(false)->change();
                $table->string('first_name')->nullable(false)->change();
            });
        }
    }
};
