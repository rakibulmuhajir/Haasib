<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first company for testing
        $company = DB::table('auth.companies')->first();

        if (! $company) {
            $this->command->warn('No company found. Please run company seeder first.');

            return;
        }

        $this->command->info('Seeding chart of accounts for company: '.$company->name);

        // Standard Chart of Accounts structure
        $accounts = [
            // ASSETS
            ['account_number' => '1000', 'account_name' => 'Cash and Cash Equivalents', 'account_type' => 'Asset', 'account_category' => 'Current Assets', 'opening_balance' => 50000],
            ['account_number' => '1010', 'account_name' => 'Petty Cash', 'account_type' => 'Asset', 'account_category' => 'Current Assets', 'opening_balance' => 1000],
            ['account_number' => '1100', 'account_name' => 'Accounts Receivable', 'account_type' => 'Asset', 'account_category' => 'Current Assets', 'opening_balance' => 25000],
            ['account_number' => '1110', 'account_name' => 'Allowance for Doubtful Accounts', 'account_type' => 'Asset', 'account_category' => 'Current Assets', 'opening_balance' => 500],
            ['account_number' => '1200', 'account_name' => 'Inventory', 'account_type' => 'Asset', 'account_category' => 'Current Assets', 'opening_balance' => 35000],
            ['account_number' => '1300', 'account_name' => 'Prepaid Expenses', 'account_type' => 'Asset', 'account_category' => 'Current Assets', 'opening_balance' => 5000],
            ['account_number' => '1500', 'account_name' => 'Equipment', 'account_type' => 'Asset', 'account_category' => 'Fixed Assets', 'opening_balance' => 100000],
            ['account_number' => '1510', 'account_name' => 'Accumulated Depreciation - Equipment', 'account_type' => 'Asset', 'account_category' => 'Fixed Assets', 'opening_balance' => 25000],
            ['account_number' => '1600', 'account_name' => 'Buildings', 'account_type' => 'Asset', 'account_category' => 'Fixed Assets', 'opening_balance' => 500000],
            ['account_number' => '1610', 'account_name' => 'Accumulated Depreciation - Buildings', 'account_type' => 'Asset', 'account_category' => 'Fixed Assets', 'opening_balance' => 100000],

            // LIABILITIES
            ['account_number' => '2000', 'account_name' => 'Accounts Payable', 'account_type' => 'Liability', 'account_category' => 'Current Liabilities', 'opening_balance' => 15000],
            ['account_number' => '2100', 'account_name' => 'Accrued Expenses', 'account_type' => 'Liability', 'account_category' => 'Current Liabilities', 'opening_balance' => 8000],
            ['account_number' => '2200', 'account_name' => 'Taxes Payable', 'account_type' => 'Liability', 'account_category' => 'Current Liabilities', 'opening_balance' => 5000],
            ['account_number' => '2300', 'account_name' => 'Short-term Loans', 'account_type' => 'Liability', 'account_category' => 'Current Liabilities', 'opening_balance' => 10000],
            ['account_number' => '2500', 'account_name' => 'Long-term Loans', 'account_type' => 'Liability', 'account_category' => 'Long-term Liabilities', 'opening_balance' => 150000],
            ['account_number' => '2600', 'account_name' => 'Mortgage Payable', 'account_type' => 'Liability', 'account_category' => 'Long-term Liabilities', 'opening_balance' => 200000],

            // EQUITY
            ['account_number' => '3000', 'account_name' => 'Capital Stock', 'account_type' => 'Equity', 'account_category' => 'Equity', 'opening_balance' => 100000],
            ['account_number' => '3100', 'account_name' => 'Additional Paid-in Capital', 'account_type' => 'Equity', 'account_category' => 'Equity', 'opening_balance' => 50000],
            ['account_number' => '3200', 'account_name' => 'Retained Earnings', 'account_type' => 'Equity', 'account_category' => 'Equity', 'opening_balance' => 25000],
            ['account_number' => '3300', 'account_name' => 'Dividends Paid', 'account_type' => 'Equity', 'account_category' => 'Equity', 'opening_balance' => 0],

            // REVENUE
            ['account_number' => '4000', 'account_name' => 'Sales Revenue', 'account_type' => 'Revenue', 'account_category' => 'Operating Revenue', 'opening_balance' => 0],
            ['account_number' => '4100', 'account_name' => 'Service Revenue', 'account_type' => 'Revenue', 'account_category' => 'Operating Revenue', 'opening_balance' => 0],
            ['account_number' => '4200', 'account_name' => 'Interest Income', 'account_type' => 'Revenue', 'account_category' => 'Non-operating Revenue', 'opening_balance' => 0],
            ['account_number' => '4300', 'account_name' => 'Other Income', 'account_type' => 'Revenue', 'account_category' => 'Non-operating Revenue', 'opening_balance' => 0],

            // EXPENSES
            ['account_number' => '5000', 'account_name' => 'Cost of Goods Sold', 'account_type' => 'Expense', 'account_category' => 'Cost of Sales', 'opening_balance' => 0],
            ['account_number' => '5100', 'account_name' => 'Salaries and Wages', 'account_type' => 'Expense', 'account_category' => 'Operating Expenses', 'opening_balance' => 0],
            ['account_number' => '5110', 'account_name' => 'Employee Benefits', 'account_type' => 'Expense', 'account_category' => 'Operating Expenses', 'opening_balance' => 0],
            ['account_number' => '5200', 'account_name' => 'Rent Expense', 'account_type' => 'Expense', 'account_category' => 'Operating Expenses', 'opening_balance' => 0],
            ['account_number' => '5210', 'account_name' => 'Utilities Expense', 'account_type' => 'Expense', 'account_category' => 'Operating Expenses', 'opening_balance' => 0],
            ['account_number' => '5220', 'account_name' => 'Insurance Expense', 'account_type' => 'Expense', 'account_category' => 'Operating Expenses', 'opening_balance' => 0],
            ['account_number' => '5300', 'account_name' => 'Depreciation Expense', 'account_type' => 'Expense', 'account_category' => 'Operating Expenses', 'opening_balance' => 0],
            ['account_number' => '5400', 'account_name' => 'Interest Expense', 'account_type' => 'Expense', 'account_category' => 'Financial Expenses', 'opening_balance' => 0],
            ['account_number' => '5500', 'account_name' => 'Tax Expense', 'account_type' => 'Expense', 'account_category' => 'Financial Expenses', 'opening_balance' => 0],
            ['account_number' => '5600', 'account_name' => 'Other Expenses', 'account_type' => 'Expense', 'account_category' => 'Non-operating Expenses', 'opening_balance' => 0],
        ];

        foreach ($accounts as $accountData) {
            // Bypass RLS policies for seeding
            DB::statement("SET app.current_company_id = '{$company->id}'");
            DB::statement('SET app.is_super_admin = true');

            DB::table('acct.chart_of_accounts')->insert([
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'account_number' => $accountData['account_number'],
                'account_name' => $accountData['account_name'],
                'account_type' => $accountData['account_type'],
                'account_category' => $accountData['account_category'],
                'is_active' => true,
                'description' => "Standard {$accountData['account_type']} account for {$accountData['account_category']}",
                'opening_balance' => $accountData['opening_balance'],
                'opening_balance_date' => now()->startOfYear(),
                'metadata' => json_encode([
                    'created_by' => 'seeder',
                    'is_standard_account' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Reset RLS settings
            DB::statement('RESET app.current_company_id');
            DB::statement('RESET app.is_super_admin');
        }

        $this->command->info('Successfully seeded '.count($accounts).' chart of accounts entries.');
    }
}
