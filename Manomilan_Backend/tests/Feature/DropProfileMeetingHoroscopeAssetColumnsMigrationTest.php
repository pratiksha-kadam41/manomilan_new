<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropProfileMeetingHoroscopeAssetColumnsMigrationTest extends TestCase
{
    private const COLUMNS = [
        'branch',
        'location',
        'meeting_date',
        'day',
        'meeting_time',
        'remarks',
        'spoke_to',
        'rashi',
        'nakshatra',
        'charan',
        'gan',
        'gotra',
        'nadi',
        'matchPatrika',
        'kundali',
        'homeStatus',
        'ownedRealEstate',
        'hasVehicle',
        'vehicleType',
        'otherInvestments',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');

        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('fullName')->nullable();
            foreach (self::COLUMNS as $column) {
                $table->text($column)->nullable();
            }
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_migration_drops_only_the_selected_columns_and_can_restore_them(): void
    {
        $migration = require database_path(
            'migrations/2026_10_31_100035_drop_unused_profile_meeting_horoscope_asset_columns.php'
        );

        $migration->up();

        foreach (self::COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('profiles', $column), $column);
        }
        $this->assertTrue(Schema::hasColumn('profiles', 'fullName'));

        $migration->down();

        foreach (self::COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('profiles', $column), $column);
        }
        $this->assertTrue(Schema::hasColumn('profiles', 'fullName'));
    }
}
