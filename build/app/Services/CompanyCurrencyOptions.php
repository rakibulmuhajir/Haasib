<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyCurrency;
use Illuminate\Support\Collection;

class CompanyCurrencyOptions
{
    public function forCompany(Company $company): Collection
    {
        return collect([[
            'currency_code' => $company->base_currency,
            'exchange_rate' => 1,
        ]])->concat(
            CompanyCurrency::query()
                ->where('company_id', $company->id)
                ->orderBy('currency_code')
                ->get(['currency_code', 'exchange_rate'])
                ->map(fn (CompanyCurrency $currency): array => [
                    'currency_code' => $currency->currency_code,
                    'exchange_rate' => $currency->exchange_rate,
                ])
        )->values();
    }
}
