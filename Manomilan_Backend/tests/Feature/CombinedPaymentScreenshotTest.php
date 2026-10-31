<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PaymentController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CombinedPaymentScreenshotTest extends TestCase
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
            $table->string('profile_id')->nullable();
            $table->string('fullName')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->string('username')->nullable();
        });

        Schema::create('payment_screenshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('screenshot_path');
            $table->boolean('payment_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('melava_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('payment_screenshot')->nullable();
            $table->boolean('payment_verified')->default(false);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_same_payment_image_is_shown_once_and_approves_both_records(): void
    {
        $profileId = DB::table('profiles')->insertGetId([
            'profile_id' => 'MM3-0001',
            'fullName' => 'Test Profile',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $path = 'uploads/payment_screenshots/shared.png';
        $paymentId = DB::table('payment_screenshots')->insertGetId([
            'profile_id' => $profileId,
            'screenshot_path' => $path,
            'payment_verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $melavaId = DB::table('melava_registrations')->insertGetId([
            'profile_id' => $profileId,
            'payment_screenshot' => $path,
            'payment_verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = new User(['user_type' => '00']);

        $listRequest = Request::create('/api/profiles/'.$profileId.'/payment-screenshots');
        $listRequest->setUserResolver(fn () => $admin);
        $listResponse = (new PaymentController())->forProfile($listRequest, $profileId);
        $screenshots = $listResponse->getData(true)['screenshots'];

        $this->assertCount(1, $screenshots);
        $this->assertSame('combined', $screenshots[0]['type']);

        $verifyRequest = Request::create(
            '/api/profiles/'.$profileId.'/payment-screenshots/'.$paymentId.'/verify',
            'POST',
            ['type' => 'combined', 'melava_id' => $melavaId],
        );
        $verifyRequest->setUserResolver(fn () => $admin);
        $verifyResponse = (new PaymentController())->verifyForProfile($verifyRequest, $profileId, $paymentId);

        $this->assertSame(200, $verifyResponse->getStatusCode());
        $this->assertDatabaseHas('payment_screenshots', [
            'id' => $paymentId,
            'payment_verified' => true,
        ]);
        $this->assertDatabaseHas('melava_registrations', [
            'id' => $melavaId,
            'payment_verified' => true,
        ]);
    }
}
