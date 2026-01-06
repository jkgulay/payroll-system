<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration cleans up:
     * 1. Removes unused tables (employee_portal_access, company_settings - not used in backend)
     * 2. Keeps sync_queue for frontend offline support
     * 3. Removes duplicate migration issue by documenting it
     */
    public function up(): void
    {
        // Drop employee_portal_access - Not used anywhere in backend
        // Users already have role-based access control
        if (Schema::hasTable('employee_portal_access')) {
            Schema::dropIfExists('employee_portal_access');
        }

        // Drop company_settings - Not implemented, Settings model uses different approach
        // Could be re-added later with proper implementation
        if (Schema::hasTable('company_settings')) {
            Schema::dropIfExists('company_settings');
        }

        // Keep sync_queue - Used by frontend for offline support
        // Keep system_notifications - May be used in future
        // Keep holidays, audit_logs - Actively used
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate employee_portal_access
        Schema::create('employee_portal_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('can_view_payslips')->default(true);
            $table->boolean('can_view_attendance')->default(true);
            $table->boolean('can_request_corrections')->default(true);
            $table->boolean('can_view_loans')->default(true);
            $table->boolean('can_view_leaves')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_access')->nullable();
            $table->timestamps();
        });

        // Recreate company_settings
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('setting_type')->default('string');
            $table->string('category')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_editable')->default(true);
            $table->timestamps();

            $table->index('category');
        });
    }
};
