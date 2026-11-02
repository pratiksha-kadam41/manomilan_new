<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_downtime_settings', function (Blueprint $table): void {
                    $table->id();
                    $table->boolean('enabled')->default(false);
                    $table->string('title', 150)->default('Scheduled Downtime');
                    $table->text('message')->nullable();
                    $table->dateTime('starts_at')->nullable();
                    $table->dateTime('ends_at')->nullable();
                    $table->boolean('show_countdown')->default(true);
                    $table->string('contact_email', 150)->nullable();
                    $table->string('contact_phone', 30)->nullable();
                    $table->boolean('show_contact')->default(true);
                    $table->string('display_location', 20)->default('both');
                    $table->timestamps();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_downtime_settings');
    }
};
