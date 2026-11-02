<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MelavaRegistrationNormalizedPersistenceTest extends TestCase
{
    private string $testPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');

        $this->testPublicPath = storage_path('framework/testing/melava-'.uniqid('', true));
        File::ensureDirectoryExists($this->testPublicPath);
        app()->usePublicPath($this->testPublicPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testPublicPath);
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_public_melava_registration_saves_data_to_normalized_profile_tables(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Candidate',
            'user_type' => '21',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $campaignId = DB::table('melava_campaigns')->insertGetId([
            'melava_code' => 'TEST',
            'title' => 'Test Melava',
            'currency' => 'INR',
            'registration_fee' => 100,
            'booklet_available' => true,
            'booklet_fee' => 20,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/api/melava-registrations', [
            'melava_campaign_id' => (string) $campaignId,
            'username' => '9876543210',
            'email' => 'candidate@example.test',
            'fullName' => 'Test Candidate',
            'groomOrBride' => 'groom',
            'birthDate' => '1995-05-10',
            'maritalStatus' => 'Unmarried',
            'religion' => 'Jain',
            'caste' => 'Digambar',
            'subCaste' => 'Saitwal',
            'height' => '5ft 8in',
            'weight' => '70',
            'havingSpecsLenses' => 'Yes',
            'bloodGroup' => 'B+',
            'motherTongue' => 'Hindi',
            'language' => 'English, Marathi',
            'specialCases' => 'None',
            'knowAboutMyself' => 'Test profile details',
            'source_of_information' => 'Family/Friends',
            'address' => '10 Test Street',
            'current_address_pincode' => '411001',
            'current_address_city' => 'Pune',
            'current_address_district' => 'Pune',
            'current_address_state' => 'Maharashtra',
            'current_address_country' => 'India',
            'permanent_address' => '20 Permanent Street',
            'timeOfBirth' => '10:30',
            'placeOfBirth' => 'Pune',
            'education' => 'Graduation',
            'degreeDiplomaName1' => 'BSc',
            'degreeDiplomaName2' => 'MSc',
            'specialisation1' => 'Physics',
            'specialisation2' => 'Astronomy',
            'universityBoardCollege' => 'Test University',
            'additionalEducation1' => 'Research',
            'additionalEducation2' => 'Certification',
            'cityOfEducation' => 'Pune',
            'profession' => 'Engineer',
            'fieldOfWorking' => 'Technology',
            'organisationWorkingIn' => 'Test Company',
            'professionalCity' => 'Mumbai',
            'designation' => 'Senior Engineer',
            'lengthOfEmployment' => '5 years',
            'monthlyIncome' => '50000',
            'annualIncome' => '600000',
            'income_currency' => 'INR',
            'personalMedicalHistory' => 'None',
            'currentMedicalProblems' => '',
            'medications' => '',
            'fathersName' => 'Father Candidate',
            'fatherAlive' => 'Yes',
            'fatherContactNumber' => '9876543211',
            'fathersOccupation' => 'Business',
            'fathersNativePlace' => 'Pune',
            'mothersName' => 'Mother Candidate',
            'motherAlive' => 'Yes',
            'motherContactNumber' => '9876543212',
            'mothersOccupation' => 'Teacher',
            'mothersNativePlace' => 'Mumbai',
            'noOfBrothers' => '1',
            'brothersMarried' => '0',
            'noOfSisters' => '1',
            'sistersMarried' => '0',
            'parentsIntercasteMarriage' => 'No',
            'parentsDifferentCaste' => '',
            'familyValues' => 'Traditional',
            'familyFinancialStatus' => 'Middle',
            'familyAnnualIncome' => '1200000',
            'profileCreatorName' => 'Creator Candidate',
            'profileCreatorRelation' => 'Self',
            'referenceName' => 'Reference Candidate',
            'referenceRelation' => 'Friend',
            'referenceAddress' => 'Pune',
            'referenceContactNumber' => '9876543213',
            'referenceEmail' => 'reference@example.test',
            'preferred_caste' => 'Digambar',
            'preferred_sub_caste' => 'Saitwal',
            'age_from' => '25',
            'age_to' => '35',
            'height_from' => '5ft 2in',
            'height_to' => '6ft',
            'preferred_education' => 'Graduate',
            'working_partner' => 'Any',
            'preferred_working_field' => 'Technology',
            'expected_income' => '50000',
            'family_type' => 'Nuclear',
            'financial_background' => 'Middle',
            'preferred_country' => 'India',
            'preferred_state' => 'Maharashtra',
            'preferred_city' => 'Pune',
            'other_expectations' => 'Kind and considerate',
            'booklet_required' => '1',
            'privacyPolicyCheck' => '1',
            'termsCheck' => '1',
            'profilePhoto' => UploadedFile::fake()->image('profile.jpg'),
            'full_body_photo' => UploadedFile::fake()->image('full.jpg'),
            'candidate_photos' => [UploadedFile::fake()->image('candidate.jpg')],
            'additionalPhotos' => [UploadedFile::fake()->image('additional.jpg')],
            'identificationProof' => UploadedFile::fake()->image('identity.jpg'),
            'birthDateProof' => UploadedFile::fake()->image('birth.jpg'),
            'educationProof' => UploadedFile::fake()->image('education.jpg'),
            'payment_screenshot' => UploadedFile::fake()->image('payment.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('success', true);
        $profileId = $response->json('data.profile_id');
        $profile = DB::table('profiles')->where('id', $profileId)->first();
        $this->assertNotNull($profile);
        $this->assertSame('Groom', $profile->groomorbride);
        $this->assertSame('Test Candidate', $profile->full_name);
        $this->assertEquals(172.72, (float) $profile->height_cm);
        $this->assertSame(
            '411001',
            json_decode($profile->addresses, true)['current']['pincode']
        );
        $this->assertSame(
            ['address' => '20 Permanent Street'],
            json_decode($profile->addresses, true)['permanent']
        );
        $photos = json_decode($profile->photos, true);
        $this->assertNotEmpty($photos['profile_photo']);
        $this->assertCount(1, $photos['candidate_photos']);
        $this->assertCount(1, $photos['additional_photos']);

        $this->assertDatabaseHas('profile_family_details', [
            'profile_id' => $profileId,
            'father_status' => 'Alive',
            'brothers_count' => 1,
        ]);
        $this->assertDatabaseCount('profile_education', 2);
        $this->assertDatabaseHas('profile_education', [
            'profile_id' => $profileId,
            'degree_name' => 'MSc',
            'specialization' => 'Astronomy',
        ]);
        $this->assertDatabaseHas('profile_employment', [
            'profile_id' => $profileId,
            'profession' => 'Engineer',
            'monthly_income' => 50000,
        ]);
        $this->assertDatabaseHas('profile_religion_details', [
            'profile_id' => $profileId,
            'religion' => 'Jain',
            'sub_caste' => 'Saitwal',
        ]);
        $this->assertNotEmpty(DB::table('profile_documents')->where('profile_id', $profileId)->value('aadhaar_card'));
        $this->assertDatabaseHas('profile_partner_preferences', [
            'profile_id' => $profileId,
            'preferred_caste' => 'Digambar / Saitwal',
            'preferred_location' => 'Pune, Maharashtra, India',
        ]);
        $this->assertDatabaseHas('profile_melava_registrations', [
            'profile_id' => $profileId,
            'melava_campaign_id' => $campaignId,
            'is_booklet_req' => true,
            'payment_verified' => false,
        ]);
        $this->assertSame($roleId, (int) DB::table('users')->where('profile_id', $profileId)->value('role_id'));

        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'Administrator',
            'user_type' => '00',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = \App\Models\User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'username' => 'admin',
            'password' => 'password',
            'role_id' => $adminRoleId,
            'user_type' => '00',
            'status' => 'active',
        ]);

        $updateRequest = Request::create(
            '/api/melava-registrations/'.$response->json('data.registration_id'),
            'PUT',
            [
                'fullName' => 'Updated Candidate',
                'groomOrBride' => 'groom',
                'birthDate' => '1995-05-10',
                'maritalStatus' => 'Unmarried',
                'religion' => 'Jain',
                'caste' => 'Digambar',
                'height' => '5ft 8in',
                'source_of_information' => 'Family/Friends',
                'address' => 'Updated Current Street',
                'current_address_pincode' => '411002',
                'current_address_city' => 'Pune',
                'education' => 'Graduation',
                'degreeDiplomaName1' => 'BSc',
                'profession' => 'Engineer',
                'professionalCity' => 'Pune',
                'booklet_required' => true,
            ]
        );
        $updateRequest->setUserResolver(fn () => $admin);
        $updateResponse = app(\App\Http\Controllers\Api\MelavaRegistrationController::class)
            ->update($updateRequest, (int) $response->json('data.registration_id'));
        $this->assertSame(200, $updateResponse->getStatusCode());
        $this->assertTrue($updateResponse->getData()->success);

        $this->assertDatabaseHas('profiles', [
            'id' => $profileId,
            'full_name' => 'Updated Candidate',
        ]);
        $updatedAddresses = json_decode(
            DB::table('profiles')->where('id', $profileId)->value('addresses'),
            true
        );
        $this->assertSame('411002', $updatedAddresses['current']['pincode']);
        $this->assertSame('Updated Current Street', $updatedAddresses['current']['address']);
    }
}
