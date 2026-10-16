<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\CompanyTaxSettings;
use App\Modules\Accounting\Models\TaxRate;
use Illuminate\Support\Facades\DB;

/**
 * The single answer to "does this company charge tax?".
 *
 * The switch is CompanyTaxSettings.tax_enabled, but that column alone cannot say
 * "never chosen": it defaults to false and the Tax settings page used to create the row
 * just by being opened. So the effective value is:
 *
 *   1. a switch that was saved on purpose (row edited after creation) wins, either way;
 *   2. otherwise the company is "on" when it already uses tax -- it has a tax rate, a
 *      taxed document, or registered for tax at onboarding -- so nothing changes for it;
 *   3. otherwise "off".
 */
class CompanyTax
{
    public function enabled(Company|string $company): bool
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        $settings = CompanyTaxSettings::where('company_id', $companyId)->first();

        if ($settings && $settings->isDeliberate()) {
            return (bool) $settings->tax_enabled;
        }
        if ($settings && $settings->tax_enabled) {
            return true;
        }

        return $this->usesTax($companyId, $company instanceof Company ? $company : null);
    }

    /**
     * May a write for this company carry tax? A document that already carries tax keeps
     * it when edited, even with the switch off -- stored documents are never altered.
     */
    public function allows(Company|string $company, float|int|string|null $existingTaxAmount = 0): bool
    {
        return (float) $existingTaxAmount > 0 || $this->enabled($company);
    }

    /** Zero every line's tax_rate in a write payload when tax is not allowed. */
    public function stripLines(array $lines, bool $allowed): array
    {
        if ($allowed) {
            return $lines;
        }

        return array_map(function ($line) {
            if (is_array($line)) {
                $line['tax_rate'] = 0;
            }

            return $line;
        }, $lines);
    }

    private function usesTax(string $companyId, ?Company $company): bool
    {
        $company ??= Company::find($companyId);
        if ($company?->tax_registered) {
            return true;
        }

        if (TaxRate::where('company_id', $companyId)->exists()) {
            return true;
        }

        foreach (['acct.invoices', 'acct.bills'] as $table) {
            if (DB::table($table)->where('company_id', $companyId)->where('tax_amount', '>', 0)->exists()) {
                return true;
            }
        }
        foreach (['acct.credit_note_items', 'acct.vendor_credit_items'] as $table) {
            if (DB::table($table)->where('company_id', $companyId)->where('tax_amount', '>', 0)->exists()) {
                return true;
            }
        }

        return false;
    }
}
