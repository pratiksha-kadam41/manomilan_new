<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ProfileController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormalizedProfileListingTest extends TestCase
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

    public function test_admin_profile_listing_loads_normalized_related_tables(): void
    {
        $profileId = DB::table('profiles')->insertGetId([
            'reg_id' => 'REG-100',
            'groomorbride' => 'Bride',
            'full_name' => 'Normalized Candidate',
            'phone' => '9999999999',
            'birth_date' => '1995-05-10',
            'verification_status' => 'Pending',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('profile_family_details')->insert([
            'profile_id' => $profileId,
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('profile_education')->insert([
            'profile_id' => $profileId,
            'degree_name' => 'BSc',
            'education_city' => 'Pune',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('profile_employment')->insert([
            'profile_id' => $profileId,
            'profession' => 'Engineer',
            'employment_city' => 'Mumbai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('profile_religion_details')->insert([
            'profile_id' => $profileId,
            'religion' => 'Jain',
            'caste' => 'Shwetambar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('profile_documents')->insert([
            'profile_id' => $profileId,
            'birth_certificate' => 'documents/birth.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('profile_partner_preferences')->insert([
            'profile_id' => $profileId,
            'preferred_location' => 'Pune',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = new User(['user_type' => '00']);
        $request = Request::create('/api/profiles/allProfiles', 'GET');
        $request->setUserResolver(fn () => $admin);
        $response = (new ProfileController())->allProfiles($request);
        $result = $response->getData(true);
        $profile = $result['data'][0];

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Normalized Candidate', $profile['full_name']);
        $this->assertSame('REG-100', $profile['registration_number']);
        $this->assertSame('Mumbai', $profile['city']);
        $this->assertSame('BSc', $profile['education']);
        $this->assertSame('Engineer', $profile['profession']);
        $this->assertSame('Father', $profile['family_details']['father_name']);
        $this->assertSame('Jain', $profile['religion_details']['religion']);
        $this->assertSame('documents/birth.pdf', $profile['documents']['birth_certificate']);
        $this->assertSame('Pune', $profile['partner_preferences']['preferred_location']);
        $this->assertSame(1, $result['stats']['total']);

        $export = (new ProfileController())->export($request);
        $this->assertSame(200, $export->getStatusCode());
        ob_start();
        $export->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('Normalized Candidate', $csv);
        $this->assertStringContainsString('Engineer', $csv);
    }
}
