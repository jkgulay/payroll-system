<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_allowances', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_allowances', 'request_batch_id')) {
                $table->string('request_batch_id', 64)
                    ->nullable()
                    ->after('status');
                $table->index('request_batch_id', 'idx_employee_allowances_request_batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_allowances', function (Blueprint $table) {
            if (Schema::hasColumn('employee_allowances', 'request_batch_id')) {
                $table->dropIndex('idx_employee_allowances_request_batch_id');
                $table->dropColumn('request_batch_id');
            }
        });
    }
};
