<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('acct.industry_coa_packs')->updateOrInsert(
            ['code' => 'umrah'],
            [
                'name' => 'Umrah / Travel Visa Agency',
                'description' => 'Visa groups, agents, passports, transport requirements, payments, and earnings.',
                'is_active' => true,
                'sort_order' => 16,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $industryId = DB::table('acct.industry_coa_packs')->where('code', 'umrah')->value('id');

        foreach ($this->accounts() as $index => $account) {
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
                ], $account, [
                    'sort_order' => $index * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }

    public function down(): void
    {
        $industryId = DB::table('acct.industry_coa_packs')->where('code', 'umrah')->value('id');

        if ($industryId) {
            DB::table('acct.industry_coa_templates')->where('industry_pack_id', $industryId)->delete();
            DB::table('acct.industry_coa_packs')->where('id', $industryId)->delete();
        }
    }

    private function accounts(): array
    {
        return [
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Agent Receivables', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1160', 'name' => 'Advances to Visa Vendors', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
            ['code' => '2100', 'name' => 'Visa Vendor Payables', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2200', 'name' => 'Agent Advances / Unearned Visa Revenue', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],
            ['code' => '4100', 'name' => 'Visa Service Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Transport Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '5100', 'name' => 'Visa Cost', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'cogs'],
            ['code' => '5110', 'name' => 'Transport Cost', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6200', 'name' => 'Salaries', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6300', 'name' => 'Office Rent', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6400', 'name' => 'Communication & Courier', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];
    }
};
