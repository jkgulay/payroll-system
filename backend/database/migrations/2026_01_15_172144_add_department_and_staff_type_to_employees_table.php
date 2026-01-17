<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'department')) {
                $table->string('department', 100)->nullable()->after('project_id');
            }
            if (!Schema::hasColumn('employees', 'staff_type')) {
                $table->string('staff_type', 50)->nullable()->after('department');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'department')) {
                $table->dropColumn('department');
            }
            if (Schema::hasColumn('employees', 'staff_type')) {
                $table->dropColumn('staff_type');
            }
        });
    }
};
