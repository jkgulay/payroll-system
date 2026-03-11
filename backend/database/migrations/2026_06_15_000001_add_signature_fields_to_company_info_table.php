<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->string('sig_prepared_by')->nullable();
            $table->string('sig_checked_by')->nullable();
            $table->string('sig_recommended_by')->nullable();
            $table->string('sig_approved_by')->nullable();
            $table->string('sig_approved_by_position')->nullable();
            $table->string('sig_checked_by_2')->nullable();
            $table->string('sig_recommended_by_2')->nullable();
            $table->string('sig_approved_by_2')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $table->dropColumn([
                'sig_prepared_by',
                'sig_checked_by',
                'sig_recommended_by',
                'sig_approved_by',
                'sig_approved_by_position',
                'sig_checked_by_2',
                'sig_recommended_by_2',
                'sig_approved_by_2',
            ]);
        });
    }
};
