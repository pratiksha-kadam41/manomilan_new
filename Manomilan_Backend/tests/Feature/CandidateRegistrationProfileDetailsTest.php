<?php

namespace Tests\Feature;

use App\Http\Controllers\Candidate\ProfileController;
use App\Models\User;
use App\Profile;
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

    public function test_candidate_education_rows_can_be_added_removed_and_reloaded(): void
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
        $createRequest = Request::create('/api/candidate/profile', 'POST', [
            'full_name' => 'Candidate Example',
            'gender' => 'bride',
            'birth_date' => '1995-06-15',
            'email' => 'candidate@example.test',
        ]);
        $createRequest->setUserResolver(fn () => $user);
        $created = (new ProfileController())->store($createRequest, new CandidateProfileService());
        $profile = Profile::findOrFail($created->getData(true)['data']['profile']['id']);
        $service = new CandidateProfileService();

        $service->update($profile, [
            'education' => 'Graduate',
            'degree' => 'Bachelor degree',
            'degree_2' => 'Master degree',
            'specialisations' => ['Physics', 'Data Science', 'Statistics'],
            'additional_qualifications' => ['Course A', 'Course B', 'Course C'],
        ]);

        $service->update($profile->fresh(), [
            'education' => 'Graduate',
            'degree' => 'Bachelor degree',
            'degree_2' => 'Master degree',
            'specialisations' => ['Data Science'],
            'additional_qualifications' => ['Course B'],
        ]);

        $showRequest = Request::create('/api/candidate/profile', 'GET');
        $showRequest->setUserResolver(fn () => $user->fresh());
        $profileResponse = (new ProfileController())->show($showRequest);
        $details = $profileResponse->getData(true)['data']['profile']['details'];

        $this->assertSame(['Data Science'], $details['specialisations']);
        $this->assertSame(['Course B'], $details['additional_qualifications']);
        $this->assertSame(3, Profile::findOrFail($profile->id)->educations()->count());
    }
}
