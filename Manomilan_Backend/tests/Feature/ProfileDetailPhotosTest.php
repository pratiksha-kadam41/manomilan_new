<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ProfileController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileDetailPhotosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'app.url' => 'http://localhost',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_profile_details_returns_all_uploaded_photo_types(): void
    {
        $profileId = DB::table('profiles')->insertGetId([
            'reg_id' => 'MM3-0001',
            'groomorbride' => 'Groom',
            'full_name' => 'Test Profile',
            'photos' => json_encode([
                'profile_photo' => 'uploads/profile_photos/profile.jpg',
                'full_body_photo' => 'uploads/full_body_photos/full.jpg',
                'half_body_photo' => 'uploads/half_body_photos/half.jpg',
                'selfie_photo' => 'uploads/selfie_photos/selfie.jpg',
                'additional_photos' => [
                    'uploads/melava_additional_photos/additional-1.jpg',
                    'uploads/melava_additional_photos/additional-2.jpg',
                ],
                'candidate_photos' => ['uploads/melava_candidate_photos/candidate.jpg'],
            ]),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $request = Request::create('/api/profiles/'.$profileId);
        $request->setUserResolver(fn () => new User(['user_type' => '00']));

        $response = (new ProfileController())->show($request, $profileId);
        $photos = $response->getData(true)['data']['photos'];

        $this->assertCount(7, $photos);
        $this->assertSame(
            [
                'Profile Photo',
                'Full Body Photo',
                'Half Body Photo',
                'Selfie Photo',
                'Additional Photo 1',
                'Additional Photo 2',
                'Candidate Photo 1',
            ],
            array_column($photos, 'label'),
        );
        $this->assertStringContainsString('melava_candidate_photos/candidate.jpg', $photos[6]['url']);
    }
}
