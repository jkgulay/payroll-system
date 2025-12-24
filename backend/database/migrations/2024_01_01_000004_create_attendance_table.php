<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('attendance_date');
            
            // Time Records
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            
            // Calculated Hours
            $table->decimal('regular_hours', 5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('undertime_hours', 5, 2)->default(0);
            $table->decimal('late_hours', 5, 2)->default(0);
            $table->decimal('night_differential_hours', 5, 2)->default(0);
            
            // Status
            $table->enum('status', ['present', 'absent', 'leave', 'holiday', 'rest_day'])->default('present');
            $table->boolean('is_holiday')->default(false);
            $table->enum('holiday_type', ['regular', 'special_non_working'])->nullable();
            
            // Manual Entry
            $table->boolean('is_manual_entry')->default(false);
            $table->text('manual_reason')->nullable();
            
            // Corrections
            $table->boolean('is_edited')->default(false);
            $table->text('edit_reason')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users');
            $table->timestamp('edited_at')->nullable();
            
            // Approval
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('approved');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // System Fields
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'attendance_date']);
            $table->index('employee_id');
            $table->index('attendance_date');
            $table->index('status');
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
