<?php

namespace App\Providers;

use App\Observers\ProfileAuditObserver;
use App\Profile;
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
        Profile::observe(ProfileAuditObserver::class);
    }
}
