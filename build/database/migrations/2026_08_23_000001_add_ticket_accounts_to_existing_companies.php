<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The COA pack applies at company creation
 * (CompanyOnboardingService::createIndustryChartOfAccounts()), which reads
 * IndustryCoaTemplate rows out of acct.industry_coa_templates -- a table
 * only ever populated by running IndustryCoaPackSeeder. Production's
 * deploy.sh runs migrate --force but never db:seed, so a seeder-only
 * change reaches nobody: this migration inserts the six template rows
 * itself, alongside backfilling existing companies, exactly as the
 * precedent (2026_08_21_000003_add_umrah_refund_accounts.php) does for
 * 1170/2300.
 *
 * Unlike that precedent's account backfill, these six accounts are
 * backfilled onto every company, not only umrah/travel ones: a company can
 * enable Umrah ticketing later, and an account nobody uses costs nothing.
 * The template insert, however, follows the precedent's scoping exactly --
 * see PACK_CODES below.
 *
 * Currency handling follows CompanyOnboardingService::createIndustryChartOfAccounts():
 * only "monetary" subtypes may carry a currency, set to the company's
 * base_currency; every other subtype (including five of these six
 * accounts) gets NULL, per the multicurrency contract's base-only rule.
 */
return new class extends Migration
{
    /**
     * The Umrah module does not run on one pack. IndustryCoaPackSeeder calls
     * seedUmrah() for BOTH the `umrah` and `travel` packs, and the `umrah`
     * pack is seeded is_active => false -- so every company actually running
     * the module today sits on `travel`. Scoping the template insert to
     * `umrah` alone reaches nobody: it inserts zero rows and looks, from the
     * outside, exactly like a clean run. Matching the precedent, both codes
     * get the template rows.
     */
    private const PACK_CODES = ['umrah', 'travel'];

    /**
     * Subtypes CompanyOnboardingService::createIndustryChartOfAccounts()
     * treats as monetary -- only these may carry a currency. Of the six
     * ticket accounts, only 2350 (other_current_liability) qualifies.
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

        // Backfill: every existing company already has its COA copied from
        // the template at creation time (if it has one at all), so it needs
        // these six rows inserted directly. Never touch a company's existing
        // account row -- an owner may have renamed it, and clobbering that
        // is data loss.
        $companies = DB::table('auth.companies')->get(['id', 'base_currency']);

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
                    'is_contra' => $account['is_contra'],
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
        // Codes 4130/4140/4150 are not unique to this migration -- they are
        // reused with different names by other industry packs (e.g. retail's
        // "Shipping & Delivery Revenue" also sits on 4140), and this
        // migration's up() backfilled every company, not only umrah/travel
        // ones, so scoping by industry_code the way the refund-accounts
        // precedent does would still risk another pack's same-numbered
        // account. Matching on name as well as code ensures a rollback only
        // ever removes a row this migration itself could have inserted.
        // Only drop a company account that has never been posted to.
        foreach ($this->accounts() as $account) {
            DB::table('acct.accounts')
                ->where('code', $account['code'])
                ->where('name', $account['name'])
                ->where('is_system', false)
                ->whereNotIn('id', function ($query) {
                    $query->select('account_id')->from('acct.journal_entries');
                })
                ->delete();
        }

        $codes = array_column($this->accounts(), 'code');

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
            ['code' => '2350', 'name' => 'Ticket Supplier Clearing', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'is_contra' => false],
            ['code' => '4130', 'name' => 'Ticket Commission Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_contra' => false],
            ['code' => '4140', 'name' => 'Ticket Service Fee Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_contra' => false],
            ['code' => '4150', 'name' => 'Ticket Discount', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true],
            ['code' => '4160', 'name' => 'Ticket Cancellation Adjustments', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true],
            ['code' => '9900', 'name' => 'Rounding Differences', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_contra' => false],
        ];
    }
};
