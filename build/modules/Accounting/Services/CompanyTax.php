<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\CompanyTaxSettings;

/**
 * The single answer to "does this company charge tax?".
 *
 * Tax is OFF unless someone chose it (owner's decision, 2026-10-16):
 *
 *   1. the "Charge tax" switch, once saved, wins either way;
 *   2. otherwise "registered for tax" chosen at onboarding turns it on;
 *   3. otherwise off. Tax rates or old taxed documents alone do not turn it on;
 *      documents that already carry tax keep it (see allows()).
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

        $company = $company instanceof Company ? $company : Company::find($companyId);

        return (bool) $company?->tax_registered;
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
}
