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
        // Drop the old constraint first
        DB::statement("ALTER TABLE holidays DROP CONSTRAINT IF EXISTS holidays_holiday_type_check");

        // Rename columns to match our Holiday model
        if (Schema::hasColumn('holidays', 'holiday_date') && !Schema::hasColumn('holidays', 'date')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->renameColumn('holiday_date', 'date');
            });
        }

        if (Schema::hasColumn('holidays', 'holiday_name') && !Schema::hasColumn('holidays', 'name')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->renameColumn('holiday_name', 'name');
            });
        }

        if (Schema::hasColumn('holidays', 'holiday_type') && !Schema::hasColumn('holidays', 'type')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->renameColumn('holiday_type', 'type');
            });
        }

        // Now update any existing 'special_non_working' or 'special_working' to just 'special'
        // This must happen AFTER renaming holiday_type to type
        DB::statement("UPDATE holidays SET type = 'special' WHERE type IN ('special_non_working', 'special_working')");

        // Add the new constraint with just 'regular' and 'special'
        DB::statement("ALTER TABLE holidays ADD CONSTRAINT holidays_type_check CHECK (type IN ('regular', 'special'))");

        Schema::table('holidays', function (Blueprint $table) {
            // Add tracking fields if they don't exist
            if (!Schema::hasColumn('holidays', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('holidays', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            }

            // Add soft deletes if it doesn't exist
            if (!Schema::hasColumn('holidays', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename back to original names
        if (Schema::hasColumn('holidays', 'date') && !Schema::hasColumn('holidays', 'holiday_date')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->renameColumn('date', 'holiday_date');
            });
        }

        if (Schema::hasColumn('holidays', 'name') && !Schema::hasColumn('holidays', 'holiday_name')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->renameColumn('name', 'holiday_name');
            });
        }

        if (Schema::hasColumn('holidays', 'type') && !Schema::hasColumn('holidays', 'holiday_type')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->renameColumn('type', 'holiday_type');
            });
        }

        Schema::table('holidays', function (Blueprint $table) {
            if (Schema::hasColumn('holidays', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('holidays', 'updated_by')) {
                $table->dropForeign(['updated_by']);
                $table->dropColumn('updated_by');
            }

            if (Schema::hasColumn('holidays', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        // Restore original enum
        DB::statement("ALTER TABLE holidays DROP CONSTRAINT IF EXISTS holidays_type_check");
        DB::statement("ALTER TABLE holidays ADD CONSTRAINT holidays_holiday_type_check CHECK (holiday_type IN ('regular', 'special_non_working', 'special_working'))");
    }
};
