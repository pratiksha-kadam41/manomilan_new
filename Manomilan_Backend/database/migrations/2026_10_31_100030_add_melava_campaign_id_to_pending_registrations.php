<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('melava_pending_registrations')
            || Schema::hasColumn('melava_pending_registrations', 'melava_campaign_id')) {
            return;
        }

        Schema::table('melava_pending_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('melava_campaign_id')
                ->nullable()
                ->after('profile_id');
            $table->index('melava_campaign_id', 'melava_pending_campaign_id_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('melava_pending_registrations')
            || ! Schema::hasColumn('melava_pending_registrations', 'melava_campaign_id')) {
            return;
        }

        Schema::table('melava_pending_registrations', function (Blueprint $table) {
            $table->dropIndex('melava_pending_campaign_id_index');
            $table->dropColumn('melava_campaign_id');
        });
    }
};
