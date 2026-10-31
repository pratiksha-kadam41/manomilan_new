<?php

use App\Http\Controllers\Api\Auth\ChangePasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\InterestController;
use App\Http\Controllers\Api\MelavaRegistrationController;
use App\Http\Controllers\Api\MelavaCampaignController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\SystemManagementController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\WorkshopRegistrationController;
use App\Http\Controllers\Candidate\DashboardController as CandidateDashboardController;
use App\Http\Controllers\Candidate\InterestController as CandidateInterestController;
use App\Http\Controllers\Candidate\MatchController as CandidateMatchController;
use App\Http\Controllers\Candidate\NotificationController as CandidateNotificationController;
use App\Http\Controllers\Candidate\PreferenceController as CandidatePreferenceController;
use App\Http\Controllers\Candidate\ProfileController as CandidateProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');

Route::post('/enquiries', [EnquiryController::class, 'store'])->middleware('throttle:10,1');
Route::get('/melava-registrations/settings', [MelavaRegistrationController::class, 'settings']);
Route::get('/melava-campaigns/active', [MelavaCampaignController::class, 'active']);
Route::get('/melava-registrations/check', [MelavaRegistrationController::class, 'check'])->middleware('throttle:20,1');
Route::post('/melava-registrations/draft', [MelavaRegistrationController::class, 'saveDraft'])->middleware('throttle:60,1');
Route::post('/melava-registrations', [MelavaRegistrationController::class, 'store'])->middleware('throttle:10,1');
Route::post('/workshop-registrations', [WorkshopRegistrationController::class, 'store'])->middleware('throttle:10,1');

