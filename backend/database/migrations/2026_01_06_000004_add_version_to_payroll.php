<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add version column for optimistic locking to prevent concurrent edit conflicts
     */
    public function up(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->integer('version')->default(0)->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
};
