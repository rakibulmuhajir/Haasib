<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\CompanyTaxSettings;
use App\Modules\Accounting\Models\TaxRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Withholding tax (WHT): what we deduct when we pay a vendor, and what a customer deducts when
 * they pay us. Everything here is gated by the one "Charge tax" switch (CompanyTax); with it off
 * no WHT is computed, offered or posted. Rates are the company's own `tax_rates` rows of type
 * 'withholding' - nothing is seeded, because rates change every budget.
 *
 * Amounts are in base currency; a foreign payment converts at its own exchange rate.
 */
class WithholdingTax
{
    /** Sections offered as suggestions on the Tax page. Rates are always entered by the user. */
    public const SECTIONS = [
        '153(1)(a)' => 'Goods',
        '153(1)(b)' => 'Services',
        '155' => 'Rent',
        '149' => 'Salaries',
        'other' => 'Other',
    ];

    public const SIDE_MADE = 'payments_made';
    public const SIDE_RECEIVED = 'payments_received';

    public function __construct(private readonly CompanyTax $tax) {}

    public function enabled(Company|string $company): bool
    {
        return $this->tax->enabled($company);
    }

    /**
     * The rate to apply for a party, or null when WHT is off or no rate was chosen.
     * A non-filer gets the non-filer rate; a rate with no non-filer value is refused, never
     * quietly replaced by the filer rate. An unknown status takes the filer rate.
     *
     * @return array{rate_id: string, rate: float, section: ?string, filer_status: string}|null
     */
    public function resolve(Company $company, ?string $rateId, string $side, ?string $filerStatus): ?array
    {
        if (! $rateId || ! $this->enabled($company)) {
            return null;
        }

        $rate = TaxRate::where('company_id', $company->id)->active()->find($rateId);
        if (! $rate || $rate->tax_type !== 'withholding') {
            throw ValidationException::withMessages(['wht_rate_id' => 'Choose a withholding rate from the Tax page.']);
        }
        if (! in_array($rate->applies_to ?? 'both', [$side, 'both'], true)) {
            throw ValidationException::withMessages(['wht_rate_id' => 'That rate is not for '.($side === self::SIDE_MADE ? 'payments we make' : 'payments we receive').'.']);
        }

        $status = in_array($filerStatus, ['filer', 'non_filer'], true) ? $filerStatus : 'unknown';
        if ($status === 'non_filer') {
            if ($rate->rate_non_filer === null) {
                throw ValidationException::withMessages(['wht_rate_id' => "{$rate->name} has no non-filer rate. Add it on the Tax page."]);
            }
            $pct = (float) $rate->rate_non_filer;
        } else {
            $pct = (float) $rate->rate;
        }

        return ['rate_id' => $rate->id, 'rate' => $pct, 'section' => $rate->section, 'filer_status' => $status];
    }

    /** WHT on a base-currency amount, rounded the way the company's tax settings round. */
    public function amount(Company $company, float $baseAmount, float $ratePct): float
    {
        $raw = $baseAmount * $ratePct / 100;
        $settings = CompanyTaxSettings::where('company_id', $company->id)->first();

        return round($settings ? $settings->roundAmount($raw) : $raw, 2);
    }

    /**
     * Split a payment-level WHT across its rows (split payments), the last row taking the
     * remainder so the rows add up to the total.
     *
     * @param  list<float>  $weights
     * @return list<float>
     */
    public function spread(float $total, array $weights): array
    {
        $sum = array_sum($weights);
        $out = [];
        $given = 0.0;
        foreach (array_values($weights) as $i => $w) {
            $share = $i === count($weights) - 1 ? round($total - $given, 2) : round($sum > 0 ? $total * $w / $sum : 0, 2);
            $out[] = $share;
            $given = round($given + $share, 2);
        }

        return $out;
    }

    /** The "WHT payable" liability account, created on first use. */
    public function payableAccount(Company $company): string
    {
        return $this->account($company, 'wht_payable_account_id', 'Withholding Tax Payable', '2310', '2319', [
            'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit',
            'description' => 'Tax withheld from vendors, owed to the tax authority.',
        ]);
    }

    /** The "advance tax" asset account (WHT our customers deducted), created on first use. */
    public function advanceTaxAccount(Company $company): string
    {
        return $this->account($company, 'advance_tax_account_id', 'Advance Income Tax (WHT deducted by customers)', '1196', '1199', [
            'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit',
            'description' => 'Tax our customers withheld; claimed back against our income tax.',
        ]);
    }

    private function account(Company $company, string $column, string $name, string $from, string $to, array $attributes): string
    {
        $settings = CompanyTaxSettings::forCompany($company);
        $current = $settings->{$column};
        if ($current && Account::where('company_id', $company->id)->whereKey($current)->exists()) {
            return $current;
        }

        $account = Account::where('company_id', $company->id)->where('name', $name)->first()
            ?? Account::create([
                'company_id' => $company->id,
                'code' => $this->nextCode($company->id, (int) $from, (int) $to),
                'name' => $name,
                'currency' => null,
                'is_contra' => false,
                'is_active' => true,
                'is_system' => false,
                ...$attributes,
            ]);

        // Straight to the table: saving the model would touch updated_at, and a settings row
        // that was only ever created reads as "never chosen" until its timestamps differ.
        DB::table('acct.company_tax_settings')->where('id', $settings->id)->update([$column => $account->id]);

        return $account->id;
    }

    private function nextCode(string $companyId, int $from, int $to): string
    {
        for ($code = $from; $code <= $to; $code++) {
            if (! Account::where('company_id', $companyId)->where('code', (string) $code)->exists()) {
                return (string) $code;
            }
        }

        return (string) $from;
    }

    /** A payment that withheld tax cannot be moved or split by a correction: void and re-enter. */
    public static function refuseCorrection(Model $payment): void
    {
        if ((float) ($payment->wht_amount ?? 0) > 0) {
            throw ValidationException::withMessages(['payment' => 'This payment has withholding tax. Void it and enter it again.']);
        }
    }
}
