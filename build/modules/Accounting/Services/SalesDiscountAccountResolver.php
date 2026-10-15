<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SalesDiscountAccountResolver
{
    /** @var array<string, callable(string): ?string> */
    private static array $resolvers = [];

    public static function extend(string $module, callable $resolver): void
    {
        self::$resolvers[$module] = $resolver;
    }

    public function resolve(Company $company): Account
    {
        foreach (self::$resolvers as $resolver) {
            $id = $resolver($company->id);
            if ($id !== null) {
                $account = Account::where('company_id', $company->id)->find($id);
                if (! $account || ! $this->valid($account, $company)) {
                    $this->unavailable($company, 'invalid_mapping', [$id]);
                }

                return $account;
            }
        }
        $candidates = Account::where('company_id', $company->id)
            ->where(fn ($q) => $q->whereIn('code', ['4210', '4910'])->orWhereRaw("lower(trim(name)) IN ('sales discount', 'sales discounts')"))
            ->orderBy('code')->get();
        $valid = $candidates->filter(fn ($account) => $this->valid($account, $company));
        if ($valid->count() === 1) {
            return $valid->first();
        }
        if ($candidates->isNotEmpty()) {
            $this->unavailable($company, $valid->count() > 1 ? 'ambiguous' : 'unavailable', $candidates->pluck('id')->all());
        }

        return Account::create(['company_id' => $company->id, 'code' => '4210', 'name' => 'Sales Discounts',
            'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true, 'is_active' => true, 'currency' => null]);
    }

    private function unavailable(Company $company, string $reason, array $accountIds): never
    {
        Log::warning('period_discount_account_setup_invalid', ['company_id' => $company->id, 'reason' => $reason, 'account_ids' => $accountIds]);
        throw ValidationException::withMessages(['period' => 'Discounts aren’t ready for this company yet. Ask your company administrator to review the discount setup, then try again.']);
    }

    private function valid(Account $account, Company $company): bool
    {
        return $account->is_active && $account->type === 'revenue' && $account->normal_balance === 'debit'
            && $account->is_contra && (! $account->currency || $account->currency === $company->base_currency);
    }
}
