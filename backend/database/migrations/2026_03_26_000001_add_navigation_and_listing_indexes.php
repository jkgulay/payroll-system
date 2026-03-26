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
        $indexExists = function (string $table, string $index): bool {
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                $result = DB::select(
                    'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                    [$table, $index]
                );
                return !empty($result);
            }

            if ($driver === 'mysql') {
                $database = DB::getDatabaseName();
                $result = DB::select(
                    'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                    [$database, $table, $index]
                );
                return !empty($result);
            }

            return false;
        };

        Schema::table('employees', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('employees', 'idx_employees_contract_created')) {
                $table->index(['contract_type', 'created_at'], 'idx_employees_contract_created');
            }

            if (!$indexExists('employees', 'idx_employees_work_schedule_created')) {
                $table->index(['work_schedule', 'created_at'], 'idx_employees_work_schedule_created');
            }

            if (!$indexExists('employees', 'idx_employees_department_created')) {
                $table->index(['department', 'created_at'], 'idx_employees_department_created');
            }

            if (!$indexExists('employees', 'idx_employees_activity_created')) {
                $table->index(['activity_status', 'created_at'], 'idx_employees_activity_created');
            }
        });

        Schema::table('resignations', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('resignations', 'idx_resignations_status_created')) {
                $table->index(['status', 'created_at'], 'idx_resignations_status_created');
            }
        });

        Schema::table('employee_applications', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('employee_applications', 'idx_employee_apps_status_created')) {
                $table->index(['application_status', 'created_at'], 'idx_employee_apps_status_created');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('idx_employees_contract_created');
            $table->dropIndex('idx_employees_work_schedule_created');
            $table->dropIndex('idx_employees_department_created');
            $table->dropIndex('idx_employees_activity_created');
        });

        Schema::table('resignations', function (Blueprint $table) {
            $table->dropIndex('idx_resignations_status_created');
        });

        Schema::table('employee_applications', function (Blueprint $table) {
            $table->dropIndex('idx_employee_apps_status_created');
        });
    }
};
