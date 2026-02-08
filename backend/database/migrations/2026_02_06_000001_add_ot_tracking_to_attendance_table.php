<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add OT time tracking fields to properly handle multiple punch records
     * This allows tracking when employees return for OT after completing their regular shift
     */
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            // OT session tracking - for employees who return after completing regular shift
            if (!Schema::hasColumn('attendance', 'ot_time_in')) {
                $table->time('ot_time_in')->nullable()->after('time_out');
            }
            if (!Schema::hasColumn('attendance', 'ot_time_out')) {
                $table->time('ot_time_out')->nullable()->after('ot_time_in');
            }
            if (!Schema::hasColumn('attendance', 'ot_time_in_2')) {
                $table->time('ot_time_in_2')->nullable()->after('ot_time_out')->comment('Second OT session if employee leaves and returns again');
            }
            if (!Schema::hasColumn('attendance', 'ot_time_out_2')) {
                $table->time('ot_time_out_2')->nullable()->after('ot_time_in_2')->comment('Second OT session time out');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn(['ot_time_in', 'ot_time_out', 'ot_time_in_2', 'ot_time_out_2']);
        });
    }
};
