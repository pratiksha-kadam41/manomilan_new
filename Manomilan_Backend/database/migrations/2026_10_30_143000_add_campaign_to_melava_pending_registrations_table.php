<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('melava_pending_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('melava_campaign_id')->nullable()->after('profile_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('melava_pending_registrations', function (Blueprint $table) {
            $table->dropIndex(['melava_campaign_id']);
            $table->dropColumn('melava_campaign_id');
        });
    }
};
