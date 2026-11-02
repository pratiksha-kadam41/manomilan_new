<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_education', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('profile_id');
                    $table->string('education_level', 100)->nullable();
                    $table->string('degree_name')->nullable();
                    $table->string('specialization')->nullable();
                    $table->string('university_name')->nullable();
                    $table->string('college_name')->nullable();
                    $table->year('passing_year')->nullable();
                    $table->decimal('percentage', 5, 2)->nullable();
                    $table->string('grade', 50)->nullable();
                    $table->string('education_city', 191)->nullable();
                    $table->text('additional_qualification')->nullable();
                    $table->string('education_certificate', 500)->nullable();
                    $table->timestamp('created_at')->nullable()->useCurrent();
                    $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
                    $table->index('profile_id', 'idx_education_profile');
                    $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_education');
    }
};
