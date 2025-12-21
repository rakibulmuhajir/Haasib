<?php

namespace App\Actions\Company;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Services\CurrentCompany;
use Illuminate\Validation\ValidationException;

class UpdateModulesAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'inventory' => ['required', 'boolean'],
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

        if (! $inventoryEnabled && $company->isModuleEnabled('fuel_station')) {
            throw ValidationException::withMessages([
                'inventory' => 'Inventory cannot be disabled while the Fuel Station module is enabled.',
            ]);
        }

        if ($inventoryEnabled) {
            $company->enableModule('inventory');
        } else {
            $company->disableModule('inventory');
        }

        return [
            'message' => $inventoryEnabled ? 'Inventory enabled.' : 'Inventory disabled.',
            'data' => [
                'modules' => [
                    'inventory' => $inventoryEnabled,
                ],
            ],
            'redirect' => "/{$company->slug}/settings",
        ];
    }
}

