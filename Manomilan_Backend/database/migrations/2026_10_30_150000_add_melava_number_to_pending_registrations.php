<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('melava_pending_registrations', 'melava_number')) {
            DB::statement('ALTER TABLE melava_pending_registrations ADD COLUMN melava_number VARCHAR(20) NULL AFTER melava_campaign_id');
        }

        DB::statement(
            'UPDATE melava_pending_registrations p
             JOIN melava_campaigns c ON c.id = p.melava_campaign_id
             SET p.melava_number = UPPER(c.melava_code)
             WHERE (p.melava_number IS NULL OR p.melava_number = "")'
        );
        DB::statement(
            'UPDATE melava_registrations r
             JOIN melava_campaigns c ON c.id = r.melava_campaign_id
             SET r.melava_number = UPPER(c.melava_code)
             WHERE r.melava_number IS NULL OR r.melava_number = ""'
        );

        if (! Schema::hasIndex('melava_pending_registrations', 'melava_pending_registrations_melava_number_index')) {
            DB::statement('CREATE INDEX melava_pending_registrations_melava_number_index ON melava_pending_registrations (melava_number)');
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('melava_pending_registrations', 'melava_pending_registrations_melava_number_index')) {
            DB::statement('DROP INDEX melava_pending_registrations_melava_number_index ON melava_pending_registrations');
        }

        if (Schema::hasColumn('melava_pending_registrations', 'melava_number')) {
            DB::statement('ALTER TABLE melava_pending_registrations DROP COLUMN melava_number');
        }
    }
};
