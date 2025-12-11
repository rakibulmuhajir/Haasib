<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds industry-specific Chart of Accounts packs.
 *
 * Each industry has a tailored COA structure based on its business model.
 * These templates are instantiated into company-specific accounts during onboarding.
 */
class IndustryCoaPackSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Define all 14 industries
        $industries = [
            ['code' => 'accountant', 'name' => 'Accountant / CPA Firm', 'sort_order' => 1],
            ['code' => 'architect', 'name' => 'Architect / Design Firm', 'sort_order' => 2],
            ['code' => 'consultant', 'name' => 'Consultant / Agency', 'sort_order' => 3],
            ['code' => 'farming', 'name' => 'Farming / Agriculture', 'sort_order' => 4],
            ['code' => 'financial_advisor', 'name' => 'Financial Advisors / Stock Brokers', 'sort_order' => 5],
            ['code' => 'healthcare', 'name' => 'Healthcare / General', 'sort_order' => 6],
            ['code' => 'insurance', 'name' => 'Insurance Agency', 'sort_order' => 7],
            ['code' => 'law_firm', 'name' => 'Law Firm', 'sort_order' => 8],
            ['code' => 'manufacturing', 'name' => 'Manufacturing', 'sort_order' => 9],
            ['code' => 'nonprofit', 'name' => 'Non-Profit', 'sort_order' => 10],
            ['code' => 'real_estate', 'name' => 'Real Estate (Agency + Developer)', 'sort_order' => 11],
            ['code' => 'restaurant', 'name' => 'Restaurant', 'sort_order' => 12],
            ['code' => 'retail', 'name' => 'Retail', 'sort_order' => 13],
            ['code' => 'wholesale', 'name' => 'Wholesale / Distribution', 'sort_order' => 14],
        ];

        foreach ($industries as $industry) {
            DB::table('acct.industry_coa_packs')->updateOrInsert(
                ['code' => $industry['code']],
                array_merge($industry, [
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        // Get industry IDs for template creation
        $industryIds = DB::table('acct.industry_coa_packs')
            ->pluck('id', 'code')
            ->toArray();

        // Industry-specific account templates
        $this->seedAccountant($industryIds['accountant'], $now);
        $this->seedArchitect($industryIds['architect'], $now);
        $this->seedConsultant($industryIds['consultant'], $now);
        $this->seedFarming($industryIds['farming'], $now);
        $this->seedFinancialAdvisor($industryIds['financial_advisor'], $now);
        $this->seedHealthcare($industryIds['healthcare'], $now);
        $this->seedInsurance($industryIds['insurance'], $now);
        $this->seedLawFirm($industryIds['law_firm'], $now);
        $this->seedManufacturing($industryIds['manufacturing'], $now);
        $this->seedNonProfit($industryIds['nonprofit'], $now);
        $this->seedRealEstate($industryIds['real_estate'], $now);
        $this->seedRestaurant($industryIds['restaurant'], $now);
        $this->seedRetail($industryIds['retail'], $now);
        $this->seedWholesale($industryIds['wholesale'], $now);
    }

    private function seedAccountant(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1010', 'name' => 'Client Trust Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'description' => 'Client funds held in trust'],
            ['code' => '1100', 'name' => 'Accounts Receivable – Services', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1200', 'name' => 'Work-in-Progress (Unbilled Time)', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
            ['code' => '1160', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Office Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation – Office Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],

            // Liabilities
            ['code' => '2050', 'name' => 'Client Trust Liability', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => 'Client funds held in trust'],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2210', 'name' => 'Accrued Payroll', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2220', 'name' => 'Payroll Taxes Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2260', 'name' => 'Unearned Revenue – Retainers', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Professional Services Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Retainer Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4120', 'name' => 'Consulting Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6200', 'name' => 'Staff Salaries', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6250', 'name' => 'Partner Drawings', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6440', 'name' => 'Software & Subscriptions', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6300', 'name' => 'Rent', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6400', 'name' => 'Office Supplies', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6800', 'name' => 'Travel', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6960', 'name' => 'Continuing Education', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6970', 'name' => 'Professional Licenses', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedArchitect(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1200', 'name' => 'Work-in-Progress', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
            ['code' => '1210', 'name' => 'Reimbursable Expenses Receivable', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Software & Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation – Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2260', 'name' => 'Project Deposits (Unearned Revenue)', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2200', 'name' => 'Accrued Project Costs', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Design Fees', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Project Management Fees', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4120', 'name' => 'Reimbursable Income', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6230', 'name' => 'Subcontractor Fees', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6440', 'name' => 'CAD/Software', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6420', 'name' => 'Printing & Plotting', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6800', 'name' => 'Travel', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6300', 'name' => 'Office Rent', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedConsultant(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1160', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
            ['code' => '1520', 'name' => 'Laptop/Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1530', 'name' => 'Accumulated Depreciation – Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2260', 'name' => 'Unearned Retainer Revenue', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2220', 'name' => 'Payroll Liabilities', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Consulting Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Retainer Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4120', 'name' => 'Training Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6700', 'name' => 'Advertising', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6440', 'name' => 'Software Tools', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6800', 'name' => 'Travel', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6200', 'name' => 'Salaries', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6230', 'name' => 'Contractor Payments', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedFarming(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1300', 'name' => 'Seeds Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1310', 'name' => 'Fertilizer Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1350', 'name' => 'Livestock', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1320', 'name' => 'Crops in Progress', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1560', 'name' => 'Equipment & Machinery', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1570', 'name' => 'Accumulated Depreciation – Machinery', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],
            ['code' => '1600', 'name' => 'Land', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2700', 'name' => 'Farm Loans', 'type' => 'liability', 'subtype' => 'loan_payable', 'normal_balance' => 'credit'],
            ['code' => '2750', 'name' => 'Lease Liabilities', 'type' => 'liability', 'subtype' => 'other_liability', 'normal_balance' => 'credit'],
            ['code' => '2220', 'name' => 'Payroll Taxes', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Crop Sales', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Livestock Sales', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4120', 'name' => 'Government Subsidies', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // COGS
            ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'cogs'],
            ['code' => '5110', 'name' => 'Feed Cost', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6150', 'name' => 'Fertilizer & Chemicals', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6200', 'name' => 'Labor', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6320', 'name' => 'Machinery Repairs', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6350', 'name' => 'Irrigation Costs', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedFinancialAdvisor(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1010', 'name' => 'Client Escrow/Trust Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'AR – Advisory Fees', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2050', 'name' => 'Client Funds Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2200', 'name' => 'Accrued Advisory Fees', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Advisory Fees', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Commissions', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4120', 'name' => 'Portfolio Management Fees', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6970', 'name' => 'Licensing & Compliance', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6450', 'name' => 'Market Data Feeds', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6300', 'name' => 'Office Rent', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6200', 'name' => 'Staff Compensation', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedHealthcare(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'AR – Patients', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1110', 'name' => 'AR – Insurance', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit'],
            ['code' => '1300', 'name' => 'Medical Supplies Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Medical Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation – Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2300', 'name' => 'Insurance Claims Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2210', 'name' => 'Accrued Medical Payroll', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Consultation Fees', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Diagnostic Income', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4120', 'name' => 'Procedure Income', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // COGS
            ['code' => '5100', 'name' => 'Medical Supplies', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'cogs'],
            ['code' => '5110', 'name' => 'Pharmaceuticals', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6200', 'name' => 'Staff Wages', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6320', 'name' => 'Equipment Maintenance', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedInsurance(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1010', 'name' => 'Client Premium Trust Account', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Commission Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2050', 'name' => 'Premiums Collected for Insurers', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2260', 'name' => 'Unearned Commissions', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Commission Income', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Policy Fees', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6700', 'name' => 'Advertising', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6970', 'name' => 'Licensing Fees', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6200', 'name' => 'Staff Compensation', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedLawFirm(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1010', 'name' => 'Client Trust Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'AR – Legal Fees', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1200', 'name' => 'Work-in-Progress', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],
            ['code' => '1210', 'name' => 'Retainer Receivable', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],

            // Liabilities
            ['code' => '2050', 'name' => 'Trust Liability', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => '1:1 with trust bank'],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2260', 'name' => 'Unearned Retainers', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2220', 'name' => 'Payroll Liabilities', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Legal Fees', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Consultation Fees', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6500', 'name' => 'Court Filing Fees', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6200', 'name' => 'Salaries', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6450', 'name' => 'Research Databases', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6800', 'name' => 'Travel', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedManufacturing(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1310', 'name' => 'Raw Materials Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1320', 'name' => 'Work-in-Process', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1330', 'name' => 'Finished Goods', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Factory Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation – Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],
            ['code' => '1160', 'name' => 'Prepaid Manufacturing Overhead', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit'],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2700', 'name' => 'Factory Loans', 'type' => 'liability', 'subtype' => 'loan_payable', 'normal_balance' => 'credit'],
            ['code' => '2210', 'name' => 'Accrued Wages', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Product Sales', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],

            // COGS
            ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'cogs'],
            ['code' => '5130', 'name' => 'Direct Materials Used', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            ['code' => '5120', 'name' => 'Direct Labor', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            ['code' => '5110', 'name' => 'Manufacturing Overhead', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            ['code' => '5140', 'name' => 'Freight In', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6300', 'name' => 'Factory Rent', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6310', 'name' => 'Utilities', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6900', 'name' => 'R&D', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedNonProfit(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Cash – Unrestricted', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1010', 'name' => 'Cash – Restricted', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Grants Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1500', 'name' => 'Fixed Assets', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2500', 'name' => 'Deferred Grants', 'type' => 'liability', 'subtype' => 'other_liability', 'normal_balance' => 'credit'],
            ['code' => '2300', 'name' => 'Funds Held for Programs', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity (Net Assets for non-profits)
            ['code' => '3100', 'name' => 'Net Assets – Unrestricted', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],
            ['code' => '3110', 'name' => 'Net Assets – Restricted', 'type' => 'equity', 'subtype' => 'equity', 'normal_balance' => 'credit'],

            // Revenue
            ['code' => '4100', 'name' => 'Donations', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Grants', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4120', 'name' => 'Membership Fees', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // Expenses
            ['code' => '6100', 'name' => 'Program Costs', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6700', 'name' => 'Fundraising', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6200', 'name' => 'Admin Salaries', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedRealEstate(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1020', 'name' => 'Escrow Funds', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1400', 'name' => 'Land Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1410', 'name' => 'Buildings Held for Sale', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Buildings Held for Investment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation – Buildings', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],
            ['code' => '1420', 'name' => 'Construction-in-Progress', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2700', 'name' => 'Mortgage Payable', 'type' => 'liability', 'subtype' => 'loan_payable', 'normal_balance' => 'credit'],
            ['code' => '2270', 'name' => 'Customer Deposits', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2750', 'name' => 'Investor Financing', 'type' => 'liability', 'subtype' => 'other_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Rental Income', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Property Sales', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4120', 'name' => 'Commission Income', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4130', 'name' => 'CAM Reimbursement', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // COGS
            ['code' => '5100', 'name' => 'Construction Costs', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'cogs'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6350', 'name' => 'Property Tax', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6320', 'name' => 'Repairs & Maintenance', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6900', 'name' => 'Depreciation – Buildings', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedRestaurant(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1060', 'name' => 'POS Cash Drawer', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1300', 'name' => 'Food Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1310', 'name' => 'Beverage Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Kitchen Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation – Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2240', 'name' => 'Sales Tax Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2280', 'name' => 'Tips Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2290', 'name' => 'Gift Cards Liability', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Food Sales', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Beverage Sales', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4140', 'name' => 'Delivery Charges', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // COGS
            ['code' => '5100', 'name' => 'Food Cost', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'cogs'],
            ['code' => '5110', 'name' => 'Beverage Cost', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            ['code' => '5120', 'name' => 'Kitchen Supplies', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6200', 'name' => 'Staff Wages', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6300', 'name' => 'Rent', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6310', 'name' => 'Utilities', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6700', 'name' => 'Advertising', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedRetail(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1030', 'name' => 'Gateway Clearing (Stripe/PayPal)', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1300', 'name' => 'Merchandise Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1350', 'name' => 'Inventory in Transit', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Store Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation – Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],

            // Liabilities
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2290', 'name' => 'Gift Cards Liability', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2295', 'name' => 'Store Credit Liability', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2240', 'name' => 'Sales Tax Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Retail Sales', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4140', 'name' => 'Shipping Income', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // COGS
            ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'cogs'],
            ['code' => '5140', 'name' => 'Freight-In', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            ['code' => '5160', 'name' => 'Shrinkage', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6300', 'name' => 'Store Rent', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6530', 'name' => 'POS Fees', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6200', 'name' => 'Staff Wages', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function seedWholesale(string $industryId, $now): void
    {
        $accounts = [
            // Assets
            ['code' => '1000', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit'],
            ['code' => '1100', 'name' => 'AR – Trade Customers', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'ar_control'],
            ['code' => '1300', 'name' => 'Bulk Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit'],
            ['code' => '1500', 'name' => 'Warehouse Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation – Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true],

            // Liabilities
            ['code' => '2100', 'name' => 'AP – Suppliers', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'ap_control'],
            ['code' => '2200', 'name' => 'Freight Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],
            ['code' => '2240', 'name' => 'Sales Tax Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit'],

            // Equity
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'retained_earnings'],

            // Revenue
            ['code' => '4100', 'name' => 'Wholesale Sales', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'is_system' => true, 'system_identifier' => 'primary_revenue'],
            ['code' => '4110', 'name' => 'Distribution Fees', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit'],

            // COGS
            ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'cogs'],
            ['code' => '5110', 'name' => 'Bulk Purchases', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            ['code' => '5140', 'name' => 'Freight-In', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],
            ['code' => '5150', 'name' => 'Warehouse Handling', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit'],

            // Expenses
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'is_system' => true, 'system_identifier' => 'primary_expense'],
            ['code' => '6300', 'name' => 'Warehouse Rent', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '6350', 'name' => 'Logistics Costs', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit'],
        ];

        $this->insertAccounts($industryId, $accounts, $now);
    }

    private function insertAccounts(string $industryId, array $accounts, $now): void
    {
        foreach ($accounts as $index => $account) {
            DB::table('acct.industry_coa_templates')->updateOrInsert(
                [
                    'industry_pack_id' => $industryId,
                    'code' => $account['code']
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
}
