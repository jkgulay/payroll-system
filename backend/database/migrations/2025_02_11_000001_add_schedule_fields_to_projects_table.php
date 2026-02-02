<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->time('time_in')->nullable()->after('description');
            $table->time('time_out')->nullable()->after('time_in');
            $table->unsignedSmallInteger('grace_period_minutes')->nullable()->after('time_out');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['time_in', 'time_out', 'grace_period_minutes']);
        });
    }
};
