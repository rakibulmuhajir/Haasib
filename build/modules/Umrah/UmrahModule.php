<?php

namespace App\Modules\Umrah;

use App\Models\Company;
use App\Support\Modules\ModuleManifest;
use Illuminate\Http\Request;

class UmrahModule extends ModuleManifest
{
    public function key(): string { return 'umrah'; }
    public function industryCodes(): array { return ['umrah', 'travel']; }
    /** Picking umrah switches fuel off and, because umrah has no stock, inventory too. */
    public function conflicts(): array { return ['fuel_station', 'inventory']; }

    public function homeUrl(Company $company): ?string
    {
        return route('umrah.dashboard', ['company' => $company->slug]);
    }

    public function homeResponse(Request $request, Company $company): mixed
    {
        return redirect()->route('umrah.dashboard', ['company' => $company->slug]);
    }

    public function ownsPermissions(): bool { return true; }

    public function permissionGroupLabel(): string { return 'Umrah operations'; }

    public function permissionPrefixes(): array { return ['umrah']; }

    public function permissionCapabilities(Company $company, string $role, callable $has): ?array
    {
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
}
