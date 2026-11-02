<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_documents', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('profile_id')->unique();
                    $table->string('aadhaar_card', 500)->nullable();
                    $table->string('pan_card', 500)->nullable();
                    $table->string('passport', 500)->nullable();
                    $table->string('birth_certificate', 500)->nullable();
                    $table->string('address_proof', 500)->nullable();
                    $table->string('education_certificate', 500)->nullable();
                    $table->string('profession_proof', 500)->nullable();
                    $table->string('income_proof', 500)->nullable();
                    $table->string('caste_certificate', 500)->nullable();
                    $table->string('medical_fitness_certificate', 500)->nullable();
                    $table->timestamp('created_at')->nullable()->useCurrent();
                    $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
                    $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_documents');
    }
};
