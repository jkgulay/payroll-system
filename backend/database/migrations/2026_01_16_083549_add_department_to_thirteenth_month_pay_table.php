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
        Schema::table('thirteenth_month_pay', function (Blueprint $table) {
            $table->string('department')->nullable()->after('period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thirteenth_month_pay', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }
};
