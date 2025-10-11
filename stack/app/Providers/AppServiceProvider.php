<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Observers\CompanyObserver;
use App\Observers\CompanyInvitationObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register model observers for audit logging
        Company::observe(CompanyObserver::class);
        CompanyInvitation::observe(CompanyInvitationObserver::class);
    }
}
