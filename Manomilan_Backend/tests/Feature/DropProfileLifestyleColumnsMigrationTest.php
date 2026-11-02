<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropProfileLifestyleColumnsMigrationTest extends TestCase
{
    private const COLUMNS = [
        'cookingSkills',
        'hobbies',
        'extraCurricularActivities',
        'achievements',
        'sportsAndFitnessLikings',
        'diet',
        'smoking',
        'smokingFrequency',
        'drinking',
        'drinkingFrequency',
        'partying',
        'activeOnSocialSites',
        'instagram',
        'facebook',
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
            'migrations/2026_10_31_100034_drop_unused_profile_lifestyle_columns.php'
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
