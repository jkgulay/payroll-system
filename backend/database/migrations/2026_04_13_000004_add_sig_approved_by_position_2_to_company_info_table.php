<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            if (!Schema::hasColumn('company_info', 'sig_approved_by_position_2')) {
                $table->string('sig_approved_by_position_2')
                    ->nullable()
                    ->after('sig_approved_by_2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            if (Schema::hasColumn('company_info', 'sig_approved_by_position_2')) {
                $table->dropColumn('sig_approved_by_position_2');
            }
        });
    }
};
