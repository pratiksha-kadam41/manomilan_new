<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'melava_registrations_username_index';

    public function up(): void
    {
        if (Schema::hasIndex('melava_registrations', self::INDEX_NAME)) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'CREATE INDEX '.self::INDEX_NAME.' ON melava_registrations (username(20))'
            );
            return;
        }

        Schema::table('melava_registrations', function ($table) {
            $table->index('username', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('melava_registrations', self::INDEX_NAME)) {
            Schema::table('melava_registrations', function ($table) {
                $table->dropIndex(self::INDEX_NAME);
            });
        }
    }
};
