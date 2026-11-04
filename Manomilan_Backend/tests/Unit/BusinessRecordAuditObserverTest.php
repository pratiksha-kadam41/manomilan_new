<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\User;
use App\MelavaCampaign;
use App\WorkshopEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Api\MelavaCampaignController;
use App\Http\Middleware\ExposeRecordEditorNames;
use Tests\TestCase;

class BusinessRecordAuditObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->nullable();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->boolean('is_password_changed')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('workshop_events', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('melava_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('melava_code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('currency')->default('INR');
            $table->decimal('registration_fee', 10, 2)->default(0);
            $table->boolean('booklet_available')->default(false);
            $table->decimal('booklet_fee', 10, 2)->default(0);
            $table->string('qr_image_path')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name_snapshot')->nullable();
            $table->string('action');
            $table->unsignedBigInteger('module_id')->nullable();
            $table->string('module_name_snapshot')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->string('candidate_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_method')->nullable();
            $table->text('request_url')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Auth::logout();
        parent::tearDown();
    }

    public function test_it_stamps_actor_ids_and_logs_record_changes(): void
    {
        $actor = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'username' => 'admin',
            'password' => 'password',
        ]);
        Auth::login($actor);

        $event = WorkshopEvent::create(['title' => 'First title']);
        $event->update(['title' => 'Updated title']);

        $this->assertSame($actor->id, $event->created_by_user_id);
        $this->assertSame($actor->id, $event->updated_by_user_id);
        $this->assertSame(2, ActivityLog::query()->where('entity_type', 'WorkshopEvent')->count());

        $updateLog = ActivityLog::query()->where('action', 'record.updated')->firstOrFail();
        $this->assertSame('First title', $updateLog->old_values['title']);
        $this->assertSame('Updated title', $updateLog->new_values['title']);
        $this->assertSame($actor->id, $updateLog->user_id);
    }

    public function test_actor_migration_leaves_existing_records_unattributed(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['created_by_user_id', 'updated_by_user_id']));
        Schema::table('workshop_events', fn (Blueprint $table) => $table->dropColumn(['created_by_user_id', 'updated_by_user_id']));
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
        });
        DB::table('profiles')->insert(['created_by' => 17]);

        $migration = require database_path('migrations/2026_11_04_120000_add_actor_ids_to_business_records.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('profiles', 'created_by_user_id'));
        $this->assertTrue(Schema::hasColumn('profiles', 'updated_by_user_id'));
        $this->assertNull(DB::table('profiles')->value('created_by_user_id'));
        $this->assertSame(17, DB::table('profiles')->value('created_by'));

        $migration->down();
        $this->assertFalse(Schema::hasColumn('profiles', 'created_by_user_id'));
    }

    public function test_melava_campaign_status_updates_are_audited(): void
    {
        $actor = User::create([
            'name' => 'Admin',
            'email' => 'campaign-admin@example.test',
            'username' => 'campaign-admin',
            'password' => 'password',
        ]);
        Auth::login($actor);

        $campaign = MelavaCampaign::create([
            'melava_code' => 'MM-01',
            'title' => 'Test Melava',
        ]);
        $request = Request::create('/api/admin/melava-campaigns/1/status', 'PATCH', ['status' => 'inactive']);
        $request->setUserResolver(fn () => $actor);

        $response = (new MelavaCampaignController)->updateStatus($request, (int) $campaign->id);

        $this->assertSame('inactive', $response->getData(true)['data']['status']);
        $this->assertSame($actor->id, $campaign->fresh()->updated_by_user_id);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'record.updated',
            'entity_type' => 'MelavaCampaign',
            'user_id' => $actor->id,
        ]);
    }

    public function test_editor_names_are_exposed_to_admin_roles_but_not_candidates(): void
    {
        $editor = User::create([
            'name' => 'Record Editor',
            'email' => 'editor@example.test',
            'username' => 'editor',
            'password' => 'password',
        ]);
        $admin = User::create([
            'name' => 'Custom Admin',
            'email' => 'custom-admin@example.test',
            'username' => 'custom-admin',
            'password' => 'password',
            'user_type' => '31',
        ]);
        $candidate = User::create([
            'name' => 'Candidate',
            'email' => 'candidate@example.test',
            'username' => 'candidate',
            'password' => 'password',
            'user_type' => '21',
        ]);
        $middleware = new ExposeRecordEditorNames;
        $payload = ['data' => [[
            'id' => 7,
            'updated_by_user_id' => $editor->id,
            'updated_by_user_name' => 'Must not leak',
        ]]];

        $adminRequest = Request::create('/api/profiles');
        $adminRequest->setUserResolver(fn () => $admin);
        $adminResponse = $middleware->handle(
            $adminRequest,
            fn () => response()->json($payload)
        );

        $candidateRequest = Request::create('/api/profiles');
        $candidateRequest->setUserResolver(fn () => $candidate);
        $candidateResponse = $middleware->handle(
            $candidateRequest,
            fn () => response()->json($payload)
        );

        $this->assertSame(
            'Record Editor',
            $adminResponse->getData(true)['data'][0]['updated_by_user_name']
        );
        $this->assertArrayNotHasKey(
            'updated_by_user_name',
            $candidateResponse->getData(true)['data'][0]
        );
    }
}
