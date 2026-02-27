<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the unused basic_rate column from payroll_items.
     *
     * History: basic_rate was added by a migration but was never added to the
     * PayrollItem model's $fillable or $casts, and is not used anywhere in
     * application code. The stored rate snapshot is correctly handled by the
     * `rate` column. The `effective_rate` is a computed accessor on the model,
     * not a database column. This removes the dead/confusing column.
     */
    public function up(): void
    {
        if (Schema::hasColumn('payroll_items', 'basic_rate')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->dropColumn('basic_rate');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('payroll_items', 'basic_rate')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->decimal('basic_rate', 10, 2)->nullable()->default(0)->after('rate');
            });
        }
    }
};
