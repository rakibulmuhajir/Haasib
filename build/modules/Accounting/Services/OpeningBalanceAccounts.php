<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;

/**
 * Resolves the control accounts an opening-balance save needs.
 * Opening Balance Equity (3080) is created on demand; the rest are reported
 * as null so the caller can raise a precise "set up account X" error.
 */
class OpeningBalanceAccounts
{
    public const EQUITY_CODE = '3080';

    public function resolve(string $companyId): array
    {
        $active = fn () => Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true);
        $byCode = fn (string $code) => (clone $active())->where('code', $code)->value('id');
        $bySubtype = fn (string $subtype) => (clone $active())->where('subtype', $subtype)->orderBy('code')->value('id');

        return [
            'cash' => $byCode('1050') ?? $bySubtype('cash'),
            'ar' => $byCode('1100') ?? $bySubtype('accounts_receivable'),
            'ap' => $byCode('2100') ?? $bySubtype('accounts_payable'),
            'amanat' => $byCode('2200'),
            'partner_deposits' => $byCode('2210'),
            'employee_advances' => $byCode('1150'),
            'equity' => $this->ensureEquity($companyId),
        ];
    }

    private function ensureEquity(string $companyId): string
    {
        $existing = Account::where('company_id', $companyId)->where('code', self::EQUITY_CODE)->first();
        if ($existing) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }
            return $existing->id;
        }

        return Account::create([
            'company_id' => $companyId,
            'code' => self::EQUITY_CODE,
            'name' => 'Opening Balance Equity',
            'type' => 'equity',
            'subtype' => 'equity',
            'normal_balance' => 'credit',
            'description' => 'Counter-entry for opening balances',
            'is_active' => true,
        ])->id;
    }
}
