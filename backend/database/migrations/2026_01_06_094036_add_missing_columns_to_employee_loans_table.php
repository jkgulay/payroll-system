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
        Schema::table('employee_loans', function (Blueprint $table) {
            $table->decimal('semi_monthly_amortization', 10, 2)->nullable()->after('monthly_amortization');
            $table->string('payment_frequency', 20)->default('monthly')->after('semi_monthly_amortization');
            $table->text('notes')->nullable()->after('reference_number');
            $table->foreignId('requested_by')->nullable()->constrained('users')->after('notes');
            $table->foreignId('created_by')->nullable()->constrained('users')->after('requested_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->after('approval_notes');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_loans', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'semi_monthly_amortization',
                'payment_frequency',
                'notes',
                'requested_by',
                'created_by',
                'approval_notes',
                'rejected_by',
                'rejected_at',
                'rejection_reason'
            ]);
        });
    }
};
