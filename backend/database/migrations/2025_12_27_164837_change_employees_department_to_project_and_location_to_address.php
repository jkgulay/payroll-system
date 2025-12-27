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
        Schema::table('employees', function (Blueprint $table) {
            // Drop old foreign key and column
            $table->dropForeign(['department_id']);
            $table->dropForeign(['location_id']);
            $table->dropColumn(['department_id', 'location_id']);

            // Add new columns
            $table->foreignId('project_id')->after('emergency_contact_number')->constrained('projects');
            $table->text('worker_address')->nullable()->after('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop new columns
            $table->dropForeign(['project_id']);
            $table->dropColumn(['project_id', 'worker_address']);

            // Restore old columns
            $table->foreignId('department_id')->after('emergency_contact_number')->constrained('departments');
            $table->foreignId('location_id')->after('department_id')->constrained('locations');
        });
    }
};
