<?php

// app/Providers/AuthServiceProvider.php
namespace App\Providers;

use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider {
    public function boot(): void {

         // System abilities the global admin can always do
    Gate::define('sys.access', fn(User $u) => $u->isSuperAdmin());
    Gate::define('sys.manage.tenants', fn(User $u) => $u->isSuperAdmin());

    // Optional: minimal bypass for a few tenant admin gates (not all)
    Gate::before(function (User $user, string $ability) {
        if ($user->isSuperAdmin() && str_starts_with($ability, 'sys.')) {
            return true; // only for system-level abilities
        }
        return null;
    });

        Gate::define('company.manageMembers', fn(User $u) => in_array(Tenancy::userRoleInCurrentCompany($u->id), ['owner','admin']));
        Gate::define('ledger.view', fn(User $u) => Tenancy::isMember($u->id));
        Gate::define('ledger.postJournal', fn(User $u) => in_array(Tenancy::userRoleInCurrentCompany($u->id), ['owner','admin','accountant']));
    }
}
