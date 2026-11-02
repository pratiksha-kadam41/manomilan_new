<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_employment', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('profile_id');
                    $table->string('employment_type', 100)->nullable();
                    $table->string('profession', 191)->nullable();
                    $table->string('working_field', 191)->nullable();
                    $table->string('organisation_name')->nullable();
                    $table->string('designation', 191)->nullable();
                    $table->string('employment_city', 191)->nullable();
                    $table->string('employment_duration', 100)->nullable();
                    $table->decimal('monthly_income', 15, 2)->nullable();
                    $table->decimal('annual_income', 15, 2)->nullable();
                    $table->string('income_currency', 10)->default('INR');
                    $table->decimal('original_monthly_income', 15, 2)->nullable();
                    $table->decimal('original_annual_income', 15, 2)->nullable();
                    $table->timestamp('created_at')->nullable()->useCurrent();
                    $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
                    $table->index('profile_id', 'idx_employment_profile');
                    $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_employment');
    }
};
