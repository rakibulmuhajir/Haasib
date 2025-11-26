<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Policies will be registered here as they're created
        // Example: \App\Models\Invoice::class => \App\Policies\InvoicePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Super admin bypasses ALL permission checks
        Gate::before(function ($user, $ability) {
            // Check for super_admin role (global, no team context)
            if ($user->hasRole('super_admin')) {
                return true;
            }

            // Check for superadmin system role
            if ($user->isSuperAdmin()) {
                return true;
            }

            return null; // Proceed with normal authorization checks
        });
    }
}
