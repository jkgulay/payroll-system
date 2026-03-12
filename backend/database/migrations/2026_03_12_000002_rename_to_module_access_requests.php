<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_modification_requests', function (Blueprint $table) {
            $table->string('module', 50)->default('attendance')->after('id');
            $table->index('module');
        });

        // Rename table to be generic
        Schema::rename('attendance_modification_requests', 'module_access_requests');
    }

    public function down(): void
    {
        Schema::rename('module_access_requests', 'attendance_modification_requests');

        Schema::table('attendance_modification_requests', function (Blueprint $table) {
            $table->dropIndex(['module']);
            $table->dropColumn('module');
        });
    }
};
