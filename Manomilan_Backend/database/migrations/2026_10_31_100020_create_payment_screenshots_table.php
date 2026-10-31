<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_screenshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('screenshot_path');
            $table->boolean('payment_verified')->default(false);
            $table->timestamps();

            $table->foreign('profile_id')->references('id')->on('profiles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_screenshots');
    }
};
