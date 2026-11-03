<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_screenshots', function (Blueprint $table): void {
            $table->json('payment_details')->nullable();
        });

        Schema::table('profile_melava_registrations', function (Blueprint $table): void {
            $table->string('booklet_payment_screenshot', 500)->nullable();
            $table->boolean('booklet_payment_verified')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('profile_melava_registrations', function (Blueprint $table): void {
            $table->dropColumn(['booklet_payment_screenshot', 'booklet_payment_verified']);
        });

        Schema::table('payment_screenshots', function (Blueprint $table): void {
            $table->dropColumn('payment_details');
        });
    }
};
