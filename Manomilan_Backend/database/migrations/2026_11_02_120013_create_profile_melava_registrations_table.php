<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_melava_registrations', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('profile_id');
                    $table->unsignedBigInteger('melava_campaign_id');
                    $table->boolean('is_booklet_req')->default(false);
                    $table->string('payment_screenshot', 500)->nullable();
                    $table->boolean('payment_verified')->default(false);
                    $table->timestamp('created_at')->nullable()->useCurrent();
                    $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
                    $table->index('profile_id', 'idx_melava_profile');
                    $table->index('melava_campaign_id', 'idx_melava_campaign');
                    $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
                    $table->foreign('melava_campaign_id')->references('id')->on('melava_campaigns')->cascadeOnDelete();
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_melava_registrations');
    }
};
