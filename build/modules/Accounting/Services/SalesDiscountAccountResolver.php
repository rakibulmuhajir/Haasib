<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
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
                    throw ValidationException::withMessages(['period' => 'The configured Sales Discounts account must be active contra-revenue with a normal debit balance and no foreign currency. Check the company account mapping.']);
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
            throw ValidationException::withMessages(['period' => $valid->count() > 1
                ? 'More than one active Sales Discounts account was found. Configure the company discount account before posting.'
                : 'Set up an active Sales Discounts contra-revenue account with a normal debit balance before posting.']);
        }

        return Account::create(['company_id' => $company->id, 'code' => '4210', 'name' => 'Sales Discounts',
            'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true, 'is_active' => true, 'currency' => null]);
    }

    private function valid(Account $account, Company $company): bool
    {
        return $account->is_active && $account->type === 'revenue' && $account->normal_balance === 'debit'
            && $account->is_contra && (! $account->currency || $account->currency === $company->base_currency);
    }
}