Route::middleware(['auth.api-token', 'rbac.api', 'audit.api'])->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json(['user' => $request->user()->load('profile')]);
    });
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::put('/change-password', [ChangePasswordController::class, 'changePassword']);

    Route::get('/system/my-permissions', [SystemManagementController::class, 'myPermissions']);
    Route::prefix('system')->middleware('superadmin')->group(function () {
        Route::get('/', [SystemManagementController::class, 'index']);
        Route::get('/roles/{role}/permissions', [SystemManagementController::class, 'showRolePermissions'])->whereNumber('role');
        Route::put('/roles/{role}/permissions', [SystemManagementController::class, 'updateRolePermissions'])->whereNumber('role');
        Route::post('/roles', [SystemManagementController::class, 'storeRole']);
        Route::put('/roles/{role}', [SystemManagementController::class, 'updateRole'])->whereNumber('role');
        Route::delete('/roles/{role}', [SystemManagementController::class, 'deleteRole'])->whereNumber('role');
        Route::post('/modules', [SystemManagementController::class, 'storeModule']);
        Route::put('/modules/{module}', [SystemManagementController::class, 'updateModule'])->whereNumber('module');
        Route::delete('/modules/{module}', [SystemManagementController::class, 'deleteModule'])->whereNumber('module');
        Route::get('/users', [SystemManagementController::class, 'users']);
        Route::patch('/users/{user}/role', [SystemManagementController::class, 'assignUserRole'])->whereNumber('user');
        Route::get('/audit-logs/export', [SystemManagementController::class, 'exportAuditLogs']);
        Route::get('/audit-logs', [SystemManagementController::class, 'auditLogs']);
    });

    Route::get('/dashboard', [HomeController::class, 'dashboard']);
    Route::get('/reports', [HomeController::class, 'dashboard']);
    Route::get('/home-data', [HomeController::class, 'homeData']);
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::post('/admin/users/{id}/reset-password', [AdminUserController::class, 'resetPassword'])->whereNumber('id');

    // Specific profile routes must stay above the generic /profiles/{id} route.
    Route::get('/profiles/allProfiles', [ProfileController::class, 'allProfiles']);
    Route::get('/profiles/export', [ProfileController::class, 'export']);
    Route::get('/profiles/{id}/verification', [ProfileController::class, 'verification']);
    Route::post('/profiles/{id}/verify', [ProfileController::class, 'verify']);
    Route::patch('/profiles/deactivate/{id}', [ProfileController::class, 'deactivate']);

    Route::get('/profiles', [ProfileController::class, 'index']);
    Route::get('/profiles/{id}', [ProfileController::class, 'show']);
    Route::get('/profiles/{id}/biodata-preview', [ProfileController::class, 'biodataPreview']);
    Route::get('/profiles/{id}/documents/{docId}/file', [ProfileController::class, 'showDocument']);
    Route::delete('/profiles/{id}/documents/{docId}', [ProfileController::class, 'deleteDocument']);
    Route::get('/profiles/{id}/biodata', [ProfileController::class, 'downloadBiodata']);
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
    Route::put('/melava-registrations/{id}', [MelavaRegistrationController::class, 'update']);
    Route::post('/melava-registrations/{id}/payment', [MelavaRegistrationController::class, 'updatePayment']);
    Route::put('/melava-registrations/{id}/payment-status', [MelavaRegistrationController::class, 'updatePaymentStatus']);
    Route::get('/melava-campaigns', [MelavaCampaignController::class, 'index']);
    Route::post('/melava-campaigns', [MelavaCampaignController::class, 'store']);
    Route::put('/melava-campaigns/{id}', [MelavaCampaignController::class, 'update']);
    Route::delete('/melava-campaigns/{id}', [MelavaCampaignController::class, 'destroy']);
    Route::put('/melava-campaigns/{id}/status', [MelavaCampaignController::class, 'updateStatus']);

    Route::get('/workshop-registrations', [WorkshopRegistrationController::class, 'index']);

    Route::post('/payments/upload', [PaymentController::class, 'uploadScreenshot']);
    Route::get('/payments/my', [PaymentController::class, 'myPayments']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::put('/payments/{id}/status', [PaymentController::class, 'updateStatus']);
    Route::get('/profiles/{profileId}/payment-screenshots', [PaymentController::class, 'forProfile'])->whereNumber('profileId');
    Route::post('/profiles/{profileId}/payment-screenshots/{paymentId}/verify', [PaymentController::class, 'verifyForProfile'])->whereNumber('profileId')->whereNumber('paymentId');
    Route::post('/profiles/{profileId}/payment-screenshot', [PaymentController::class, 'uploadForProfile'])->whereNumber('profileId');
    Route::get('/profiles/{profileId}/receipt', [ReceiptController::class, 'show'])->whereNumber('profileId');

    Route::prefix('candidate')->group(function () {
        Route::get('/dashboard', [CandidateDashboardController::class, 'index']);

        Route::get('/profile', [CandidateProfileController::class, 'show']);
        Route::post('/profile', [CandidateProfileController::class, 'store']);
        Route::put('/profile', [CandidateProfileController::class, 'update']);
        Route::post('/profile/photo', [CandidateProfileController::class, 'uploadPhoto']);
        Route::delete('/profile/photo', [CandidateProfileController::class, 'deletePhoto']);
        Route::post('/profile/body-photos', [CandidateProfileController::class, 'uploadBodyPhotos']);
        Route::post('/profile/additional-photos', [CandidateProfileController::class, 'uploadAdditionalPhotos']);
        Route::post('/profile/documents', [CandidateProfileController::class, 'uploadDocuments']);
        Route::delete('/profile/documents/{key}', [CandidateProfileController::class, 'deleteDocument']);

        Route::get('/preferences', [CandidatePreferenceController::class, 'show']);
        Route::put('/preferences', [CandidatePreferenceController::class, 'update']);
        Route::post('/preferences', [CandidatePreferenceController::class, 'update']);

        Route::get('/matches', [CandidateMatchController::class, 'index']);
        Route::get('/matches/{id}', [CandidateMatchController::class, 'show'])->whereNumber('id');

        Route::get('/interests/sent', [CandidateInterestController::class, 'sent']);
        Route::get('/interests/received', [CandidateInterestController::class, 'received']);
        Route::post('/interests', [CandidateInterestController::class, 'store']);
        Route::post('/interests/{id}/respond', [CandidateInterestController::class, 'respond'])->whereNumber('id');

        Route::get('/notifications', [CandidateNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [CandidateNotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [CandidateNotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read', [CandidateNotificationController::class, 'markRead'])->whereNumber('id');
        Route::delete('/notifications/{id}', [CandidateNotificationController::class, 'destroy'])->whereNumber('id');
    });
});
