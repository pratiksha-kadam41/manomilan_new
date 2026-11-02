<?php

namespace Tests\Feature;

use App\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NormalizedProfileSchemaTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_migrations_create_the_normalized_profile_tables_and_relationships(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $expectedTables = [
            'profiles',
            'profile_family_details',
            'profile_education',
            'profile_employment',
            'profile_religion_details',
            'profile_documents',
            'profile_partner_preferences',
            'profile_melava_registrations',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $this->assertTrue(Schema::hasTable('melava_campaigns'));
        $this->assertSame(
            [
                'id', 'reg_id', 'groomorbride', 'full_name', 'email', 'phone', 'birth_date',
                'height_cm', 'weight_kg', 'has_spectacles', 'blood_group', 'mother_tongue',
                'languages', 'special_cases', 'about_me', 'photos', 'monthly_income',
                'annual_income', 'personal_medical_history', 'current_medical_problems',
                'medications', 'birth_place', 'birth_time', 'created_by', 'creator_relation',
                'addresses', 'verification_status', 'status', 'created_at', 'updated_at',
            ],
            Schema::getColumnListing('profiles')
        );

        $profileId = DB::table('profiles')->insertGetId([
            'groomorbride' => 'Groom',
            'full_name' => 'Test Profile',
            'status' => 'active',
        ]);
        $profile = Profile::findOrFail($profileId);

        $profile->educations()->create(['degree_name' => 'BSc']);
        $profile->educations()->create(['degree_name' => 'MSc']);
        $profile->employments()->create(['profession' => 'Engineer']);
        $profile->employments()->create(['profession' => 'Manager']);
        $profile->familyDetails()->create(['father_name' => 'Parent']);

        $this->assertCount(2, $profile->educations()->get());
        $this->assertCount(2, $profile->employments()->get());
        $this->assertSame($profileId, $profile->familyDetails()->firstOrFail()->profile_id);

        foreach (array_slice($expectedTables, 1) as $table) {
            $foreignKeys = Schema::getForeignKeys($table);
            $profileForeignKey = collect($foreignKeys)->first(
                fn (array $foreignKey): bool => in_array('profile_id', $foreignKey['columns'], true)
                    && $foreignKey['foreign_table'] === 'profiles'
            );
            $this->assertNotNull($profileForeignKey, $table);
            $this->assertSame(['id'], $profileForeignKey['foreign_columns']);
            $this->assertSame('cascade', $profileForeignKey['on_delete']);
        }

        $campaignId = DB::table('melava_campaigns')->insertGetId([
            'melava_code' => 'TEST',
            'title' => 'Test campaign',
            'currency' => 'INR',
            'registration_fee' => 0,
            'booklet_available' => false,
            'booklet_fee' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $registration = $profile->profileMelavaRegistrations()->create([
            'melava_campaign_id' => $campaignId,
            'is_booklet_req' => true,
        ]);

        $this->assertSame($profileId, $registration->profile()->firstOrFail()->id);
        $this->assertSame($campaignId, $registration->campaign()->firstOrFail()->id);
    }
}
