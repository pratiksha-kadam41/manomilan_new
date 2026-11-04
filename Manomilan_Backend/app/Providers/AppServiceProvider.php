<?php

namespace App\Providers;

use App\Observers\BusinessRecordAuditObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            \App\Application::class,
            \App\Candidate::class,
            \App\CandidateRequest::class,
            \App\DocumentVerification::class,
            \App\Enquiry::class,
            \App\Meeting::class,
            \App\MelavaCampaign::class,
            \App\MelavaPendingRegistration::class,
            \App\Module::class,
            \App\Payment::class,
            \App\PaymentScreenshot::class,
            \App\Preference::class,
            \App\Profile::class,
            \App\ProfileDocument::class,
            \App\ProfileEducation::class,
            \App\ProfileEmployment::class,
            \App\ProfileFamilyDetail::class,
            \App\ProfileMelavaRegistration::class,
            \App\ProfilePartnerPreference::class,
            \App\ProfileReligionDetail::class,
            \App\ProfileVisitor::class,
            \App\Role::class,
            \App\SuccessStory::class,
            \App\Ticket::class,
            \App\TicketComment::class,
            \App\TicketItem::class,
            \App\TicketStatusHistory::class,
            \App\UserInterest::class,
            \App\WorkshopEvent::class,
            \App\WorkshopRegistration::class,
            \App\Models\CandidateNotification::class,
            \App\Models\ModulePermission::class,
            \App\Models\User::class,
            \App\Models\WebsiteDowntimeSetting::class,
            \App\Models\WebsiteMaintenanceSetting::class,
            \App\Models\WebsiteSetting::class,
        ] as $modelClass) {
            if (is_subclass_of($modelClass, Model::class)) {
                $modelClass::observe(BusinessRecordAuditObserver::class);
            }
        }
    }
}
