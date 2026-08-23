<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The COA pack applies at company creation
 * (CompanyOnboardingService::createIndustryChartOfAccounts()), so adding
 * accounts to IndustryCoaPackSeeder::seedUmrah() alone would leave every
 * live company without them, and every ticket posting failing on a missing
 * role mapping at the moment of first use.
 *
 * Unlike the umrah/travel-only refund accounts (2026_08_21_000003), these
 * are backfilled onto every company, not only umrah/travel ones: a company
 * can enable Umrah ticketing later, and an account nobody uses costs
 * nothing.
 *
 * Currency handling follows CompanyOnboardingService::createIndustryChartOfAccounts():
 * only "monetary" subtypes may carry a currency, set to the company's
 * base_currency; every other subtype (including these five revenue/expense
 * accounts) gets NULL, per the multicurrency contract's base-only rule.
 */
return new class extends Migration
{
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
        $codes = array_column($this->accounts(), 'code');

        // Only drop a company account that has never been posted to.
        DB::table('acct.accounts')
            ->whereIn('code', $codes)
            ->where('is_system', false)
            ->whereNotIn('id', function ($query) {
                $query->select('account_id')->from('acct.journal_entries');
            })
            ->delete();
    }

    private function accounts(): array
    {
        return [
            ['code' => '2350', 'name' => 'Ticket Supplier Clearing', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'is_contra' => false],
            ['code' => '4130', 'name' => 'Ticket Commission Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_contra' => false],
            ['code' => '4140', 'name' => 'Ticket Service Fee Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_contra' => false],
            ['code' => '4150', 'name' => 'Ticket Discount', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true],
            ['code' => '4160', 'name' => 'Ticket Cancellation Adjustments', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => false],
            ['code' => '9900', 'name' => 'Rounding Differences', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_contra' => false],
        ];
    }
};
