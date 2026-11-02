<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_interests', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('user_id');
                    $table->unsignedBigInteger('profile_id');
                    $table->timestamps();
                    $table->string('status', 20)->default('pending');
                    $table->unique(['user_id', 'profile_id'], 'user_profile_unique');
                    $table->index(['profile_id', 'created_at'], 'user_interests_profile_created_index');
                    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                    $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_interests');
    }
};
