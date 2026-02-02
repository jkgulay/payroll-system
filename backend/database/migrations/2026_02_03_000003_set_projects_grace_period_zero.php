<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('projects')->update(['grace_period_minutes' => 0]);
    }

    public function down(): void
    {
        // No-op: previous grace period values are unknown.
    }
};
