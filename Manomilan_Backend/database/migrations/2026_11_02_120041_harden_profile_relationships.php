<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', static function (Blueprint $table): void {
            $table->unique('reg_id', 'profiles_reg_id_unique');
            $table->unique('phone', 'profiles_phone_unique');
        });

        Schema::table('profile_melava_registrations', static function (Blueprint $table): void {
            $table->unique(
                ['profile_id', 'melava_campaign_id'],
                'profile_melava_registrations_profile_campaign_unique'
            );
        });

        Schema::table('melava_pending_registrations', static function (Blueprint $table): void {
            $table->foreign('profile_id', 'melava_pending_profile_id_foreign')
                ->references('id')
                ->on('profiles')
                ->nullOnDelete();
            $table->foreign('user_id', 'melava_pending_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('melava_campaign_id', 'melava_pending_campaign_id_foreign')
                ->references('id')
                ->on('melava_campaigns')
                ->nullOnDelete();
        });

        Schema::table('payments', static function (Blueprint $table): void {
            $table->foreign('user_id', 'payments_user_id_foreign')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', static function (Blueprint $table): void {
            $table->dropForeign('payments_user_id_foreign');
        });

        Schema::table('melava_pending_registrations', static function (Blueprint $table): void {
            $table->dropForeign('melava_pending_profile_id_foreign');
            $table->dropForeign('melava_pending_user_id_foreign');
            $table->dropForeign('melava_pending_campaign_id_foreign');
        });

        Schema::table('profile_melava_registrations', static function (Blueprint $table): void {
            $table->dropUnique('profile_melava_registrations_profile_campaign_unique');
        });

        Schema::table('profiles', static function (Blueprint $table): void {
            $table->dropUnique('profiles_reg_id_unique');
            $table->dropUnique('profiles_phone_unique');
        });
    }
};
