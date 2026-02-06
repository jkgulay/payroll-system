<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // If legacy "payroll" table exists, rename to "payrolls"
        if (Schema::hasTable('payroll') && !Schema::hasTable('payrolls')) {
            Schema::rename('payroll', 'payrolls');
        }

        // Fix foreign key on payroll_items to reference payrolls
        if (Schema::hasTable('payroll_items') && Schema::hasTable('payrolls')) {
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE payroll_items DROP CONSTRAINT IF EXISTS payroll_items_payroll_id_foreign');
            } else {
                try {
                    Schema::table('payroll_items', function (Blueprint $table) {
                        $table->dropForeign(['payroll_id']);
                    });
                } catch (\Throwable $e) {
                    // Ignore if foreign key does not exist or cannot be dropped
                }
            }

            Schema::table('payroll_items', function (Blueprint $table) {
                $table->foreign('payroll_id')
                    ->references('id')
                    ->on('payrolls')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if (Schema::hasTable('payroll_items')) {
            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE payroll_items DROP CONSTRAINT IF EXISTS payroll_items_payroll_id_foreign');
            } else {
                try {
                    Schema::table('payroll_items', function (Blueprint $table) {
                        $table->dropForeign(['payroll_id']);
                    });
                } catch (\Throwable $e) {
                    // Ignore if foreign key does not exist or cannot be dropped
                }
            }
        }

        // If legacy "payroll" table does not exist, rename "payrolls" back to "payroll"
        if (!Schema::hasTable('payroll') && Schema::hasTable('payrolls')) {
            Schema::rename('payrolls', 'payroll');
        }

        // Recreate FK to legacy table if present
        if (Schema::hasTable('payroll_items') && Schema::hasTable('payroll')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->foreign('payroll_id')
                    ->references('id')
                    ->on('payroll')
                    ->cascadeOnDelete();
            });
        }
    }
};
