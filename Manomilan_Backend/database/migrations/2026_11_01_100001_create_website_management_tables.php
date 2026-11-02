<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_settings')) {
            Schema::create('website_settings', function (Blueprint $table) {
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

        if (! Schema::hasTable('website_downtime_settings')) {
            Schema::create('website_downtime_settings', function (Blueprint $table) {
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

        if (! Schema::hasTable('website_maintenance_settings')) {
            Schema::create('website_maintenance_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(false);
                $table->string('title', 150)->default('We will be back soon');
                $table->text('message')->nullable();
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('website_maintenance_settings');
        Schema::dropIfExists('website_downtime_settings');
        Schema::dropIfExists('website_settings');
    }
};
