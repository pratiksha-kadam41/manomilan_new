<?php

namespace Tests\Unit;

use App\Http\Controllers\Candidate\ProfileController;
use App\Models\User;
use App\Profile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    protected $profileEventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profileEventDispatcher = Profile::getEventDispatcher();
        Profile::unsetEventDispatcher();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->string('user_type')->nullable();
            $table->timestamps();
        });
        Schema::create('profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('reg_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('groomorbride')->nullable();
            $table->string('full_name')->nullable();
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->decimal('height_cm')->nullable();
            $table->decimal('weight_kg')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('mother_tongue')->nullable();
            $table->string('birth_time')->nullable();
            $table->string('birth_place')->nullable();
            $table->decimal('monthly_income')->nullable();
            $table->boolean('has_medical_history')->nullable();
            $table->string('source_of_information')->nullable();
            $table->string('marital_status')->nullable();
            $table->json('photos')->nullable();
            $table->json('addresses')->nullable();
            $table->unsignedTinyInteger('profile_completion')->nullable();
            $table->timestamps();
        });
        Schema::create('profile_family_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('father_name')->nullable();
            $table->string('father_status')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_status')->nullable();
            $table->unsignedTinyInteger('brothers_count')->nullable();
            $table->unsignedTinyInteger('sisters_count')->nullable();
            $table->timestamps();
        });
        Schema::create('profile_education', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('education_level')->nullable();
            $table->string('degree_name')->nullable();
            $table->timestamps();
        });
        Schema::create('profile_employment', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('profession')->nullable();
            $table->string('working_field')->nullable();
            $table->string('employment_city')->nullable();
            $table->decimal('monthly_income')->nullable();
            $table->timestamps();
        });
        Schema::create('profile_religion_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('religion')->nullable();
            $table->string('caste')->nullable();
            $table->timestamps();
        });
        Schema::create('profile_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('aadhaar_card')->nullable();
            $table->string('birth_certificate')->nullable();
            $table->timestamps();
        });
        Schema::create('profile_partner_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('profile_id');
        });
        Schema::create('preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
        });
    }

    protected function tearDown(): void
    {
        Profile::setEventDispatcher($this->profileEventDispatcher);

        parent::tearDown();
    }

    public function test_filled_required_candidate_fields_produce_one_hundred_percent(): void
    {
        $userId = DB::table('users')->insertGetId([
            'username' => '9876543210',
            'user_type' => '21',
        ]);
        $profile = Profile::create([
            'reg_id' => 'MM3-TEST',
            'created_by' => $userId,
            'groomorbride' => 'Bride',
            'full_name' => 'Candidate Example',
            'phone' => '9876543210',
            'birth_date' => '1995-06-15',
            'height_cm' => 165,
            'weight_kg' => 55,
            'blood_group' => 'A+',
            'mother_tongue' => 'Marathi',
            'birth_time' => '10:00:00',
            'birth_place' => 'Pune',
            'monthly_income' => 50000,
            'has_medical_history' => false,
            'source_of_information' => 'Family/Friends',
            'marital_status' => 'Unmarried',
            'photos' => [
                'profile_photo' => 'profile.jpg',
                'full_body_photo' => 'full.jpg',
                'half_body_photo' => 'half.jpg',
                'selfie_photo' => 'selfie.jpg',
            ],
            'addresses' => [
                'current' => ['address' => 'Current address'],
                'permanent' => ['address' => 'Permanent address'],
                'reference' => ['name' => 'Reference', 'relation' => 'Friend'],
            ],
        ]);
        DB::table('users')->where('id', $userId)->update(['profile_id' => $profile->id]);
        DB::table('profile_religion_details')->insert([
            'profile_id' => $profile->id,
            'religion' => 'Jain',
            'caste' => 'Shwetambar',
        ]);
        DB::table('profile_family_details')->insert([
            'profile_id' => $profile->id,
            'father_name' => 'Father Example',
            'father_status' => 'Alive',
            'mother_name' => 'Mother Example',
            'mother_status' => 'Alive',
            'brothers_count' => 1,
            'sisters_count' => 0,
        ]);
        DB::table('profile_education')->insert([
            'profile_id' => $profile->id,
            'education_level' => 'UG',
            'degree_name' => 'Bachelor degree',
        ]);
        DB::table('profile_employment')->insert([
            'profile_id' => $profile->id,
            'profession' => 'Engineer',
            'working_field' => 'Technology',
            'employment_city' => 'Pune',
            'monthly_income' => 50000,
        ]);
        DB::table('profile_documents')->insert([
            'profile_id' => $profile->id,
            'aadhaar_card' => 'identity.pdf',
            'birth_certificate' => 'birth.pdf',
        ]);

        $profile->calculateCompletionPercentage();
        $this->assertSame(100, (int) $profile->fresh()->profile_completion);
        $this->assertTrue($profile->hasCompletedMandatoryFields());

        $profile->height_cm = null;
        $profile->save();
        $profile->calculateCompletionPercentage();
        $this->assertLessThan(100, (int) $profile->fresh()->profile_completion);

        $profile->height_cm = 165;
        $profile->save();
        $profile->calculateCompletionPercentage();

        $request = Request::create('/api/candidate/profile/status', 'GET');
        $request->setUserResolver(fn () => User::findOrFail($userId));
        $status = (new ProfileController())->status($request)->getData(true);
        $this->assertSame(100, $status['data']['profile_completion']);
        $this->assertTrue($status['data']['complete']);
    }
}
