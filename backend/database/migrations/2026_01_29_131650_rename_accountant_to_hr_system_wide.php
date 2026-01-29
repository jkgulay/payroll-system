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
        // Rename the accountant_resumes table to hr_resumes if it exists
        if (Schema::hasTable('accountant_resumes') && !Schema::hasTable('hr_resumes')) {
            Schema::rename('accountant_resumes', 'hr_resumes');
        }

        // Update all user roles from 'accountant' to 'hr'
        DB::table('users')
            ->where('role', 'accountant')
            ->update(['role' => 'hr']);

        // Update audit logs module references
        DB::table('audit_logs')
            ->where('module', 'accountant_resume')
            ->update(['module' => 'hr_resume']);

        // Drop and recreate the role constraint if it exists (for PostgreSQL/MySQL with constraints)
        try {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        } catch (\Exception $e) {
            // Constraint might not exist, continue
        }

        // Add new constraint with hr role
        try {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'hr', 'employee', 'payrollist'))");
        } catch (\Exception $e) {
            // MySQL doesn't support CHECK constraints in older versions, that's okay
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename hr_resumes back to accountant_resumes
        if (Schema::hasTable('hr_resumes') && !Schema::hasTable('accountant_resumes')) {
            Schema::rename('hr_resumes', 'accountant_resumes');
        }

        // Revert all user roles from 'hr' back to 'accountant'
        DB::table('users')
            ->where('role', 'hr')
            ->update(['role' => 'accountant']);

        // Revert audit logs module references
        DB::table('audit_logs')
            ->where('module', 'hr_resume')
            ->update(['module' => 'accountant_resume']);

        // Drop and recreate the role constraint
        try {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
        } catch (\Exception $e) {
            // Continue
        }

        // Add old constraint with accountant role
        try {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'accountant', 'employee', 'payrollist'))");
        } catch (\Exception $e) {
            // MySQL doesn't support CHECK constraints in older versions
        }
    }
};
