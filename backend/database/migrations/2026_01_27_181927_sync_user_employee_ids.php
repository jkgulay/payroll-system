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
        // Sync employee_id on users table from employees table
        // For each employee that has a user_id, update the corresponding user's employee_id
        DB::statement("
            UPDATE users 
            SET employee_id = employees.id 
            FROM employees 
            WHERE employees.user_id = users.id 
            AND users.employee_id IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - the data sync is non-destructive
    }
};
