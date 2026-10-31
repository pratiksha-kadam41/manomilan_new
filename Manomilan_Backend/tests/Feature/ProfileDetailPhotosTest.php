<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ProfileController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('profile_id')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('serial_no')->nullable();
            $table->string('fullName')->nullable();
            $table->string('profilePhoto')->nullable();
            $table->string('full_body_photo')->nullable();
            $table->string('half_body_photo')->nullable();
            $table->string('selfie_photo')->nullable();
            $table->json('additional_photos')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
        });

        Schema::create('preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
        });

        Schema::create('melava_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->json('candidate_photos')->nullable();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_profile_details_returns_all_uploaded_photo_types(): void
    {
        $profileId = DB::table('profiles')->insertGetId([
            'profile_id' => 'MM3-0001',
            'fullName' => 'Test Profile',
            'profilePhoto' => 'uploads/profile_photos/profile.jpg',
            'full_body_photo' => 'uploads/full_body_photos/full.jpg',
            'half_body_photo' => 'uploads/half_body_photos/half.jpg',
            'selfie_photo' => 'uploads/selfie_photos/selfie.jpg',
            'additional_photos' => json_encode([
                'uploads/melava_additional_photos/additional-1.jpg',
                'uploads/melava_additional_photos/additional-2.jpg',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('melava_registrations')->insert([
            'profile_id' => $profileId,
            'candidate_photos' => json_encode(['uploads/melava_candidate_photos/candidate.jpg']),
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
