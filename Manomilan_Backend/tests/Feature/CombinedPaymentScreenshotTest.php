<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PaymentController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_same_payment_image_is_shown_once_and_approves_both_records(): void
    {
        $profileId = DB::table('profiles')->insertGetId([
            'reg_id' => 'MM3-0001',
            'groomorbride' => 'Groom',
            'full_name' => 'Test Profile',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $campaignId = DB::table('melava_campaigns')->insertGetId([
            'melava_code' => 'PAYMENT-TEST',
            'title' => 'Payment Test Campaign',
            'currency' => 'INR',
            'registration_fee' => 100,
            'booklet_available' => false,
            'booklet_fee' => 0,
            'status' => 'active',
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
        $melavaId = DB::table('profile_melava_registrations')->insertGetId([
            'profile_id' => $profileId,
            'melava_campaign_id' => $campaignId,
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
        $this->assertDatabaseHas('profile_melava_registrations', [
            'profile_id' => $profileId,
            'melava_campaign_id' => $campaignId,
            'payment_verified' => true,
        ]);
    }
}
