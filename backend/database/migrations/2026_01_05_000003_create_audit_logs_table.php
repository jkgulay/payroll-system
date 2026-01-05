<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates audit_logs table to track salary changes and other critical employee updates
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type'); // e.g., 'App\Models\Employee'
            $table->unsignedBigInteger('auditable_id'); // Employee ID
            $table->string('event'); // 'created', 'updated', 'deleted'
            $table->string('field_name')->nullable(); // Which field changed (e.g., 'basic_salary')
            $table->text('old_value')->nullable(); // Previous value
            $table->text('new_value')->nullable(); // New value
            $table->text('description')->nullable(); // Human-readable description
            $table->unsignedBigInteger('user_id')->nullable(); // Who made the change
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Indexes for fast lookups
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('user_id');
            $table->index('field_name');
            $table->index('created_at');
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
