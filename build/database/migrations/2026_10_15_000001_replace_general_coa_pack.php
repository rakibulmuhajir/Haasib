<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces the thin `other` COA pack with a full small-business chart, named
 * "General business". Codes line up with what core looks up (opening balances,
 * payroll, the discount resolver, the default-account provisioner) so no core
 * lookup creates a stray account.
 *
 * Templates only: acct.industry_coa_templates and the pack name. Companies that
 * already exist keep the accounts they were created with; bringing one up to
 * date is a deliberate RepairChartOfAccounts run, never this migration.
 *
 * deploy.sh runs migrate but never db:seed, which is why this is a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replace($this->general(), 'General business');
    }

    public function down(): void
    {
        $this->replace($this->previous(), 'Other');
    }

    private function replace(array $accounts, string $packName): void
    {
        $now = now();

        DB::transaction(function () use ($accounts, $packName, $now) {
            $packId = DB::table('acct.industry_coa_packs')->where('code', 'other')->value('id');

            if (! $packId) {
                return; // pack never seeded here; the seeder creates it with the right rows
            }

            DB::table('acct.industry_coa_templates')->where('industry_pack_id', $packId)->delete();

            foreach (array_values($accounts) as $index => $account) {
                DB::table('acct.industry_coa_templates')->insert(array_merge([
                    'is_contra' => false,
                    'is_system' => false,
                    'system_identifier' => null,
                    'description' => null,
                ], $account, [
                    'industry_pack_id' => $packId,
                    'sort_order' => $index * 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }

            DB::table('acct.industry_coa_packs')->where('id', $packId)->update([
                'name' => $packName,
                'updated_at' => $now,
            ]);
        });
    }

    private function general(): array
    {
        return [
            ['code' => '1000', 'name' => 'Bank Account', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit'],
            ['code' => '1070', 'name' => 'Undeposited Funds', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1150', 'name' => 'Employee Advances', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
            ['code' => '1170', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
            ['code' => '1190', 'name' => 'Input Tax Recoverable', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
            ['code' => '1300', 'name' => 'Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Furniture & Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation – Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],
            ['code' => '1520', 'name' => 'Vehicles', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1530', 'name' => 'Accumulated Depreciation – Vehicles', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2150', 'name' => 'Accrued Expenses', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2210', 'name' => 'Partner Deposits', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2211', 'name' => 'Salary Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2221', 'name' => 'Payroll Deductions Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2300', 'name' => 'Sales Tax Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2500', 'name' => 'Loans Payable', 'type' => 'liability', 'subtype' => 'loan_payable', 'normal_balance' => 'credit'],
            ['code' => '3000', 'name' => 'Owner\'s Capital', 'type' => 'equity', 'subtype' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '3050', 'name' => 'Owner\'s Drawings', 'type' => 'equity', 'subtype' => 'equity', 'normal_balance' => 'debit'],
            ['code' => '3080', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'subtype' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],
            ['code' => '4000', 'name' => 'Sales', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4100', 'name' => 'Service Income', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4210', 'name' => 'Sales Discounts', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true],
            ['code' => '4900', 'name' => 'Other Income', 'type' => 'other_income', 'subtype' => 'other_income', 'normal_balance' => 'credit'],
            ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_cogs'],
            ['code' => '6000', 'name' => 'General Expenses', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6100', 'name' => 'Rent', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6110', 'name' => 'Utilities', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6200', 'name' => 'Salaries & Wages', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6210', 'name' => 'Payroll Taxes', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6220', 'name' => 'Employee Benefits', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6300', 'name' => 'Office Supplies', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6400', 'name' => 'Bank Charges', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6500', 'name' => 'Depreciation', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6600', 'name' => 'Professional Fees', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6700', 'name' => 'Repairs & Maintenance', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6800', 'name' => 'Transport', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6850', 'name' => 'Telephone & Internet', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6900', 'name' => 'Other Expenses', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit'],
            ['code' => '7050', 'name' => 'Inventory Gain', 'type' => 'other_income', 'subtype' => 'other_income', 'normal_balance' => 'credit'],
            ['code' => '8060', 'name' => 'Inventory Shrinkage', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit'],
        ];
    }

    private function previous(): array
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
