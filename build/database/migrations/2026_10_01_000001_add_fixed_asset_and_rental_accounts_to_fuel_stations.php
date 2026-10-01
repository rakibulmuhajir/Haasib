<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fixed assets (furniture, equipment, building improvements, each with its
 * accumulated depreciation), Depreciation Expense, and Rental Income -- rent
 * from shops on the station premises.
 *
 * The fuel station COA is copied into acct.accounts when a company is created,
 * so adding these to the template alone would miss every existing station;
 * this backfills them. A company that already has one of these codes keeps
 * its own row untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $packId = DB::table('acct.industry_coa_packs')->where('code', 'fuel_station')->value('id');
        if ($packId) {
            foreach ($this->accounts() as $account) {
                $after = DB::table('acct.industry_coa_templates')
                    ->where('industry_pack_id', $packId)
                    ->where('code', $account['after'])
                    ->value('sort_order');

                DB::table('acct.industry_coa_templates')->updateOrInsert(
                    ['industry_pack_id' => $packId, 'code' => $account['code']],
                    array_merge($this->row($account), [
                        'system_identifier' => null,
                        'sort_order' => $after !== null ? $after + $account['offset'] : 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                );
            }
        }

        $companyIds = DB::table('auth.companies')->where('industry_code', 'fuel_station')->pluck('id');

        foreach ($companyIds as $companyId) {
            // Tenant context, in case row-level security is enforced.
            DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);

            $existing = DB::table('acct.accounts')
                ->where('company_id', $companyId)
                ->whereIn('code', array_column($this->accounts(), 'code'))
                ->pluck('code')
                ->all();

            foreach ($this->accounts() as $account) {
                if (in_array($account['code'], $existing, true)) {
                    continue;
                }

                DB::table('acct.accounts')->insert(array_merge($this->row($account), [
                    'id' => (string) Str::uuid(),
                    'company_id' => $companyId,
                    'currency' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        DB::statement("SELECT set_config('app.current_company_id', '', false)");
    }

    public function down(): void
    {
        $codes = array_column($this->accounts(), 'code');
        $companyIds = DB::table('auth.companies')->where('industry_code', 'fuel_station')->pluck('id');

        foreach ($companyIds as $companyId) {
            DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);

            // Only accounts that have never been posted to.
            DB::table('acct.accounts')
                ->where('company_id', $companyId)
                ->whereIn('code', $codes)
                ->whereNotIn('id', fn ($q) => $q->select('account_id')->from('acct.journal_entries'))
                ->delete();
        }

        DB::statement("SELECT set_config('app.current_company_id', '', false)");

        $packId = DB::table('acct.industry_coa_packs')->where('code', 'fuel_station')->value('id');
        if ($packId) {
            DB::table('acct.industry_coa_templates')
                ->where('industry_pack_id', $packId)
                ->whereIn('code', $codes)
                ->delete();
        }
    }

    private function row(array $account): array
    {
        return [
            'code' => $account['code'],
            'name' => $account['name'],
            'type' => $account['type'],
            'subtype' => $account['subtype'],
            'normal_balance' => $account['normal_balance'],
            'is_contra' => $account['is_contra'] ?? false,
            'is_system' => false,
            'description' => $account['description'] ?? null,
        ];
    }

    /** 'after' / 'offset' place each one in the template's sort order. */
    private function accounts(): array
    {
        $fixed = ['type' => 'asset', 'subtype' => 'fixed_asset', 'after' => '1251'];
        $depreciation = $fixed + ['normal_balance' => 'credit', 'is_contra' => true];

        return [
            $fixed + ['code' => '1500', 'name' => 'Furniture & Fixtures', 'normal_balance' => 'debit', 'offset' => 1],
            $depreciation + ['code' => '1510', 'name' => 'Accumulated Depreciation – Furniture', 'offset' => 2],
            $fixed + ['code' => '1520', 'name' => 'Equipment & Machinery', 'normal_balance' => 'debit', 'description' => 'Generator, compressor, pumps', 'offset' => 3],
            $depreciation + ['code' => '1530', 'name' => 'Accumulated Depreciation – Equipment', 'offset' => 4],
            $fixed + ['code' => '1540', 'name' => 'Building Improvements', 'normal_balance' => 'debit', 'description' => 'Canopy, forecourt, construction', 'offset' => 5],
            $depreciation + ['code' => '1550', 'name' => 'Accumulated Depreciation – Building Improvements', 'offset' => 6],
            ['code' => '4400', 'name' => 'Rental Income', 'type' => 'other_income', 'subtype' => 'other_income', 'normal_balance' => 'credit', 'description' => 'Rent from shops on the station premises', 'after' => '4210', 'offset' => 5],
            ['code' => '6600', 'name' => 'Depreciation Expense', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Yearly write-down of fixed assets', 'after' => '6302', 'offset' => 5],
        ];
    }
};
