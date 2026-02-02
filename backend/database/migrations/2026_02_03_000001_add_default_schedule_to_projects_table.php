<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'time_in')) {
                $table->time('time_in')->default('07:30')->after('description');
            }
            if (!Schema::hasColumn('projects', 'time_out')) {
                $table->time('time_out')->default('17:00')->after('time_in');
            }
            if (!Schema::hasColumn('projects', 'grace_period_minutes')) {
                $table->unsignedSmallInteger('grace_period_minutes')->default(0)->after('time_out');
            }
        });

        DB::table('projects')
            ->whereNull('time_in')
            ->update(['time_in' => '07:30']);

        DB::table('projects')
            ->whereNull('time_out')
            ->update(['time_out' => '17:00']);

        DB::table('projects')
            ->whereNull('grace_period_minutes')
            ->update(['grace_period_minutes' => 0]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'grace_period_minutes')) {
                $table->dropColumn('grace_period_minutes');
            }
            if (Schema::hasColumn('projects', 'time_out')) {
                $table->dropColumn('time_out');
            }
            if (Schema::hasColumn('projects', 'time_in')) {
                $table->dropColumn('time_in');
            }
        });
    }
};
