<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_visitors', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('profile_id')->nullable();
            $table->bigInteger('visitor_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_visitors');
    }
};
