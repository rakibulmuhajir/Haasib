<?php

namespace App\Services;

use App\Models\Company;
use App\Support\Modules\ModuleRegistry;

class UserPermissionPresenter
{
    /** @param array<int, string> $permissions */
    public function forCompany(Company $company, string $role, array $permissions): array
    {
        return [
            'permissions' => collect($permissions)
                ->filter(fn (string $permission) => $this->belongsToEnabledModule($company, $permission))
                ->values()
                ->all(),
            'capabilities' => $this->capabilities($company, $role, $permissions),
        ];
    }

    private function belongsToEnabledModule(Company $company, string $permission): bool
    {
        $prefix = explode('.', $permission)[0];
        $registry = app(ModuleRegistry::class);
        $enabled = $registry->enabledFor($company);

        // A module that owns the company's permissions (umrah) shows only its own and payroll's.
        foreach ($enabled as $manifest) {
            if ($manifest->ownsPermissions()) {
                return in_array($prefix, $manifest->permissionPrefixes(), true)
                    || ($company->isModuleEnabled('payroll') && $this->isPayrollPermission($prefix));
            }
        }

        if (! $company->isModuleEnabled('inventory') && $this->isInventoryPermission($prefix)) {
            return false;
        }

        if (! $company->isModuleEnabled('payroll') && $this->isPayrollPermission($prefix)) {
            return false;
        }

        // Permissions of a module that is off for this company stay out of the list.
        foreach ($registry->all() as $key => $manifest) {
            if (! isset($enabled[$key]) && in_array($prefix, $manifest->permissionPrefixes(), true)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, string> $permissions */
    private function capabilities(Company $company, string $role, array $permissions): array
    {
        $has = fn (string $permission): bool => in_array($permission, $permissions, true);

        foreach (app(ModuleRegistry::class)->enabledFor($company) as $manifest) {
            if ($manifest->ownsPermissions()) {
                return $manifest->permissionCapabilities($company, $role, $has) ?? [];
            }
        }

        return [];
    }

    private function isPayrollPermission(string $prefix): bool
    {
        return in_array($prefix, ['employee', 'payroll', 'payroll_run', 'leave_request', 'payslip'], true);
    }

    private function isInventoryPermission(string $prefix): bool
    {
        return in_array($prefix, ['item', 'item_category', 'warehouse', 'stock'], true);
    }
}
