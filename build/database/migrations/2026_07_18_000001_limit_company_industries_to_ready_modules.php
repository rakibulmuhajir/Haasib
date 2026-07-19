<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $supported = [
            'fuel_station' => ['name' => 'Petrol Pump', 'description' => 'Fuel station operations, inventory, shifts, and accounting.', 'sort_order' => 1],
            'travel' => ['name' => 'Travel', 'description' => 'Visa groups, vouchers, hotels, transport, payments, and reports.', 'sort_order' => 2],
            'other' => ['name' => 'Other', 'description' => 'General accounting for businesses without a dedicated module.', 'sort_order' => 3],
        ];

        DB::transaction(function () use ($now, $supported): void {
            DB::table('acct.industry_coa_packs')
                ->whereNotIn('code', array_keys($supported))
                ->update(['is_active' => false, 'updated_at' => $now]);

            foreach ($supported as $code => $attributes) {
                $pack = DB::table('acct.industry_coa_packs')->where('code', $code)->first();
                if ($pack) {
                    DB::table('acct.industry_coa_packs')->where('id', $pack->id)->update([
                        ...$attributes,
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                DB::table('acct.industry_coa_packs')->insert([
                    'id' => (string) Str::uuid(),
                    'code' => $code,
                    ...$attributes,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $otherId = DB::table('acct.industry_coa_packs')->where('code', 'other')->value('id');
            foreach ($this->otherTemplates() as $sortOrder => $template) {
                $existingId = DB::table('acct.industry_coa_templates')
                    ->where('industry_pack_id', $otherId)
                    ->where('code', $template['code'])
                    ->value('id');
                $values = [
                    ...$template,
                    'is_contra' => $template['is_contra'] ?? false,
                    'is_system' => $template['is_system'] ?? false,
                    'system_identifier' => $template['system_identifier'] ?? null,
                    'description' => $template['description'] ?? null,
                    'sort_order' => $sortOrder + 1,
                    'updated_at' => $now,
                ];

                if ($existingId) {
                    DB::table('acct.industry_coa_templates')->where('id', $existingId)->update($values);
                } else {
                    DB::table('acct.industry_coa_templates')->insert([
                        'id' => (string) Str::uuid(),
                        'industry_pack_id' => $otherId,
                        ...$values,
                        'created_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('acct.industry_coa_packs')->where('code', 'other')->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('acct.industry_coa_packs')->whereNotIn('code', ['other', 'umrah'])->update(['is_active' => true, 'updated_at' => now()]);
        DB::table('acct.industry_coa_packs')->where('code', 'fuel_station')->update(['name' => 'Fuel Station / Petrol Pump']);
        DB::table('acct.industry_coa_packs')->where('code', 'travel')->update(['name' => 'Travel Agency']);
    }

    private function otherTemplates(): array
    {
        return [
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1500', 'name' => 'Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2200', 'name' => 'Accrued Expenses', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '5000', 'name' => 'Cost of Sales', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            ['code' => '6000', 'name' => 'General Expenses', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6100', 'name' => 'Salaries and Wages', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6200', 'name' => 'Rent and Utilities', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6900', 'name' => 'Other Expenses', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit'],
        ];
    }
};
