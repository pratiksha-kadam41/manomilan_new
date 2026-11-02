<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('melava_pending_registrations', function (Blueprint $table): void {
                    $table->id();
                    $table->string('token', 64)->unique('melava_pending_token_unique');
                    $table->string('mobile', 10);
                    $table->boolean('is_existing_website_user')->default(false);
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->unsignedBigInteger('profile_id')->nullable();
                    $table->unsignedBigInteger('melava_campaign_id')->nullable()->index('melava_pending_campaign_id_index');
                    $table->string('melava_number', 20)->nullable();
                    $table->longText('registration_data');
                    $table->string('status', 20)->default('pending');
                    $table->timestamp('expires_at')->nullable();
                    $table->timestamps();
                    $table->index('mobile', 'melava_pending_mobile_index');
                    $table->index('expires_at', 'melava_pending_expires_at_index');
                    $table->index(['mobile', 'status', 'id'], 'melava_pending_mobile_status_id_index');
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('melava_pending_registrations');
    }
};
