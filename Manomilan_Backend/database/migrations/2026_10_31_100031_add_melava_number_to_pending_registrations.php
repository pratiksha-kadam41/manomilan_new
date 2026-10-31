<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('melava_pending_registrations')
            || Schema::hasColumn('melava_pending_registrations', 'melava_number')) {
            return;
        }

        Schema::table('melava_pending_registrations', function (Blueprint $table) {
            $table->string('melava_number', 20)->nullable()->after('melava_campaign_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('melava_pending_registrations')
            || ! Schema::hasColumn('melava_pending_registrations', 'melava_number')) {
            return;
        }

        Schema::table('melava_pending_registrations', function (Blueprint $table) {
            $table->dropColumn('melava_number');
        });
    }
};
