<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_family_details', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('profile_id')->unique();
                    $table->string('father_name')->nullable();
                    $table->enum('father_status', ['Alive', 'Deceased'])->nullable();
                    $table->string('father_native_place')->nullable();
                    $table->string('father_occupation')->nullable();
                    $table->string('father_contact', 20)->nullable();
                    $table->string('mother_name')->nullable();
                    $table->enum('mother_status', ['Alive', 'Deceased'])->nullable();
                    $table->string('mother_native_place')->nullable();
                    $table->string('mother_occupation')->nullable();
                    $table->string('mother_contact', 20)->nullable();
                    $table->unsignedTinyInteger('brothers_count')->nullable();
                    $table->unsignedTinyInteger('married_brothers_count')->nullable();
                    $table->unsignedTinyInteger('sisters_count')->nullable();
                    $table->unsignedTinyInteger('married_sisters_count')->nullable();
                    $table->boolean('parents_intercaste_marriage')->nullable();
                    $table->text('parents_different_caste_details')->nullable();
                    $table->string('family_values', 100)->nullable();
                    $table->string('family_financial_status', 100)->nullable();
                    $table->decimal('family_annual_income', 15, 2)->nullable();
                    $table->timestamp('created_at')->nullable()->useCurrent();
                    $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
                    $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_family_details');
    }
};
