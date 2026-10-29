<?php

use App\Http\Controllers\Api\Auth\ChangePasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\InterestController;
use App\Http\Controllers\Api\MelavaRegistrationController;
use App\Http\Controllers\Api\MelavaCampaignController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\WorkshopRegistrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');

Route::post('/enquiries', [EnquiryController::class, 'store'])->middleware('throttle:10,1');
Route::get('/melava-registrations/settings', [MelavaRegistrationController::class, 'settings']);
Route::get('/melava-campaigns/active', [MelavaCampaignController::class, 'active']);
Route::get('/melava-registrations/check', [MelavaRegistrationController::class, 'check'])->middleware('throttle:20,1');
Route::post('/melava-registrations', [MelavaRegistrationController::class, 'store'])->middleware('throttle:10,1');
Route::post('/workshop-registrations', [WorkshopRegistrationController::class, 'store'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json(['user' => $request->user()->load('profile')]);
    });
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::put('/change-password', [ChangePasswordController::class, 'changePassword']);

    Route::get('/dashboard', [HomeController::class, 'dashboard']);
    Route::get('/home-data', [HomeController::class, 'homeData']);

    // Specific profile routes must stay above the generic /profiles/{id} route.
    Route::get('/profiles/allProfiles', [ProfileController::class, 'allProfiles']);
    Route::get('/profiles/export', [ProfileController::class, 'export']);
    Route::get('/profiles/{id}/verification', [ProfileController::class, 'verification']);
    Route::post('/profiles/{id}/verify', [ProfileController::class, 'verify']);
    Route::patch('/profiles/deactivate/{id}', [ProfileController::class, 'deactivate']);

    Route::get('/profiles', [ProfileController::class, 'index']);
    Route::get('/profiles/{id}', [ProfileController::class, 'show']);
    Route::get('/my-profile', [ProfileController::class, 'myProfile']);
    Route::post('/profiles', [ProfileController::class, 'store']);

    Route::get('/enquiries', [EnquiryController::class, 'index']);
    Route::get('/enquiries/{id}', [EnquiryController::class, 'show']);
    Route::put('/enquiries/{id}', [EnquiryController::class, 'update']);
    Route::delete('/enquiries/{id}', [EnquiryController::class, 'destroy']);

    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::put('/tickets/{id}', [TicketController::class, 'update']);

    Route::get('/interests/sent', [InterestController::class, 'sent']);
    Route::get('/interests/received', [InterestController::class, 'received']);
    Route::post('/interests/send', [InterestController::class, 'send']);
    Route::post('/interests/{id}/respond', [InterestController::class, 'respond']);

    Route::get('/preferences', [PreferenceController::class, 'show']);
    Route::post('/preferences', [PreferenceController::class, 'store']);

    Route::get('/melava-registrations', [MelavaRegistrationController::class, 'index']);
    Route::get('/melava-registrations/{id}', [MelavaRegistrationController::class, 'show']);
    Route::put('/melava-registrations/{id}/payment-status', [MelavaRegistrationController::class, 'updatePaymentStatus']);
    Route::get('/melava-campaigns', [MelavaCampaignController::class, 'index']);
    Route::post('/melava-campaigns', [MelavaCampaignController::class, 'store']);
    Route::put('/melava-campaigns/{id}/status', [MelavaCampaignController::class, 'updateStatus']);

    Route::get('/workshop-registrations', [WorkshopRegistrationController::class, 'index']);

    Route::post('/payments/upload', [PaymentController::class, 'uploadScreenshot']);
    Route::get('/payments/my', [PaymentController::class, 'myPayments']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::put('/payments/{id}/status', [PaymentController::class, 'updateStatus']);
});
