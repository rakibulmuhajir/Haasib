<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 2a of docs/contracts/refunds.md -- the two accounts the refund
 * postings need. This migration lays no ledger entry itself; it only makes
 * the accounts exist everywhere the settlement pass (Phase 2b) will need
 * them:
 *
 *  - 2300 Refunds Payable (liability, credit) -- an agent refund
 *  - 1170 Refunds Receivable (asset, debit) -- a vendor refund
 *
 * The umrah COA pack is copied into acct.accounts at company creation
 * (CompanyOnboardingService::createIndustryChartOfAccounts()), so touching
 * only the template here would leave every existing umrah company without
 * these two accounts. This migration backfills them too.
 */
return new class extends Migration
{
    /**
     * The Umrah module does not run on one pack. IndustryCoaPackSeeder calls
     * seedUmrah() for BOTH the `umrah` and `travel` packs, and the `umrah`
     * pack is seeded is_active => false -- so every company actually running
     * the module today sits on `travel`. Scoping either the template insert
     * or the backfill to `umrah` alone reaches nobody: it inserts zero rows
     * and looks, from the outside, exactly like a clean run.
     */
    private const PACK_CODES = ['umrah', 'travel'];

    /**
     * Subtypes CompanyOnboardingService::createIndustryChartOfAccounts()
     * treats as monetary -- only these may carry a currency. Both new
     * accounts are "other_current_asset" / "other_current_liability", so
     * both qualify and both get the company's base_currency.
     */
    private const MONETARY_SUBTYPES = [
        'bank',
        'cash',
        'accounts_receivable',
        'accounts_payable',
        'credit_card',
        'other_current_asset',
        'other_asset',
        'other_current_liability',
        'other_liability',
    ];

    public function up(): void
    {
        $now = now();

        $industryIds = DB::table('acct.industry_coa_packs')
            ->whereIn('code', self::PACK_CODES)
            ->pluck('id');

        foreach ($industryIds as $industryId) {
            foreach ($this->accounts() as $account) {
                DB::table('acct.industry_coa_templates')->updateOrInsert(
                    [
                        'industry_pack_id' => $industryId,
                        'code' => $account['code'],
                    ],
                    array_merge([
                        'is_contra' => false,
                        'is_system' => false,
                        'system_identifier' => null,
                        'description' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $account)
                );
            }
        }

        // Backfill: every existing umrah company already has its COA copied
        // from the template at creation time, so it needs these two rows
        // inserted directly. Never touch a company's existing account row --
        // an owner may have renamed it, and clobbering that is data loss.
        $companies = DB::table('auth.companies')
            ->whereIn('industry_code', self::PACK_CODES)
            ->get(['id', 'base_currency']);

        foreach ($companies as $company) {
            foreach ($this->accounts() as $account) {
                $exists = DB::table('acct.accounts')
                    ->where('company_id', $company->id)
                    ->where('code', $account['code'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $currency = in_array($account['subtype'], self::MONETARY_SUBTYPES, true)
                    ? $company->base_currency
                    : null;

                DB::table('acct.accounts')->insert([
                    'id' => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'code' => $account['code'],
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'subtype' => $account['subtype'],
                    'normal_balance' => $account['normal_balance'],
                    'currency' => $currency,
                    'is_contra' => false,
                    'is_active' => true,
                    'is_system' => false,
                    'description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $codes = array_column($this->accounts(), 'code');

        // Codes 1170/2300 are not unique across industry packs (insurance and
        // nonprofit both reuse 2300 for something else entirely), so scope
        // the account deletion to umrah companies only. Only drop a company
        // account that has never been posted to.
        $umrahCompanyIds = DB::table('auth.companies')
            ->whereIn('industry_code', self::PACK_CODES)
            ->pluck('id');

        DB::table('acct.accounts')
            ->whereIn('company_id', $umrahCompanyIds)
            ->whereIn('code', $codes)
            ->whereNotIn('id', function ($query) {
                $query->select('account_id')->from('acct.journal_entries');
            })
            ->delete();

        $industryIds = DB::table('acct.industry_coa_packs')
            ->whereIn('code', self::PACK_CODES)
            ->pluck('id');

        DB::table('acct.industry_coa_templates')
            ->whereIn('industry_pack_id', $industryIds)
            ->whereIn('code', $codes)
            ->delete();
    }

    private function accounts(): array
    {
        return [
            ['code' => '1170', 'name' => 'Refunds Receivable', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit', 'sort_order' => 35],
            ['code' => '2300', 'name' => 'Refunds Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'sort_order' => 55],
        ];
    }
};
