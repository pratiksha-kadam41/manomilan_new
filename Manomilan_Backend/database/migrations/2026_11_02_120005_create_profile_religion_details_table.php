<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_religion_details', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('profile_id')->unique('unique_profile_religion');
                    $table->string('religion', 191)->nullable();
                    $table->string('caste', 191)->nullable();
                    $table->string('sub_caste', 191)->nullable();
                    $table->string('community', 191)->nullable();
                    $table->string('horoscope', 100)->nullable();
                    $table->boolean('horoscope_match_required')->nullable();
                    $table->string('manglik_status', 100)->nullable();
                    $table->string('rashi', 100)->nullable();
                    $table->string('nakshatra', 100)->nullable();
                    $table->string('charan', 50)->nullable();
                    $table->string('nadi', 100)->nullable();
                    $table->string('gan', 100)->nullable();
                    $table->string('gotra', 191)->nullable();
                    $table->timestamp('created_at')->nullable()->useCurrent();
                    $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
                    $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_religion_details');
    }
};
