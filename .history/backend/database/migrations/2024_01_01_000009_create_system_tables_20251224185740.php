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
        // Company Holidays
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date');
            $table->string('holiday_name');
            $table->enum('holiday_type', ['regular', 'special_non_working', 'special_working'])->default('regular');
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false); // For yearly recurring holidays
            $table->integer('recurring_month')->nullable(); // 1-12
            $table->integer('recurring_day')->nullable(); // 1-31
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['holiday_date', 'is_active']);
            $table->index('holiday_type');
        });

        // Company Settings
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->string('setting_type')->default('string'); // string, number, boolean, json
            $table->string('category')->nullable(); // payroll, attendance, system, etc.
            $table->string('description')->nullable();
            $table->boolean('is_editable')->default(true);
            $table->timestamps();

            $table->index('category');
        });

        // Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // create, update, delete, login, logout, etc.
            $table->string('module'); // employees, payroll, attendance, etc.
            $table->string('model_type')->nullable(); // Employee, Payroll, etc.
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['module', 'action']);
            $table->index(['model_type', 'model_id']);
            $table->index('created_at');
        });

        // System Notifications
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('type'); // info, warning, error, success
            $table->string('title');
            $table->text('message');
            $table->string('action_url')->nullable();
            $table->string('action_text')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
        });

        // Employee Documents
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('document_type'); // contract, nbi, medical, diploma, certificate, etc.
            $table->string('document_name');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable(); // pdf, docx, jpg, png
            $table->integer('file_size')->nullable(); // in bytes
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'document_type']);
            $table->index(['expiry_date', 'is_expired']);
        });

        // Leave Types
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('default_credits')->default(0); // Annual credits
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_convertible_to_cash')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Employee Leaves
        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('cascade');
            $table->date('leave_date_from');
            $table->date('leave_date_to');
            $table->decimal('number_of_days', 5, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['leave_date_from', 'leave_date_to']);
        });

        // Employee Leave Credits
        Schema::create('employee_leave_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('cascade');
            $table->integer('year');
            $table->decimal('total_credits', 5, 2)->default(0);
            $table->decimal('used_credits', 5, 2)->default(0);
            $table->decimal('available_credits', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
            $table->index(['employee_id', 'year']);
        });

        // Sync Queue (for offline-first capability)
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('action'); // create, update, delete
            $table->string('model_type'); // Employee, Attendance, etc.
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('data');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
        Schema::dropIfExists('employee_leave_credits');
        Schema::dropIfExists('employee_leaves');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('system_notifications');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('holidays');
    }
};
