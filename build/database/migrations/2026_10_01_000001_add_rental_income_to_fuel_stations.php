<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 4400 Rental Income -- rent from shops on the station premises (tyre shop,
 * tuck shop). The fuel station COA is copied into acct.accounts when a company
 * is created, so adding it to the template alone would miss every existing
 * station; this backfills them. An existing 4400 row is never touched.
 */
return new class extends Migration
{
    private const ACCOUNT = [
        'code' => '4400',
        'name' => 'Rental Income',
        'type' => 'other_income',
        'subtype' => 'other_income',
        'normal_balance' => 'credit',
        'description' => 'Rent from shops on the station premises',
    ];

    public function up(): void
    {
        $now = now();

        $packId = DB::table('acct.industry_coa_packs')->where('code', 'fuel_station')->value('id');
        if ($packId) {
            $after = DB::table('acct.industry_coa_templates')
                ->where('industry_pack_id', $packId)
                ->where('code', '4210')
                ->value('sort_order');

            DB::table('acct.industry_coa_templates')->updateOrInsert(
                ['industry_pack_id' => $packId, 'code' => self::ACCOUNT['code']],
                array_merge(self::ACCOUNT, [
                    'is_contra' => false,
                    'is_system' => false,
                    'system_identifier' => null,
                    'sort_order' => $after !== null ? $after + 5 : 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $companyIds = DB::table('auth.companies')->where('industry_code', 'fuel_station')->pluck('id');

        foreach ($companyIds as $companyId) {
            // Tenant context, in case row-level security is enforced.
            DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);

            $exists = DB::table('acct.accounts')
                ->where('company_id', $companyId)
                ->where('code', self::ACCOUNT['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('acct.accounts')->insert(array_merge(self::ACCOUNT, [
                'id' => (string) Str::uuid(),
                'company_id' => $companyId,
                'currency' => null,
                'is_contra' => false,
                'is_active' => true,
                'is_system' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        DB::statement("SELECT set_config('app.current_company_id', '', false)");
    }

    public function down(): void
    {
        $companyIds = DB::table('auth.companies')->where('industry_code', 'fuel_station')->pluck('id');

        foreach ($companyIds as $companyId) {
            DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);

            // Only an account that has never been posted to.
            DB::table('acct.accounts')
                ->where('company_id', $companyId)
                ->where('code', self::ACCOUNT['code'])
                ->whereNotIn('id', fn ($q) => $q->select('account_id')->from('acct.journal_entries'))
                ->delete();
        }

        DB::statement("SELECT set_config('app.current_company_id', '', false)");

        $packId = DB::table('acct.industry_coa_packs')->where('code', 'fuel_station')->value('id');
        if ($packId) {
            DB::table('acct.industry_coa_templates')
                ->where('industry_pack_id', $packId)
                ->where('code', self::ACCOUNT['code'])
                ->delete();
        }
    }
};
