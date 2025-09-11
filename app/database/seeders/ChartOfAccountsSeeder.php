<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all companies or create a default one if none exist
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $companies = collect([Company::create([
                'id' => Str::uuid(),
                'name' => 'Default Company',
                'slug' => 'default-company',
                'active' => true,
            ])]);
        }

        foreach ($companies as $company) {
            $this->createChartOfAccountsForCompany($company);
        }
    }

    private function createChartOfAccountsForCompany(Company $company): void
    {
        $accounts = [
            // Assets (1000-1999)
            ['code' => '1000', 'name' => 'Current Assets', 'type' => 'asset', 'normal_balance' => 'debit', 'system_account' => true, 'children' => [
                ['code' => '1100', 'name' => 'Cash', 'type' => 'asset', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '1300', 'name' => 'Inventory', 'type' => 'asset', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '1400', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'normal_balance' => 'debit', 'system_account' => true],
            ]],

            ['code' => '1500', 'name' => 'Fixed Assets', 'type' => 'asset', 'normal_balance' => 'debit', 'system_account' => true, 'children' => [
                ['code' => '1510', 'name' => 'Property, Plant & Equipment', 'type' => 'asset', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '1520', 'name' => 'Accumulated Depreciation', 'type' => 'asset', 'normal_balance' => 'credit', 'system_account' => true],
                ['code' => '1530', 'name' => 'Vehicles', 'type' => 'asset', 'normal_balance' => 'debit', 'system_account' => false],
            ]],

            // Liabilities (2000-2999)
            ['code' => '2000', 'name' => 'Current Liabilities', 'type' => 'liability', 'normal_balance' => 'credit', 'system_account' => true, 'children' => [
                ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit', 'system_account' => true],
                ['code' => '2200', 'name' => 'Short-term Loans', 'type' => 'liability', 'normal_balance' => 'credit', 'system_account' => true],
                ['code' => '2300', 'name' => 'Accrued Expenses', 'type' => 'liability', 'normal_balance' => 'credit', 'system_account' => true],
                ['code' => '2400', 'name' => 'Unearned Revenue', 'type' => 'liability', 'normal_balance' => 'credit', 'system_account' => true],
            ]],

            ['code' => '2500', 'name' => 'Long-term Liabilities', 'type' => 'liability', 'normal_balance' => 'credit', 'system_account' => true, 'children' => [
                ['code' => '2510', 'name' => 'Long-term Loans', 'type' => 'liability', 'normal_balance' => 'credit', 'system_account' => true],
                ['code' => '2520', 'name' => 'Bonds Payable', 'type' => 'liability', 'normal_balance' => 'credit', 'system_account' => true],
            ]],

            // Equity (3000-3999)
            ['code' => '3000', 'name' => 'Equity', 'type' => 'equity', 'normal_balance' => 'credit', 'system_account' => true, 'children' => [
                ['code' => '3100', 'name' => 'Share Capital', 'type' => 'equity', 'normal_balance' => 'credit', 'system_account' => true],
                ['code' => '3200', 'name' => 'Retained Earnings', 'type' => 'equity', 'normal_balance' => 'credit', 'system_account' => true],
                ['code' => '3300', 'name' => 'Dividends', 'type' => 'equity', 'normal_balance' => 'debit', 'system_account' => true],
            ]],

            // Revenue (4000-4999)
            ['code' => '4000', 'name' => 'Revenue', 'type' => 'revenue', 'normal_balance' => 'credit', 'system_account' => true, 'children' => [
                ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'normal_balance' => 'credit', 'system_account' => true],
                ['code' => '4200', 'name' => 'Service Revenue', 'type' => 'revenue', 'normal_balance' => 'credit', 'system_account' => true],
                ['code' => '4300', 'name' => 'Other Income', 'type' => 'revenue', 'normal_balance' => 'credit', 'system_account' => true],
            ]],

            // Expenses (5000-5999)
            ['code' => '5000', 'name' => 'Operating Expenses', 'type' => 'expense', 'normal_balance' => 'debit', 'system_account' => true, 'children' => [
                ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '5200', 'name' => 'Salaries and Wages', 'type' => 'expense', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '5300', 'name' => 'Rent Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '5400', 'name' => 'Utilities Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '5500', 'name' => 'Marketing Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '5600', 'name' => 'Office Supplies', 'type' => 'expense', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '5700', 'name' => 'Depreciation Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '5800', 'name' => 'Interest Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'system_account' => true],
                ['code' => '5900', 'name' => 'Tax Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'system_account' => true],
            ]],
        ];

        foreach ($accounts as $accountData) {
            $this->createAccountWithChildren($company, $accountData);
        }
    }

    private function createAccountWithChildren(Company $company, array $accountData, ?LedgerAccount $parent = null): LedgerAccount
    {
        $children = $accountData['children'] ?? [];
        unset($accountData['children']);

        $accountData['company_id'] = $company->id;
        $accountData['parent_id'] = $parent?->id;
        $accountData['level'] = $parent ? $parent->level + 1 : 1;
        $accountData['active'] = true;

        $account = LedgerAccount::create($accountData);

        foreach ($children as $childData) {
            $this->createAccountWithChildren($company, $childData, $account);
        }

        return $account;
    }
}
