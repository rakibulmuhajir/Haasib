<?php

namespace Modules\Acct\Providers;

use App\Providers\ModuleServiceProvider;

class AcctServiceProvider extends ModuleServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();
        
        // Register module-specific services
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
        
        // Bootstrap module-specific features
    }
}