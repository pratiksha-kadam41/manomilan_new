<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('melava_pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64);
            $table->string('mobile', 10);
            $table->boolean('is_existing_website_user')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->longText('registration_data');
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('token', 'melava_pending_token_unique');
            $table->index('mobile', 'melava_pending_mobile_index');
            $table->index('expires_at', 'melava_pending_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('melava_pending_registrations');
    }
};
