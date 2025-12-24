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
        // Job Applicants
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile_number');
            $table->string('phone_number')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->enum('civil_status', ['single', 'married', 'widowed', 'separated'])->nullable();

            // Applied Position
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->string('position_applied');
            $table->date('application_date');
            $table->decimal('expected_salary', 10, 2)->nullable();
            $table->date('available_start_date')->nullable();

            // Work Experience
            $table->integer('years_of_experience')->default(0);
            $table->string('previous_employer')->nullable();
            $table->string('previous_position')->nullable();

            // Education
            $table->string('highest_educational_attainment')->nullable();
            $table->string('school')->nullable();
            $table->string('course')->nullable();
            $table->integer('year_graduated')->nullable();

            // Skills
            $table->text('skills')->nullable(); // JSON or comma-separated
            $table->text('certifications')->nullable();

            // Application Status
            $table->enum('status', [
                'applied',
                'for_review',
                'for_interview',
                'interviewed',
                'for_exam',
                'passed_exam',
                'for_approval',
                'approved',
                'for_hiring',
                'hired',
                'rejected',
                'withdrawn'
            ])->default('applied');

            $table->date('interview_date')->nullable();
            $table->text('interview_notes')->nullable();
            $table->date('exam_date')->nullable();
            $table->decimal('exam_score', 5, 2)->nullable();
            $table->text('remarks')->nullable();

            // References
            $table->string('reference_name_1')->nullable();
            $table->string('reference_contact_1')->nullable();
            $table->string('reference_name_2')->nullable();
            $table->string('reference_contact_2')->nullable();

            // Source
            $table->string('source')->nullable(); // walk-in, referral, online, agency
            $table->string('referred_by')->nullable();

            // Processing
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('interviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('hired_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('hired_at')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('set null'); // If converted to employee

            $table->timestamps();
            $table->softDeletes();

            $table->index('application_number');
            $table->index(['status', 'application_date']);
            $table->index('position_applied');
            $table->index('mobile_number');
            $table->index('email');
        });

        // Applicant Documents
        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->onDelete('cascade');
            $table->string('document_type'); // resume, id, certificate, diploma, nbi_clearance, etc.
            $table->string('document_name');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable(); // pdf, docx, jpg, png
            $table->integer('file_size')->nullable(); // in bytes
            $table->date('upload_date');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['applicant_id', 'document_type']);
        });

        // Interview Schedule
        Schema::create('interview_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->onDelete('cascade');
            $table->dateTime('interview_datetime');
            $table->string('interview_type'); // initial, technical, final, panel
            $table->string('location')->nullable();
            $table->foreignId('interviewer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('interview_notes')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
            $table->enum('result', ['passed', 'failed', 'for_next_interview', 'pending'])->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['applicant_id', 'interview_datetime']);
            $table->index(['interviewer_id', 'interview_datetime']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interview_schedules');
        Schema::dropIfExists('applicant_documents');
        Schema::dropIfExists('applicants');
    }
};
