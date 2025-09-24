<?php

// app/Providers/AuthServiceProvider.php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\CompanyLookupService;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Invoice::class => \App\Policies\InvoicePolicy::class,
        Customer::class => \App\Policies\CustomerPolicy::class,
        Payment::class => \App\Policies\PaymentPolicy::class,
    ];

    public function boot(): void
    {
        // System-level abilities (outside tenant data)
        Gate::define('sys.access', fn (User $u) => $u->isSuperAdmin());
        Gate::define('sys.manage.tenants', fn (User $u) => $u->isSuperAdmin());

        // Only bypass for sys.* abilities
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin() && str_starts_with($ability, 'sys.')) {
                return true;
            }

            return null;
        });

        // Ledger permissions - require company context and proper role-based permissions
        Gate::define('ledger.view', function (User $user) {
            $companyId = $user->currentCompany?->id;
            if (! $companyId) {
                return false;
            }

            // Superadmin can always view
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Check if user has permission through role in current company
            $role = app(CompanyLookupService::class)->userRole($companyId, $user->id);

            return in_array($role, ['owner', 'admin', 'accountant', 'viewer']);
        });

        Gate::define('ledger.create', function (User $user) {
            $companyId = $user->currentCompany?->id;
            if (! $companyId) {
                return false;
            }

            if ($user->isSuperAdmin()) {
                return true;
            }

            $role = app(CompanyLookupService::class)->userRole($companyId, $user->id);

            return in_array($role, ['owner', 'admin', 'accountant']);
        });

        Gate::define('ledger.accounts.view', function (User $user) {
            $companyId = $user->currentCompany?->id;
            if (! $companyId) {
                return false;
            }

            if ($user->isSuperAdmin()) {
                return true;
            }

            $role = app(CompanyLookupService::class)->userRole($companyId, $user->id);

            return in_array($role, ['owner', 'admin', 'accountant', 'viewer']);
        });

        // Unified authorization for CLI + GUI command execution
        Gate::define('command.execute', function (User $user, string $action, ?string $companyId = null) {
            // Superadmin can do any command regardless of company context
            if ($user->isSuperAdmin()) {
                return true;
            }

            // For non-superadmins, require company context
            if (! $companyId) {
                return false;
            }

            // Map actions to required tenant roles (coarse UI gating)
            $requiredRoles = [
                'company.assign' => ['owner'],
                'company.unassign' => ['owner'],
                'company.invite' => ['owner', 'admin'],
                'invitation.revoke' => ['owner', 'admin'],
                // Superadmin-only actions (handled by superadmin bypass)
                'company.create' => [],
                'company.delete' => [],
                'user.create' => [],
                'user.delete' => [],
            ][$action] ?? ['viewer'];

            // If no specific role listed, deny (non-superadmin)
            if (empty($requiredRoles)) {
                return false;
            }

            // UI-only actions (start with ui.) are always permitted here; backend won't receive them
            if (str_starts_with($action, 'ui.')) {
                return true;
            }

            $role = app(CompanyLookupService::class)->userRole($companyId, $user->id);

            return $role !== null && in_array($role, $requiredRoles, true);
        });

        // Invoicing permissions
        Gate::define('invoices.view', function (User $user) {
            $companyId = $user->currentCompany?->id;
            if (! $companyId) {
                return false;
            }
            if ($user->isSuperAdmin()) {
                return true;
            }
            $role = app(CompanyLookupService::class)->userRole($companyId, $user->id);

            return in_array($role, ['owner', 'admin', 'accountant', 'viewer']);
        });

        Gate::define('invoices.create', function (User $user) {
            $companyId = $user->currentCompany?->id;
            if (! $companyId) {
                return false;
            }
            if ($user->isSuperAdmin()) {
                return true;
            }
            $role = app(CompanyLookupService::class)->userRole($companyId, $user->id);

            return in_array($role, ['owner', 'admin', 'accountant']);
        });

        Gate::define('invoices.edit', fn (User $user) => Gate::forUser($user)->check('invoices.create'));
        Gate::define('invoices.delete', fn (User $user) => Gate::forUser($user)->check('invoices.create'));
        Gate::define('invoices.send', fn (User $user) => Gate::forUser($user)->check('invoices.create'));
        Gate::define('invoices.post', fn (User $user) => Gate::forUser($user)->check('invoices.create'));

        // Payments permissions
        Gate::define('payments.view', function (User $user) {
            $companyId = $user->currentCompany?->id;
            if (! $companyId) {
                return false;
            }
            if ($user->isSuperAdmin()) {
                return true;
            }
            $role = app(CompanyLookupService::class)->userRole($companyId, $user->id);

            return in_array($role, ['owner', 'admin', 'accountant', 'viewer']);
        });
        Gate::define('payments.create', function (User $user) {
            $companyId = $user->currentCompany?->id;
            if (! $companyId) {
                return false;
            }
            if ($user->isSuperAdmin()) {
                return true;
            }
            $role = app(CompanyLookupService::class)->userRole($companyId, $user->id);

            return in_array($role, ['owner', 'admin', 'accountant']);
        });
        Gate::define('payments.edit', fn (User $user) => Gate::forUser($user)->check('payments.create'));
        Gate::define('payments.delete', fn (User $user) => Gate::forUser($user)->check('payments.create'));
        Gate::define('payments.allocate', fn (User $user) => Gate::forUser($user)->check('payments.create'));
    }
}
