<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AddQueryPerformanceIndexesMigrationTest extends TestCase
{
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
            $table->string('registration_number')->nullable();
            $table->string('active')->nullable();
            $table->string('groomOrBride')->nullable();
            $table->date('birthDate')->nullable();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->index('username', 'users_existing_username_index');
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('failed_at');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_migration_adds_useful_indexes_without_duplicating_existing_ones(): void
    {
        $migration = require database_path(
            'migrations/2026_10_31_100037_add_query_performance_indexes.php'
        );

        $migration->up();

        $this->assertTrue(Schema::hasIndex('profiles', 'profiles_registration_number_index'));
        $this->assertTrue(Schema::hasIndex('profiles', 'profiles_active_gender_birthdate_index'));
        $this->assertTrue(Schema::hasIndex('users', 'users_existing_username_index'));
        $this->assertFalse(Schema::hasIndex('users', 'users_username_index'));
        $this->assertTrue(Schema::hasIndex('users', 'users_profile_id_index'));
        $this->assertTrue(Schema::hasIndex('failed_jobs', 'failed_jobs_failed_at_index'));
        $this->assertFalse(Schema::hasTable('candidates'));
        $this->assertFalse(Schema::hasTable('candidate_requests'));

        $migration->down();

        $this->assertFalse(Schema::hasIndex('profiles', 'profiles_registration_number_index'));
        $this->assertTrue(Schema::hasIndex('users', 'users_existing_username_index'));
        $this->assertFalse(Schema::hasIndex('users', 'users_profile_id_index'));
        $this->assertFalse(Schema::hasIndex('failed_jobs', 'failed_jobs_failed_at_index'));
    }
}
