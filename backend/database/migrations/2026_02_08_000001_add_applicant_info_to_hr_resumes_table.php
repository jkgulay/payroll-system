<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_resumes', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('user_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('email')->nullable()->after('last_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('position_applied')->nullable()->after('phone');
            $table->text('notes')->nullable()->after('position_applied');
            
            // Index for duplicate checking based on email
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('hr_resumes', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropColumn([
                'first_name',
                'last_name', 
                'email',
                'phone',
                'position_applied',
                'notes',
            ]);
        });
    }
};
