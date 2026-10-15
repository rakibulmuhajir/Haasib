<?php

namespace App\Actions\Company;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Services\CurrentCompany;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Validation\ValidationException;

class UpdateModulesAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'inventory' => ['required', 'boolean'],
            'payroll' => ['required', 'boolean'],
        ];
    }

    public function permission(): ?string
    {
        return Permissions::COMPANY_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = app(CurrentCompany::class)->get();
        if (! $company) {
            throw new \RuntimeException('Company context required but not set.');
        }

        $inventoryEnabled = (bool) $params['inventory'];
        $payrollEnabled = (bool) $params['payroll'];

        if (! $inventoryEnabled) {
            foreach (app(ModuleRegistry::class)->all() as $manifest) {
                if (in_array('inventory', $manifest->requires(), true) && $company->isModuleEnabled($manifest->key())) {
                    throw ValidationException::withMessages([
                        'inventory' => "Inventory cannot be disabled while the {$manifest->name()} module is enabled.",
                    ]);
                }
            }
        }

        if ($inventoryEnabled) {
            $company->enableModule('inventory');
        } else {
            $company->disableModule('inventory');
        }

        if ($payrollEnabled) {
            $company->enableModule('payroll');
        } else {
            $company->disableModule('payroll');
        }

        return [
            'message' => 'Module settings updated.',
            'data' => [
                'modules' => [
                    'inventory' => $inventoryEnabled,
                    'payroll' => $payrollEnabled,
                ],
            ],
            'redirect' => "/{$company->slug}/settings",
        ];
    }
}
