<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_maintenance_settings', function (Blueprint $table): void {
                    $table->id();
                    $table->boolean('enabled')->default(false);
                    $table->string('title', 150)->default('We will be back soon');
                    $table->text('message')->nullable();
                    $table->dateTime('starts_at')->nullable();
                    $table->dateTime('ends_at')->nullable();
                    $table->timestamps();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_maintenance_settings');
    }
};
