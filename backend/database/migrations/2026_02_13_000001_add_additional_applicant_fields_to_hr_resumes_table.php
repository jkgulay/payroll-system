<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_resumes', function (Blueprint $table) {
            $table->string('middle_name')->nullable()->after('first_name');
            $table->date('date_of_birth')->nullable()->after('last_name');
            $table->enum('gender', ['male', 'female'])->nullable()->after('date_of_birth');
            $table->text('address')->nullable()->after('phone');
            $table->string('department_preference')->nullable()->after('position_applied');
            $table->string('expected_salary')->nullable()->after('department_preference');
            $table->date('availability_date')->nullable()->after('expected_salary');
        });
    }

    public function down(): void
    {
        Schema::table('hr_resumes', function (Blueprint $table) {
            $table->dropColumn([
                'middle_name',
                'date_of_birth',
                'gender',
                'address',
                'department_preference',
                'expected_salary',
                'availability_date',
            ]);
        });
    }
};
