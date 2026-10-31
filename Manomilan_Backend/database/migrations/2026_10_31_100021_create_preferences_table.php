<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('preferred_religion')->nullable();
            $table->string('preferred_caste')->nullable();
            $table->string('preferred_sub_caste')->nullable();
            $table->integer('age_from')->nullable();
            $table->integer('age_to')->nullable();
            $table->string('height_from')->nullable();
            $table->string('height_to')->nullable();
            $table->string('preferred_education')->nullable();
            $table->string('working_partner')->nullable();
            $table->string('preferred_working_field')->nullable();
            $table->string('expected_income')->nullable();
            $table->string('family_type')->nullable();
            $table->string('financial_background')->nullable();
            $table->string('family_values')->nullable();
            $table->string('preferred_body_type')->nullable();
            $table->string('preferred_location')->nullable();
            $table->string('preferred_country')->nullable();
            $table->string('preferred_state')->nullable();
            $table->string('preferred_city')->nullable();
            $table->text('other_expectations')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

            $table->unique('user_id', 'preferences_user_id_unique');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preferences');
    }
};
