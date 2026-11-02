<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_visitors', function (Blueprint $table): void {
                    $table->id();
                    $table->bigInteger('profile_id')->nullable();
                    $table->bigInteger('visitor_id')->nullable();
                    $table->timestamps();
                    $table->index(['profile_id', 'visitor_id'], 'profile_visitors_profile_visitor_index');
                    $table->index(['visitor_id', 'profile_id'], 'profile_visitors_visitor_profile_index');
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_visitors');
    }
};
