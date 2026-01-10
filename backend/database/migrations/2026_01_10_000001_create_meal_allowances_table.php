<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_allowances', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->string('title'); // e.g., "Meal Allowance for November 26 - December 10, 2025 @ CSU MED 2"
            $table->date('period_start');
            $table->date('period_end');
            $table->string('location')->nullable(); // e.g., "CSU MED 2"
            $table->string('project_name')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('set null');
            $table->foreignId('position_id')->nullable()->constrained('position_rates')->onDelete('set null');
            $table->string('status')->default('draft'); // draft, pending_approval, approved, rejected
            $table->text('notes')->nullable();
            
            // Approval workflow
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            // Signatories
            $table->string('prepared_by_name')->nullable();
            $table->string('checked_by_name')->nullable();
            $table->string('verified_by_name')->nullable();
            $table->string('recommended_by_name')->nullable();
            $table->string('approved_by_name')->nullable();
            
            // PDF path
            $table->string('pdf_path')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_allowances');
    }
};
