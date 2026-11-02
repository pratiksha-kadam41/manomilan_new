<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_partner_preferences', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('profile_id')->unique();
                    $table->unsignedInteger('age_from')->nullable();
                    $table->unsignedInteger('age_to')->nullable();
                    $table->string('height_from', 50)->nullable();
                    $table->string('height_to', 50)->nullable();
                    $table->string('preferred_education')->nullable();
                    $table->string('preferred_working_field')->nullable();
                    $table->string('preferred_caste')->nullable();
                    $table->string('preferred_location')->nullable();
                    $table->text('other_expectations')->nullable();
                    $table->timestamp('created_at')->nullable()->useCurrent();
                    $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
                    $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_partner_preferences');
    }
};
