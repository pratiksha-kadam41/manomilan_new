<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('melava_campaigns', function (Blueprint $table): void {
                    $table->id();
                    $table->string('melava_code', 20)->unique();
                    $table->string('title');
                    $table->text('description')->nullable();
                    $table->date('start_date')->nullable();
                    $table->date('end_date')->nullable();
                    $table->char('currency', 3)->default('INR');
                    $table->decimal('registration_fee', 10, 2)->default(0);
                    $table->boolean('booklet_available')->default(false);
                    $table->decimal('booklet_fee', 10, 2)->default(0);
                    $table->string('qr_image_path')->nullable();
                    $table->string('status', 20)->default('active');
                    $table->timestamps();
                    $table->index(['status', 'start_date']);
                });
    }

    public function down(): void
    {
        Schema::dropIfExists('melava_campaigns');
    }
};
