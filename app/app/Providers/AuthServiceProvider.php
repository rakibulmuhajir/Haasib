<?php

// app/Providers/AuthServiceProvider.php
namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // System-level abilities (outside tenant data)
        Gate::define('sys.access', fn(User $u) => $u->isSuperAdmin());
        Gate::define('sys.manage.tenants', fn(User $u) => $u->isSuperAdmin());

        // Only bypass for sys.* abilities
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin() && str_starts_with($ability, 'sys.')) {
                return true;
            }
            return null;
        });
    }
}
