<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('melava_campaigns', 'melava_code')) {
            DB::statement('ALTER TABLE melava_campaigns ADD COLUMN melava_code VARCHAR(20) NULL');
        }

        if (! Schema::hasColumn('melava_registrations', 'melava_number')) {
            DB::statement('ALTER TABLE melava_registrations ADD COLUMN melava_number VARCHAR(20) NULL');
        }

        $campaigns = DB::table('melava_campaigns')->orderBy('id')->get();
        foreach ($campaigns as $index => $campaign) {
            if (trim((string) ($campaign->melava_code ?? '')) !== '') {
                continue;
            }

            $code = 'MM-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            DB::table('melava_campaigns')->where('id', $campaign->id)->update(['melava_code' => $code]);
            DB::table('melava_registrations')
                ->where('melava_campaign_id', $campaign->id)
                ->update(['melava_number' => $code]);
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            $duplicateCodes = DB::selectOne(
                'SELECT COUNT(*) AS duplicate_count FROM (
                    SELECT melava_code FROM melava_campaigns
                    WHERE melava_code IS NOT NULL AND melava_code <> ""
                    GROUP BY melava_code HAVING COUNT(*) > 1
                ) duplicates'
            );
            if ((int) $duplicateCodes->duplicate_count > 0) {
                throw new RuntimeException('Duplicate Melava codes exist. Please resolve them before running this migration.');
            }

            DB::statement('ALTER TABLE melava_campaigns MODIFY melava_code VARCHAR(20) NOT NULL');
            DB::statement('CREATE UNIQUE INDEX melava_campaigns_melava_code_unique ON melava_campaigns (melava_code)');
        }

        foreach ($campaigns as $campaign) {
            if ($campaign->melava_code) {
                DB::table('melava_registrations')
                    ->where('melava_campaign_id', $campaign->id)
                    ->whereNull('melava_number')
                    ->update(['melava_number' => $campaign->melava_code]);
            }
        }

        if (! Schema::hasIndex('melava_registrations', 'melava_registrations_melava_number_index')) {
            DB::statement('CREATE INDEX melava_registrations_melava_number_index ON melava_registrations (melava_number)');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE melava_campaigns DROP INDEX melava_campaigns_melava_code_unique');
        DB::statement('ALTER TABLE melava_registrations DROP INDEX melava_registrations_melava_number_index');
        DB::statement('ALTER TABLE melava_campaigns DROP COLUMN melava_code');
        DB::statement('ALTER TABLE melava_registrations DROP COLUMN melava_number');
    }
};
