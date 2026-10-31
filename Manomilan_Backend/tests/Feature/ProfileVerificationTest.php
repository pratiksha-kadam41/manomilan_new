<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ProfileController;
use App\Models\User;
use App\Profile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('profileVerificationStatus')->default('Pending');
            $table->timestamps();
        });

        Schema::create('document_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->unique();

            foreach ([
                'identificationProof',
                'birthDateProof',
                'addressProof',
                'educationProof',
                'professionProof',
                'incomeProof',
                'casteCertificate',
                'medicalFitnessCertificate',
                'selfieVerification',
            ] as $key) {
                $table->string($key.'_status')->default('pending');
                $table->text($key.'_remarks')->nullable();
            }

            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_saving_verified_required_documents_completes_the_profile(): void
    {
        $profileId = DB::table('profiles')->insertGetId([
            'profileVerificationStatus' => 'Pending',
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
            Profile::findOrFail($profileId)->profileVerificationStatus,
        );
    }
}
