<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ProfileController;
use App\Models\User;
use App\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileVerificationTest extends TestCase
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
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_saving_verified_required_documents_completes_the_profile(): void
    {
        $profileId = DB::table('profiles')->insertGetId([
            'groomorbride' => 'Groom',
            'full_name' => 'Test Profile',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('profile_documents')->insert([
            'profile_id' => $profileId,
            'aadhaar_card' => 'uploads/identity.jpg',
            'birth_certificate' => 'uploads/birth.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('payment_screenshots')->insert([
            'profile_id' => $profileId,
            'screenshot_path' => 'uploads/payment.jpg',
            'payment_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $statuses = [
            'identificationProof_status' => 'verified',
            'birthDateProof_status' => 'verified',
            'addressProof_status' => 'verified',
            'educationProof_status' => 'verified',
            'professionProof_status' => 'verified',
            'incomeProof_status' => 'verified',
            'casteCertificate_status' => 'verified',
            'medicalFitnessCertificate_status' => 'verified',
            'selfieVerification_status' => 'verified',
        ];
        $request = Request::create('/api/profiles/'.$profileId.'/verify', 'POST', $statuses);
        $admin = new User(['user_type' => '00']);
        $request->setUserResolver(fn () => $admin);

        $response = Profile::withoutEvents(
            fn () => (new ProfileController())->verify($request, $profileId),
        );

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertSame(
            'Completed',
            Profile::findOrFail($profileId)->verification_status,
        );
    }
}
