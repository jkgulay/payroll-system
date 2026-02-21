<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop columns that are either fully dead or cleanly superseded:
     *
     * 1. employee_government_info.is_govt_contribution_exempt
     *    – Never read in any business logic; contribution flags (has_sss,
     *      has_philhealth, has_pagibig) on employees are the canonical source.
     *
     * 2. applicants.interview_date + interview_notes
     *    – Superseded by the interview_schedules table (full scheduling model).
     *      Zero PHP reads of these columns anywhere in the codebase.
     *
     * 3. meal_allowances.project_name
     *    – String duplicate of meal_allowances.project_id → projects.name.
     *      Never written to by any controller; project() relationship is used.
     */
    public function up(): void
    {
        // 1. Drop unused govt contribution exempt flag
        if (Schema::hasColumn('employee_government_info', 'is_govt_contribution_exempt')) {
            Schema::table('employee_government_info', function (Blueprint $table) {
                $table->dropColumn('is_govt_contribution_exempt');
            });
        }

        // 2. Drop superseded interview fields from applicants
        Schema::table('applicants', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('applicants', 'interview_date'))  $columns[] = 'interview_date';
            if (Schema::hasColumn('applicants', 'interview_notes')) $columns[] = 'interview_notes';
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        // 3. Drop redundant project_name string from meal_allowances
        if (Schema::hasColumn('meal_allowances', 'project_name')) {
            Schema::table('meal_allowances', function (Blueprint $table) {
                $table->dropColumn('project_name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('employee_government_info', function (Blueprint $table) {
            $table->boolean('is_govt_contribution_exempt')->default(false)->nullable();
        });

        Schema::table('applicants', function (Blueprint $table) {
            $table->date('interview_date')->nullable();
            $table->text('interview_notes')->nullable();
        });

        Schema::table('meal_allowances', function (Blueprint $table) {
            $table->string('project_name')->nullable();
        });
    }
};
