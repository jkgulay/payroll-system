<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'overtime_employee_ids')) {
                $table->json('overtime_employee_ids')->nullable()->after('deduct_pagibig');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (Schema::hasColumn('payrolls', 'overtime_employee_ids')) {
                $table->dropColumn('overtime_employee_ids');
            }
        });
    }
};
