<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attendance Correction Requests
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('requested_by')->constrained('users');
            
            // Original Values
            $table->time('original_time_in')->nullable();
            $table->time('original_time_out')->nullable();
            $table->string('original_status')->nullable();
            
            // Requested Changes
            $table->time('requested_time_in')->nullable();
            $table->time('requested_time_out')->nullable();
            $table->string('requested_status')->nullable();
            
            // Request Details
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();
            
            $table->timestamps();
            
            $table->index(['employee_id', 'status']);
            $table->index('requested_by');
        });

        // Employee Portal Access
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
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_portal_access');
        Schema::dropIfExists('attendance_corrections');
    }
};
