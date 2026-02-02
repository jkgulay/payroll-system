<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attendance DROP CONSTRAINT IF EXISTS attendance_holiday_type_check');

        DB::statement("ALTER TABLE attendance ADD CONSTRAINT attendance_holiday_type_check CHECK (holiday_type IN ('regular', 'special', 'special_non_working', 'special_working') OR holiday_type IS NULL)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendance DROP CONSTRAINT IF EXISTS attendance_holiday_type_check');

        DB::statement("ALTER TABLE attendance ADD CONSTRAINT attendance_holiday_type_check CHECK (holiday_type IN ('regular', 'special_non_working') OR holiday_type IS NULL)");
    }
};
