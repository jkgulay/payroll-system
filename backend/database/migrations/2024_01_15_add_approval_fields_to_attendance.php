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
        Schema::table('attendance', function (Blueprint $table) {
            // Add new approval fields that controller uses but migration doesn't have
            $table->boolean('is_approved')->default(false)->after('approval_status');
            $table->boolean('is_rejected')->default(false)->after('is_approved');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->after('is_rejected');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('approval_notes')->nullable()->after('rejection_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['is_approved', 'is_rejected', 'rejected_by', 'rejected_at', 'approval_notes']);
        });
    }
};
