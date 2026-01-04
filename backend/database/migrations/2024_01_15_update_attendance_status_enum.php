<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL uses VARCHAR, not ENUM - no changes needed
        // The status column is already a varchar and can accept any of these values
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No changes needed
    }
};
