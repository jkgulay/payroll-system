<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attendance DROP CONSTRAINT IF EXISTS attendance_status_check');
        DB::statement("ALTER TABLE attendance ADD CONSTRAINT attendance_status_check CHECK (status IN ('present', 'absent', 'late', 'half_day', 'on_leave', 'leave', 'holiday', 'rest_day', 'incomplete'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendance DROP CONSTRAINT IF EXISTS attendance_status_check');
        DB::statement("ALTER TABLE attendance ADD CONSTRAINT attendance_status_check CHECK (status IN ('present', 'absent', 'late', 'half_day', 'on_leave', 'leave', 'holiday', 'rest_day'))");
    }
};
