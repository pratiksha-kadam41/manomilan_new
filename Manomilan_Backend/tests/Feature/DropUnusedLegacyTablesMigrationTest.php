<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropUnusedLegacyTablesMigrationTest extends TestCase
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

        $candidatesMigration = require database_path(
            'migrations/2026_10_31_100008_create_candidates_table.php'
        );
        $candidateRequestsMigration = require database_path(
            'migrations/2026_10_31_100009_create_candidate_requests_table.php'
        );

        $candidatesMigration->up();
        $candidateRequestsMigration->up();

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->text('name');
            $table->string('token', 64);
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique('token', 'personal_access_tokens_token_unique');
            $table->index(
                ['tokenable_type', 'tokenable_id'],
                'personal_access_tokens_tokenable_type_tokenable_id_index'
            );
            $table->index('expires_at', 'personal_access_tokens_expires_at_index');
        });

        Schema::create('password_resets', function (Blueprint $table): void {
            $table->string('email');
            $table->string('token');
            $table->timestamp('created_at')->nullable();
            $table->index('email', 'password_resets_email_index');
        });

        $failedJobsMigration = require database_path(
            'migrations/2026_10_31_100005_create_failed_jobs_table.php'
        );
        $failedJobsMigration->up();
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_migration_drops_unused_tables_and_can_recreate_them(): void
    {
        $migration = require database_path(
            'migrations/2026_10_31_100036_drop_unused_legacy_tables.php'
        );

        $migration->up();

        $this->assertFalse(Schema::hasTable('candidates'));
        $this->assertFalse(Schema::hasTable('candidate_requests'));
        $this->assertFalse(Schema::hasTable('personal_access_tokens'));
        $this->assertFalse(Schema::hasTable('password_resets'));

        $migration->down();

        $this->assertTrue(Schema::hasTable('candidates'));
        $this->assertTrue(Schema::hasTable('candidate_requests'));
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
        $this->assertTrue(Schema::hasTable('password_resets'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertTrue(Schema::hasColumn('candidates', 'name'));
        $this->assertTrue(Schema::hasColumn('candidate_requests', 'marital_status'));
        $this->assertTrue(Schema::hasColumn('personal_access_tokens', 'token'));
        $this->assertTrue(Schema::hasColumn('password_resets', 'token'));
    }
}
