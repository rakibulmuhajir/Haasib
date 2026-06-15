<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Models\StationSettings;

class StationAccountMapper
{
    private const DEFINITIONS = [
        'cash_account_id' => [
            'codes' => ['1050'],
            'names' => ['Cash on Hand', 'Cash Drawer'],
            'create' => ['code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit'],
        ],
        'operating_bank_account_id' => [
            'codes' => ['1000'],
            'names' => ['Operating Bank Account', 'Main Bank'],
            'create' => ['code' => '1000', 'name' => 'Operating Bank Account', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
        ],
        'fuel_sales_account_id' => [
            'codes' => ['4190', '4100'],
            'names' => ['Fallback Fuel Sales', 'Fuel Sales - Other'],
            'create' => ['code' => '4190', 'name' => 'Fallback Fuel Sales', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
        ],
        'fuel_cogs_account_id' => [
            'codes' => ['5190', '5100'],
            'names' => ['Fallback Fuel Cost of Goods Sold', 'Cost of Fuel - Other'],
            'create' => ['code' => '5190', 'name' => 'Fallback Fuel Cost of Goods Sold', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
        ],
        'fuel_inventory_account_id' => [
            'codes' => ['1290', '1200'],
            'names' => ['Fallback Fuel Inventory', 'Fuel Inventory - Other'],
            'create' => ['code' => '1290', 'name' => 'Fallback Fuel Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
        ],
        'cash_over_short_account_id' => [
            'codes' => ['6180', '8050'],
            'names' => ['Cash Short/Over', 'Cash Short & Over'],
            'create' => ['code' => '6180', 'name' => 'Cash Short/Over', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ],
        'partner_drawings_account_id' => [
            'codes' => ['3200'],
            'names' => ['Partner Drawings', 'Owner Drawings'],
            'create' => ['code' => '3200', 'name' => 'Partner Drawings', 'type' => 'equity', 'subtype' => 'equity', 'normal_balance' => 'debit', 'is_contra' => true],
        ],
        'employee_advances_account_id' => [
            'codes' => ['1150'],
            'names' => ['Employee Advances'],
            'create' => ['code' => '1150', 'name' => 'Employee Advances', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
        ],
        'fuel_card_clearing_account_id' => [
            'codes' => ['1030'],
            'names' => ['Vendor Card Clearing', 'Fuel Card Clearing'],
            'create' => ['code' => '1030', 'name' => 'Vendor Card Clearing', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
        ],
        'card_pos_clearing_account_id' => [
            'codes' => ['1040'],
            'names' => ['Card Receipts Clearing', 'Card POS Clearing'],
            'create' => ['code' => '1040', 'name' => 'Card Receipts Clearing', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
        ],
    ];

    public function ensureMappings(StationSettings $settings, ?string $userId = null): StationSettings
    {
        $company = Company::query()->findOrFail($settings->company_id);
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));
        $updates = [];

        foreach (self::DEFINITIONS as $field => $definition) {
            if ($this->validAccountId($settings->{$field}, $settings->company_id)) {
                continue;
            }

            $updates[$field] = $this->resolveAccount($settings->company_id, $definition, $baseCurrency, $userId)->id;
        }

        $channels = $this->withAutomaticChannelMappings(
            $settings->payment_channels ?? StationSettings::DEFAULT_PAYMENT_CHANNELS,
            array_merge($settings->getAttributes(), $updates)
        );

        if ($channels !== ($settings->payment_channels ?? [])) {
            $updates['payment_channels'] = $channels;
        }

        if ($updates) {
            $settings->update($updates);
        }

        return $settings->fresh();
    }

    public function applyAutomaticPayloadMappings(StationSettings $settings, array $payload, ?string $userId = null): array
    {
        $settings = $this->ensureMappings($settings, $userId);

        foreach (array_keys(self::DEFINITIONS) as $field) {
            $payload[$field] = $payload[$field] ?? $settings->{$field};
        }

        $payload['payment_channels'] = $this->withAutomaticChannelMappings(
            $payload['payment_channels'] ?? $settings->payment_channels ?? StationSettings::DEFAULT_PAYMENT_CHANNELS,
            $payload
        );

        return $payload;
    }

    public function withAutomaticChannelMappings(array $channels, array $mappingSource): array
    {
        return array_map(function (array $channel) use ($mappingSource) {
            if (!($channel['enabled'] ?? false)) {
                return $channel;
            }

            $type = $channel['type'] ?? null;

            if ($type === 'bank_transfer') {
                $channel['bank_account_id'] = $channel['bank_account_id'] ?: ($mappingSource['operating_bank_account_id'] ?? null);
            }

            if ($type === 'card_pos') {
                $channel['bank_account_id'] = $channel['bank_account_id'] ?: ($mappingSource['operating_bank_account_id'] ?? null);
                $channel['clearing_account_id'] = $channel['clearing_account_id'] ?: ($mappingSource['card_pos_clearing_account_id'] ?? null);
            }

            if ($type === 'fuel_card') {
                $channel['bank_account_id'] = $channel['bank_account_id'] ?: ($mappingSource['operating_bank_account_id'] ?? null);
                $channel['clearing_account_id'] = $channel['clearing_account_id'] ?: ($mappingSource['fuel_card_clearing_account_id'] ?? null);
            }

            if ($type === 'mobile_wallet' && empty($channel['bank_account_id']) && empty($channel['clearing_account_id'])) {
                $channel['bank_account_id'] = $mappingSource['operating_bank_account_id'] ?? null;
            }

            return $channel;
        }, $channels);
    }

    private function resolveAccount(string $companyId, array $definition, string $baseCurrency, ?string $userId): Account
    {
        $account = $this->findByName($companyId, $definition);
        if ($account) {
            return $this->activate($account, $userId);
        }

        foreach ($definition['codes'] as $code) {
            $account = Account::query()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->whereNull('deleted_at')
                ->first();

            if ($account && $this->matchesDefinition($account, $definition)) {
                return $this->activate($account, $userId);
            }
        }

        return $this->createAccount($companyId, $definition['create'], $baseCurrency, $userId);
    }

    private function findByName(string $companyId, array $definition): ?Account
    {
        foreach ($definition['names'] as $name) {
            $account = Account::query()
                ->where('company_id', $companyId)
                ->where('type', $definition['create']['type'])
                ->where('subtype', $definition['create']['subtype'])
                ->where('name', 'ilike', '%' . $name . '%')
                ->whereNull('deleted_at')
                ->orderBy('code')
                ->first();

            if ($account) {
                return $account;
            }
        }

        return null;
    }

    private function createAccount(string $companyId, array $definition, string $baseCurrency, ?string $userId): Account
    {
        $code = $this->availableCode($companyId, $definition['code']);
        $currency = in_array($definition['subtype'], ['bank', 'cash', 'accounts_receivable', 'accounts_payable', 'credit_card', 'other_current_asset', 'other_asset', 'other_current_liability', 'other_liability'], true)
            ? $baseCurrency
            : null;

        return Account::query()->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $definition['name'],
            'type' => $definition['type'],
            'subtype' => $definition['subtype'],
            'normal_balance' => $definition['normal_balance'],
            'currency' => $currency,
            'is_contra' => $definition['is_contra'] ?? false,
            'is_active' => true,
            'is_system' => true,
            'description' => 'Auto-created for fuel station daily close posting.',
            'created_by_user_id' => $userId,
        ]);
    }

    private function activate(Account $account, ?string $userId): Account
    {
        if (!$account->is_active) {
            $account->update([
                'is_active' => true,
                'updated_by_user_id' => $userId,
            ]);
        }

        return $account->fresh();
    }

    private function matchesDefinition(Account $account, array $definition): bool
    {
        return $account->type === $definition['create']['type']
            && $account->subtype === $definition['create']['subtype']
            && $this->nameMatches($account->name, $definition['names']);
    }

    private function nameMatches(string $actual, array $expectedNames): bool
    {
        $actual = strtolower($actual);

        foreach ($expectedNames as $expected) {
            $expected = strtolower($expected);
            if ($actual === $expected || str_contains($actual, $expected) || str_contains($expected, $actual)) {
                return true;
            }
        }

        return false;
    }

    private function validAccountId(?string $accountId, string $companyId): ?string
    {
        if (!$accountId) {
            return null;
        }

        return Account::query()
            ->where('company_id', $companyId)
            ->where('id', $accountId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->value('id');
    }

    private function availableCode(string $companyId, string $startCode): string
    {
        $code = (int) $startCode;

        do {
            $candidate = (string) $code++;
            $exists = Account::query()
                ->where('company_id', $companyId)
                ->where('code', $candidate)
                ->whereNull('deleted_at')
                ->exists();
        } while ($exists);

        return $candidate;
    }
}
