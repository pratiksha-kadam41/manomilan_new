<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\HomeController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminDashboardNormalizedProfileTest extends TestCase
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

    public function test_admin_dashboard_reads_profiles_from_the_normalized_schema(): void
    {
        $profileId = DB::table('profiles')->insertGetId([
            'reg_id' => 'REG-001',
            'groomorbride' => 'Groom',
            'full_name' => 'Test Candidate',
            'verification_status' => 'Completed',
            'status' => 'active',
            'addresses' => json_encode(['city' => 'Pune']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('profile_education')->insert([
            'profile_id' => $profileId,
            'degree_name' => 'BSc',
            'education_city' => 'Mumbai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('profile_employment')->insert([
            'profile_id' => $profileId,
            'profession' => 'Engineer',
            'employment_city' => 'Nashik',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('melava_campaigns')->insert([
            'melava_code' => 'TEST-001',
            'title' => 'Test event',
            'start_date' => now()->addWeek()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/dashboard', 'GET', ['period' => 'all']);
        $request->setUserResolver(fn () => new User(['user_type' => '00']));
        $response = (new HomeController())->dashboard($request);
        $result = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['data']['total_profiles']);
        $this->assertSame(1, $result['data']['verified_profiles']);
        $this->assertSame(1, $result['data']['groom_profiles']);
        $this->assertSame('Test Candidate', $result['data']['recent_profiles'][0]['name']);
        $this->assertSame('Nashik', $result['data']['recent_profiles'][0]['location']);
        $this->assertSame('REG-001', $result['data']['recent_profiles'][0]['registration_number']);

        $this->assertTrue(Schema::hasTable('melava_campaigns'));
    }
}
