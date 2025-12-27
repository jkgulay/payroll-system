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
        Schema::create('employee_applications', function (Blueprint $table) {
            $table->id();
            
            // Personal Information
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('email')->unique();
            $table->string('mobile_number')->nullable();
            $table->text('worker_address');
            
            // Employment Information
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->string('position');
            $table->date('date_hired');
            $table->enum('employment_status', ['regular', 'probationary', 'contractual', 'active']);
            $table->enum('employment_type', ['regular', 'contractual', 'part_time']);
            $table->enum('salary_type', ['daily', 'monthly']);
            $table->decimal('basic_salary', 10, 2);
            
            // Allowances
            $table->boolean('has_water_allowance')->default(false);
            $table->decimal('water_allowance', 10, 2)->default(0);
            $table->boolean('has_cola')->default(false);
            $table->decimal('cola', 10, 2)->default(0);
            $table->boolean('has_incentives')->default(false);
            $table->decimal('incentives', 10, 2)->default(0);
            $table->boolean('has_ppe')->default(false);
            $table->decimal('ppe', 10, 2)->default(0);
            
            // Document paths
            $table->string('resume_path')->nullable();
            $table->string('id_path')->nullable();
            $table->string('contract_path')->nullable();
            $table->string('certificates_path')->nullable();
            
            // Application status
            $table->enum('application_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // HR/Accountant
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null'); // Admin
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            
            // Created employee reference (after approval)
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('application_status');
            $table->index('created_by');
            $table->index('reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_applications');
    }
};
