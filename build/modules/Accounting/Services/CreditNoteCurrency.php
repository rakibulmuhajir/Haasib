<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Validation\ValidationException;

class CreditNoteCurrency
{
    public function resolve(Company $company, ?Invoice $invoice, array $params, float $amount): array
    {
        $base = $company->base_currency;
        $currency = $params['currency'] ?? $invoice?->currency ?? $base;
        if ($invoice && $currency !== $invoice->currency) {
            throw ValidationException::withMessages(['currency' => 'Use the currency of the selected invoice.']);
        }
        $rate = null;
        if ($currency !== $base) {
            if (! CompanyCurrency::where('company_id', $company->id)->where('currency_code', $currency)->exists()) {
                throw ValidationException::withMessages(['currency' => 'Enable this currency for the company first.']);
            }
            $rate = $invoice?->exchange_rate ?? ($params['exchange_rate'] ?? null);
            if (! is_numeric($rate) || (float) $rate <= 0) {
                throw ValidationException::withMessages(['exchange_rate' => 'Enter a positive exchange rate for this foreign-currency credit note.']);
            }
        }

        return ['currency' => $currency, 'base_currency' => $base, 'exchange_rate' => $rate,
            'base_amount' => round($amount * ($rate ?? 1), 2)];
    }
}
