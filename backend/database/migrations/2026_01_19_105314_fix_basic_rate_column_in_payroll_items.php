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
            // Make basic_rate nullable or set a default value
            if (Schema::hasColumn('payroll_items', 'basic_rate')) {
                $table->decimal('basic_rate', 10, 2)->nullable()->default(0)->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_items', 'basic_rate')) {
                $table->decimal('basic_rate', 10, 2)->nullable(false)->change();
            }
        });
    }
};
