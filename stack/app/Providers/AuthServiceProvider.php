<?php

namespace App\Providers;

use App\Models\InvoiceTemplate;
use App\Policies\InvoiceTemplatePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        InvoiceTemplate::class => InvoiceTemplatePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Define gates for quick permission checks
        $this->defineGates();
    }

    /**
     * Define gates for quick permission checks.
     */
    protected function defineGates(): void
    {
        // Template-related gates
        Gate::define('view-templates', function ($user, $company = null) {
            if ($company) {
                return $user->hasPermissionTo('templates.view') &&
                       $user->companies()->where('companies.id', $company->id)->exists();
            }

            return $user->hasPermissionTo('templates.view');
        });

        Gate::define('create-templates', function ($user, $company = null) {
            if ($company) {
                return $user->hasPermissionTo('templates.create') &&
                       $user->companies()->where('companies.id', $company->id)->exists();
            }

            return $user->hasPermissionTo('templates.create');
        });

        Gate::define('update-templates', function ($user, $template) {
            return $user->hasPermissionTo('templates.update') &&
                   $user->companies()->where('companies.id', $template->company_id)->exists();
        });

        Gate::define('delete-templates', function ($user, $template) {
            return $user->hasPermissionTo('templates.delete') &&
                   $user->companies()->where('companies.id', $template->company_id)->exists();
        });

        Gate::define('apply-templates', function ($user, $template) {
            return ($user->hasPermissionTo('templates.apply') || $user->hasPermissionTo('invoices.create')) &&
                   $user->companies()->where('companies.id', $template->company_id)->exists();
        });

        Gate::define('duplicate-templates', function ($user, $template) {
            return $user->hasPermissionTo('templates.create') &&
                   $user->companies()->where('companies.id', $template->company_id)->exists();
        });
    }
}
