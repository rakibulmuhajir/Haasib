<?php

// app/Providers/AuthServiceProvider.php
namespace App\Providers;

use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider {
    public function boot(): void {
        Gate::before(function (User $user, string $ability) {
            $role = Tenancy::userRoleInCurrentCompany($user->id);
            return in_array($role, ['owner','admin']) ? true : null; // full access for owner/admin
        });

        Gate::define('company.manageMembers', fn(User $u) => in_array(Tenancy::userRoleInCurrentCompany($u->id), ['owner','admin']));
        Gate::define('ledger.view', fn(User $u) => Tenancy::isMember($u->id));
        Gate::define('ledger.postJournal', fn(User $u) => in_array(Tenancy::userRoleInCurrentCompany($u->id), ['owner','admin','accountant']));
    }
}
