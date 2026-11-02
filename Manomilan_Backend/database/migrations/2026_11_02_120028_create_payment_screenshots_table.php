<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_screenshots', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('profile_id');
                    $table->string('screenshot_path');
                    $table->boolean('payment_verified')->default(false);
                    $table->timestamps();
                    $table->index(['profile_id', 'payment_verified', 'created_at'], 'payment_screenshots_profile_status_created_index');
                    $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_screenshots');
    }
};
