<?php

// app/Providers/AuthServiceProvider.php
namespace App\Providers;

use App\Models\User;
use App\Services\CompanyLookupService;
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

        // Unified authorization for CLI + GUI command execution
        Gate::define('command.execute', function (User $user, string $action, ?string $companyId = null) {
            // Superadmin can do any command
            if ($user->isSuperAdmin()) return true;

            // Map actions to required tenant roles (coarse UI gating)
            $requiredRoles = [
                'company.assign'   => ['owner'],
                'company.unassign' => ['owner'],
                'company.invite'   => ['owner','admin'],
                'invitation.revoke'=> ['owner','admin'],
                // Superadmin-only actions (handled by superadmin bypass)
                'company.create'   => [],
                'company.delete'   => [],
                'user.create'      => [],
                'user.delete'      => [],
            ][$action] ?? ['viewer'];

            // If no specific role listed, deny (non-superadmin)
            if (empty($requiredRoles)) return false;

            // UI-only actions (start with ui.) are always permitted here; backend won't receive them
            if (str_starts_with($action, 'ui.')) return true;

            if (! $companyId) return false;
            $role = app(CompanyLookupService::class)->userRole($companyId, $user->id);
            return $role !== null && in_array($role, $requiredRoles, true);
        });
    }
}
