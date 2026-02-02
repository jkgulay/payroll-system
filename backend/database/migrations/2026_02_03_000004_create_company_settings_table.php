<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('company_settings')) {
            Schema::create('company_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key')->unique();
                $table->text('setting_value')->nullable();
                $table->string('setting_type')->default('string');
                $table->string('category')->nullable();
                $table->string('description')->nullable();
                $table->boolean('is_editable')->default(true);
                $table->timestamps();

                $table->index('category');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
