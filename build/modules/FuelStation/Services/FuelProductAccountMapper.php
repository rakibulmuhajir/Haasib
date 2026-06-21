<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Inventory\Models\Item;

class FuelProductAccountMapper
{
    private const ACCOUNT_MAP = [
        'petrol' => [
            'income' => ['code' => '4100', 'name' => 'Fuel Sales - Petrol', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            'expense' => ['code' => '5100', 'name' => 'Cost of Fuel - Petrol', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            'asset' => ['code' => '1200', 'name' => 'Fuel Inventory - Petrol', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
        ],
        'high_octane' => [
            'income' => ['code' => '4101', 'name' => 'Fuel Sales - Hi-Octane', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            'expense' => ['code' => '5101', 'name' => 'Cost of Fuel - Hi-Octane', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            'asset' => ['code' => '1201', 'name' => 'Fuel Inventory - Hi-Octane', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
        ],
        'diesel' => [
            'income' => ['code' => '4102', 'name' => 'Fuel Sales - Diesel', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            'expense' => ['code' => '5102', 'name' => 'Cost of Fuel - Diesel', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            'asset' => ['code' => '1202', 'name' => 'Fuel Inventory - Diesel', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
        ],
        'lubricant' => [
            'income' => ['code' => '4150', 'name' => 'Lubricant Sales - Open', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            'expense' => ['code' => '5150', 'name' => 'Cost of Lubricants - Open', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            'asset' => ['code' => '1250', 'name' => 'Lubricant Inventory - Open', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
        ],
        'lubricant_packaged' => [
            'income' => ['code' => '4151', 'name' => 'Lubricant Sales - Sealed Packs', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            'expense' => ['code' => '5151', 'name' => 'Cost of Lubricants - Sealed Packs', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            'asset' => ['code' => '1251', 'name' => 'Lubricant Inventory - Sealed Packs', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
        ],
    ];

    public function ensureItemMappings(Item $item, ?string $userId = null): Item
    {
        $category = $this->normalizeCategory($item->fuel_category);
        if (!$category || !isset(self::ACCOUNT_MAP[$category])) {
            return $item;
        }

        $baseCurrency = strtoupper((string) ($item->company?->base_currency ?: 'PKR'));
        $accounts = $this->resolveAccounts($item->company_id, $category, $baseCurrency, $userId);

        $updates = [
            'income_account_id' => $item->income_account_id ?: $accounts['income']->id,
            'expense_account_id' => $item->expense_account_id ?: $accounts['expense']->id,
            'asset_account_id' => $item->asset_account_id ?: $accounts['asset']->id,
        ];

        if ($updates['income_account_id'] !== $item->income_account_id
            || $updates['expense_account_id'] !== $item->expense_account_id
            || $updates['asset_account_id'] !== $item->asset_account_id) {
            $item->update($updates + ['updated_by_user_id' => $userId]);
        }

        return $item->fresh();
    }

    public function resolveAccounts(string $companyId, string $category, string $baseCurrency = 'PKR', ?string $userId = null): array
    {
        $category = $this->normalizeCategory($category);
        $map = self::ACCOUNT_MAP[$category] ?? self::ACCOUNT_MAP['petrol'];

        return [
            'income' => $this->resolveOrCreateAccount($companyId, $map['income'], null, $userId),
            'expense' => $this->resolveOrCreateAccount($companyId, $map['expense'], null, $userId),
            'asset' => $this->resolveOrCreateAccount($companyId, $map['asset'], $baseCurrency, $userId),
        ];
    }

    private function resolveOrCreateAccount(string $companyId, array $definition, ?string $currency, ?string $userId): Account
    {
        $account = Account::where('company_id', $companyId)
            ->where('code', $definition['code'])
            ->whereNull('deleted_at')
            ->first();

        if ($account) {
            $updates = [];

            if (in_array($account->name, ['Fuel Sales', 'Cost of Goods - Fuel', 'Fuel Inventory'], true)) {
                $updates['name'] = $definition['name'];
            }

            if (!$account->is_active) {
                $updates['is_active'] = true;
            }

            if (!empty($updates)) {
                $account->update($updates + ['updated_by_user_id' => $userId]);
            }

            return $account->fresh();
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => $definition['code'],
            'name' => $definition['name'],
            'type' => $definition['type'],
            'subtype' => $definition['subtype'],
            'normal_balance' => $definition['normal_balance'],
            'currency' => $currency,
            'is_active' => true,
            'is_system' => false,
            'created_by_user_id' => $userId,
        ]);
    }

    private function normalizeCategory(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        return match ($category) {
            'hi_octane' => 'high_octane',
            'sealed_lubricant', 'packaged_lubricant' => 'lubricant_packaged',
            default => $category,
        };
    }
}
