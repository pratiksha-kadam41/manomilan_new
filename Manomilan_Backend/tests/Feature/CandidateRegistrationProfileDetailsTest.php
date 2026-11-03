<?php

namespace Tests\Feature;

use App\Http\Controllers\Candidate\ProfileController;
use App\Models\User;
use App\Services\CandidateProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CandidateRegistrationProfileDetailsTest extends TestCase
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

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_registration_saves_profile_details_for_the_authenticated_candidate(): void
    {
        DB::table('roles')->insert([
            'name' => 'Candidate',
            'user_type' => '21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $registration = $this->postJson('/api/register', [
            'name' => 'Candidate Example',
            'email' => 'candidate@example.test',
            'phone' => '9876543210',
            'password' => 'secret123',
            'birth_date' => '1995-06-15',
            'gender' => 'bride',
        ])->assertCreated();

        $user = User::findOrFail($registration->json('user.id'));
        $this->assertSame('1995-06-15', $user->birth_date);
        $this->assertSame('bride', $user->gender);
        $this->assertNull($user->profile_id);

        $request = Request::create('/api/candidate/profile', 'GET');
        $request->setUserResolver(fn () => $user);
        $profileResponse = (new ProfileController())->show($request);

        $this->assertSame(200, $profileResponse->getStatusCode());
        $this->assertSame('1995-06-15', $profileResponse->getData(true)['data']['user']['birth_date']);
        $this->assertSame('bride', $profileResponse->getData(true)['data']['user']['gender']);
        $this->assertNull($profileResponse->getData(true)['data']['profile']);
    }

    public function test_candidate_can_create_a_profile_with_the_current_schema(): void
    {
        DB::table('roles')->insert([
            'name' => 'Candidate',
            'user_type' => '21',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $registration = $this->postJson('/api/register', [
            'name' => 'Candidate Example',
            'email' => 'candidate@example.test',
            'phone' => '9876543210',
            'password' => 'secret123',
            'birth_date' => '1995-06-15',
            'gender' => 'bride',
        ])->assertCreated();

        $user = User::findOrFail($registration->json('user.id'));
        $request = Request::create('/api/candidate/profile', 'POST', [
            'full_name' => 'Candidate Example',
            'gender' => 'bride',
            'birth_date' => '1995-06-15',
            'email' => 'candidate@example.test',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = (new ProfileController())->store($request, new CandidateProfileService());
        $body = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Candidate Example', $body['data']['profile']['full_name']);
        $this->assertNotEmpty($body['data']['profile']['profile_id']);
        $this->assertNotNull($user->fresh()->profile_id);
    }
}
