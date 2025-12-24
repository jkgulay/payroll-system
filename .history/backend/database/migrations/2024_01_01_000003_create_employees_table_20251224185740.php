<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->onDelete('set null');
            $table->string('employee_number', 20)->unique();

            // Personal Information
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 10)->nullable();
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('civil_status', ['single', 'married', 'widowed', 'separated'])->nullable();
            $table->string('nationality', 50)->default('Filipino');

            // Contact Information
            $table->string('email')->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->string('phone_number', 20)->nullable();

            // Address
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();

            // Emergency Contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_relationship', 50)->nullable();
            $table->string('emergency_contact_number', 20)->nullable();

            // Employment Details
            $table->foreignId('department_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->string('position', 100);
            $table->enum('employment_type', ['regular', 'contractual', 'part_time'])->default('regular');
            $table->enum('employment_status', ['active', 'resigned', 'terminated', 'retired'])->default('active');
            $table->date('date_hired');
            $table->date('date_regularized')->nullable();
            $table->date('date_separated')->nullable();

            // Salary Information
            $table->enum('salary_type', ['daily', 'monthly', 'hourly'])->default('daily');
            $table->decimal('basic_salary', 12, 2); // Daily rate for daily-paid

            // Government IDs
            $table->string('sss_number', 20)->nullable();
            $table->string('philhealth_number', 20)->nullable();
            $table->string('pagibig_number', 20)->nullable();
            $table->string('tin_number', 20)->nullable();

            // Banking
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 50)->nullable();

            // Profile
            $table->string('profile_photo')->nullable();

            // System Fields
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_number');
            $table->index('department_id');
            $table->index('location_id');
            $table->index('employment_status');
            $table->index('employment_type');
            $table->index('last_name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
