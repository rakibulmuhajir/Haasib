<?php

namespace App\Services;

use App\Models\Company;

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
        $isUmrah = $company->isModuleEnabled('umrah')
            || in_array($company->industry_code, ['umrah', 'travel'], true);

        if ($isUmrah) {
            return $prefix === 'umrah'
                || ($company->isModuleEnabled('payroll') && $this->isPayrollPermission($prefix));
        }

        if (! $company->isModuleEnabled('inventory') && $this->isInventoryPermission($prefix)) {
            return false;
        }

        if (! $company->isModuleEnabled('payroll') && $this->isPayrollPermission($prefix)) {
            return false;
        }

        return $prefix !== 'umrah';
    }

    /** @param array<int, string> $permissions */
    private function capabilities(Company $company, string $role, array $permissions): array
    {
        $has = fn (string $permission): bool => in_array($permission, $permissions, true);
        $isUmrah = $company->isModuleEnabled('umrah')
            || in_array($company->industry_code, ['umrah', 'travel'], true);

        if (! $isUmrah) {
            return [];
        }

        $financialsHidden = in_array($role, ['agent', 'operations'], true);
        $capabilities = [
            ['label' => 'View selling prices and customer rates', 'allowed' => $role !== 'operations', 'detail' => $role === 'agent' ? 'Own agent records only' : null],
            ['label' => 'View supplier costs and profit', 'allowed' => ! $financialsHidden, 'detail' => null],
            ['label' => 'View group accounting', 'allowed' => $has('umrah.group-accounting.view'), 'detail' => null],
            ['label' => 'View voucher accounting', 'allowed' => $has('umrah.voucher-accounting.view'), 'detail' => null],
            ['label' => 'View payments', 'allowed' => $has('umrah.payment.view'), 'detail' => $role === 'agent' ? 'Own agent records only' : null],
            ['label' => 'View and record expenses', 'allowed' => $has('umrah.expense.view'), 'detail' => null],
            ['label' => 'View financial reports', 'allowed' => $has('umrah.report.view') || $has('umrah.report.own.view'), 'detail' => $has('umrah.report.own.view') && ! $has('umrah.report.view') ? 'Own agent reports only' : null],
            ['label' => 'Manage vendors, rates and pricing settings', 'allowed' => $has('umrah.settings.update'), 'detail' => null],
            ['label' => 'Access all company records', 'allowed' => $role !== 'agent', 'detail' => $role === 'agent' ? 'Restricted to linked agent records' : null],
        ];

        if ($company->isModuleEnabled('payroll')) {
            $capabilities[] = ['label' => 'View employees and payroll', 'allowed' => $has('employee.view') || $has('payroll_run.view') || $has('payslip.view'), 'detail' => null];
            $capabilities[] = ['label' => 'Create or process payroll', 'allowed' => $has('payroll_run.create') || $has('payslip.create') || $has('payslip.pay'), 'detail' => null];
        }

        return $capabilities;
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
