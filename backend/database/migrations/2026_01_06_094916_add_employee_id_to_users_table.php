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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->after('id');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
            $table->index('employee_id');
        });

        // Populate employee_id from employees.user_id relationship
        DB::statement('
            UPDATE users u 
            SET employee_id = e.id 
            FROM employees e 
            WHERE e.user_id = u.id
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropIndex(['employee_id']);
            $table->dropColumn('employee_id');
        });
    }
};
