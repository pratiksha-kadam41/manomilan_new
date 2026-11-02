<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_settings', function (Blueprint $table): void {
                    $table->id();
                    $table->boolean('upcoming_events_enabled')->default(true);
                    $table->string('upcoming_events_label', 80)->default('Upcoming Events');
                    $table->string('upcoming_events_icon', 40)->default('calendar');
                    $table->string('upcoming_events_bg_color', 7)->default('#c4144f');
                    $table->string('upcoming_events_text_color', 7)->default('#ffffff');
                    $table->string('upcoming_events_position', 20)->default('bottom-right');
                    $table->string('upcoming_events_url', 2048)->nullable();
                    $table->boolean('upcoming_events_open_new_tab')->default(true);
                    $table->timestamps();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
