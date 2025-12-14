<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds global account templates for Chart of Accounts.
 *
 * These templates populate dropdowns when creating company accounts.
 * Codes align with coa-schema.md system accounts specification.
 *
 * Code ranges:
 * - 1xxx: Assets
 * - 2xxx: Liabilities
 * - 3xxx: Equity
 * - 4xxx: Revenue
 * - 5xxx: Cost of Goods Sold
 * - 6xxx: Operating Expenses
 * - 7xxx: Other Income
 * - 8xxx: Other Expenses
 */
class AccountTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $templates = [
            // ═══════════════════════════════════════════════════════════════
            // ASSETS (1xxx) - Normal balance: Debit
            // ═══════════════════════════════════════════════════════════════

            // Bank accounts (1000-1049)
            ['code' => '1000', 'name' => 'Operating Bank Account', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'description' => 'Primary operating bank account'],
            ['code' => '1010', 'name' => 'Payroll Bank Account', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'description' => 'Bank account for payroll disbursements'],
            ['code' => '1020', 'name' => 'Savings Account', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'description' => 'Business savings account'],

            // Cash accounts (1050-1099)
            ['code' => '1050', 'name' => 'Petty Cash', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'description' => 'Petty cash fund'],
            ['code' => '1060', 'name' => 'Cash Drawer', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'description' => 'Cash register drawer'],
            ['code' => '1070', 'name' => 'Undeposited Funds', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'description' => 'Payments received but not yet deposited'],

            // Accounts Receivable (1100-1149) - SYSTEM ACCOUNT per contract
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'description' => 'Use this to track money owed to you by customers for products or services sold on credit.'],
            ['code' => '1110', 'name' => 'Allowance for Doubtful Accounts', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'credit', 'is_contra' => true, 'description' => 'Contra-asset for uncollectible receivables'],

            // Other Current Assets (1150-1299)
            ['code' => '1150', 'name' => 'Employee Advances', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit', 'description' => 'Advances to employees'],
            ['code' => '1160', 'name' => 'Prepaid Expenses', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit', 'description' => 'Prepaid rent, insurance, etc.'],
            ['code' => '1170', 'name' => 'Prepaid Insurance', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit', 'description' => 'Prepaid insurance premiums'],
            ['code' => '1180', 'name' => 'Prepaid Rent', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit', 'description' => 'Prepaid rent deposits'],
            ['code' => '1190', 'name' => 'VAT Receivable', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit', 'description' => 'Input VAT/GST recoverable from purchases'],
            ['code' => '1195', 'name' => 'Tax Receivable', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit', 'description' => 'Other tax receivables and refunds'],

            // Inventory (1300-1399)
            ['code' => '1300', 'name' => 'Inventory', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit', 'description' => 'Value of goods available for sale.'],
            ['code' => '1310', 'name' => 'Raw Materials', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit', 'description' => 'Raw materials for production'],
            ['code' => '1320', 'name' => 'Work in Progress', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit', 'description' => 'Partially completed goods'],
            ['code' => '1330', 'name' => 'Finished Goods', 'type' => 'asset', 'subtype' => 'inventory', 'normal_balance' => 'debit', 'description' => 'Completed goods ready for sale'],

            // Fixed Assets (1500-1699)
            ['code' => '1500', 'name' => 'Furniture & Fixtures', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit', 'description' => 'Long-term assets such as office furniture and fixtures.'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation - Furniture', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true, 'description' => 'Accumulated depreciation on furniture'],
            ['code' => '1520', 'name' => 'Computer Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit', 'description' => 'Computers, servers, and IT equipment'],
            ['code' => '1530', 'name' => 'Accumulated Depreciation - Computer Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true, 'description' => 'Accumulated depreciation on computer equipment'],
            ['code' => '1540', 'name' => 'Vehicles', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit', 'description' => 'Company vehicles'],
            ['code' => '1550', 'name' => 'Accumulated Depreciation - Vehicles', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true, 'description' => 'Accumulated depreciation on vehicles'],
            ['code' => '1560', 'name' => 'Machinery & Equipment', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit', 'description' => 'Production machinery and equipment'],
            ['code' => '1570', 'name' => 'Accumulated Depreciation - Machinery', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true, 'description' => 'Accumulated depreciation on machinery'],
            ['code' => '1580', 'name' => 'Leasehold Improvements', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'debit', 'description' => 'Improvements to leased property'],
            ['code' => '1590', 'name' => 'Accumulated Amortization - Leasehold', 'type' => 'asset', 'subtype' => 'fixed_asset', 'normal_balance' => 'credit', 'is_contra' => true, 'description' => 'Accumulated amortization on leasehold improvements'],

            // Other Assets (1700-1799)
            ['code' => '1700', 'name' => 'Security Deposits', 'type' => 'asset', 'subtype' => 'other_asset', 'normal_balance' => 'debit', 'description' => 'Security deposits paid'],
            ['code' => '1710', 'name' => 'Goodwill', 'type' => 'asset', 'subtype' => 'other_asset', 'normal_balance' => 'debit', 'description' => 'Goodwill from acquisitions'],
            ['code' => '1720', 'name' => 'Intangible Assets', 'type' => 'asset', 'subtype' => 'other_asset', 'normal_balance' => 'debit', 'description' => 'Patents, trademarks, licenses'],

            // System/Clearing Accounts (1900-1999)
            ['code' => '1900', 'name' => 'Suspense Account', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit', 'description' => 'Temporary clearing for unclassified transactions'],
            ['code' => '1910', 'name' => 'Clearing Account', 'type' => 'asset', 'subtype' => 'other_current_asset', 'normal_balance' => 'debit', 'description' => 'Inter-account clearing transactions'],

            // ═══════════════════════════════════════════════════════════════
            // LIABILITIES (2xxx) - Normal balance: Credit
            // ═══════════════════════════════════════════════════════════════

            // Accounts Payable (2100-2149) - SYSTEM ACCOUNT per contract
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'subtype' => 'accounts_payable', 'normal_balance' => 'credit', 'description' => 'Use this to track the balance of what you owe vendors (i.e. suppliers, online subscriptions providers) after you accepted their service or receive items for which you have not yet paid. Bills in Wave are already tracked in the Accounts Payable category.'],

            // Credit Cards (2150-2199)
            ['code' => '2150', 'name' => 'Corporate Credit Card', 'type' => 'liability', 'subtype' => 'credit_card', 'normal_balance' => 'credit', 'description' => 'Outstanding balance on the corporate credit card.'],
            ['code' => '2160', 'name' => 'Business Credit Line', 'type' => 'liability', 'subtype' => 'credit_card', 'normal_balance' => 'credit', 'description' => 'Business line of credit'],

            // Other Current Liabilities (2200-2499)
            ['code' => '2200', 'name' => 'Accrued Expenses', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => 'Accrued but unpaid expenses'],
            ['code' => '2210', 'name' => 'Accrued Salaries & Wages', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => 'Salaries and wages payable'],
            ['code' => '2220', 'name' => 'Payroll Taxes Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => 'The total amount you owe for your payroll. This includes wages due to employees and payroll taxes owed to the government.'],
            ['code' => '2230', 'name' => 'VAT Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => 'Output VAT/GST collected from sales'],
            ['code' => '2240', 'name' => 'Sales Tax Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => 'Sales taxes collected from customers that are due to the government.'],
            ['code' => '2250', 'name' => 'Income Tax Payable', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => 'Corporate income tax liability'],
            ['code' => '2260', 'name' => 'Unearned Revenue', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => 'Customer deposits and prepayments'],
            ['code' => '2270', 'name' => 'Customer Deposits', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => 'Deposits received from customers'],
            ['code' => '2280', 'name' => 'Current Portion of Long-Term Debt', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'description' => 'Loan principal due within one year'],

            // Other Liabilities (2500-2699)
            ['code' => '2500', 'name' => 'Deferred Revenue', 'type' => 'liability', 'subtype' => 'other_liability', 'normal_balance' => 'credit', 'description' => 'Long-term deferred revenue'],
            ['code' => '2510', 'name' => 'Deferred Tax Liability', 'type' => 'liability', 'subtype' => 'other_liability', 'normal_balance' => 'credit', 'description' => 'Deferred income taxes'],

            // Loans Payable (2700-2799)
            ['code' => '2700', 'name' => 'Bank Loan', 'type' => 'liability', 'subtype' => 'loan_payable', 'normal_balance' => 'credit', 'description' => 'Long-term bank loan'],
            ['code' => '2710', 'name' => 'Equipment Loan', 'type' => 'liability', 'subtype' => 'loan_payable', 'normal_balance' => 'credit', 'description' => 'Loan for equipment purchases'],
            ['code' => '2720', 'name' => 'Vehicle Loan', 'type' => 'liability', 'subtype' => 'loan_payable', 'normal_balance' => 'credit', 'description' => 'Loan for vehicle purchases'],
            ['code' => '2730', 'name' => 'Shareholder Loan', 'type' => 'liability', 'subtype' => 'loan_payable', 'normal_balance' => 'credit', 'description' => 'Loans from shareholders'],

            // ═══════════════════════════════════════════════════════════════
            // EQUITY (3xxx) - Normal balance: Credit
            // ═══════════════════════════════════════════════════════════════

            ['code' => '3000', 'name' => 'Owner\'s Capital', 'type' => 'equity', 'subtype' => 'equity', 'normal_balance' => 'credit', 'description' => 'Owner\'s invested capital'],
            ['code' => '3010', 'name' => 'Common Stock', 'type' => 'equity', 'subtype' => 'equity', 'normal_balance' => 'credit', 'description' => 'Issued common stock'],
            ['code' => '3020', 'name' => 'Additional Paid-in Capital', 'type' => 'equity', 'subtype' => 'equity', 'normal_balance' => 'credit', 'description' => 'Capital in excess of par value'],
            ['code' => '3050', 'name' => 'Owner\'s Drawings', 'type' => 'equity', 'subtype' => 'equity', 'normal_balance' => 'debit', 'is_contra' => true, 'description' => 'Owner withdrawals reduce equity'],
            ['code' => '3080', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'subtype' => 'equity', 'normal_balance' => 'credit', 'description' => 'Opening balance adjustments'],

            // Retained Earnings (3100) - SYSTEM ACCOUNT per contract
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => 'equity', 'subtype' => 'retained_earnings', 'normal_balance' => 'credit', 'description' => 'Accumulated retained earnings'],

            // ═══════════════════════════════════════════════════════════════
            // REVENUE (4xxx) - Normal balance: Credit
            // ═══════════════════════════════════════════════════════════════

            // Primary Revenue (4100) - SYSTEM ACCOUNT per contract
            ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'description' => 'Income earned from the sale of goods or services.'],
            ['code' => '4110', 'name' => 'Service Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'description' => 'Revenue from services rendered'],
            ['code' => '4120', 'name' => 'Consulting Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'description' => 'Revenue from consulting services'],
            ['code' => '4130', 'name' => 'Subscription Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'description' => 'Recurring subscription income'],
            ['code' => '4140', 'name' => 'Shipping & Delivery Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'description' => 'Revenue from shipping charges'],
            ['code' => '4150', 'name' => 'Rental Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit', 'description' => 'Revenue from equipment/property rental'],
            ['code' => '4900', 'name' => 'Sales Returns & Allowances', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true, 'description' => 'Returns and allowances reduce revenue'],
            ['code' => '4910', 'name' => 'Sales Discounts', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'debit', 'is_contra' => true, 'description' => 'Early payment discounts reduce revenue'],

            // ═══════════════════════════════════════════════════════════════
            // COST OF GOODS SOLD (5xxx) - Normal balance: Debit
            // ═══════════════════════════════════════════════════════════════

            // COGS (5100) - SYSTEM ACCOUNT per contract
            ['code' => '5100', 'name' => 'Cost of Goods Sold', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'description' => 'Direct costs attributable to the production of the goods sold in a company. This includes the cost of the materials used in creating the good along with the direct labor costs used to produce the good.'],
            ['code' => '5110', 'name' => 'Cost of Services', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'description' => 'Direct cost of services delivered'],
            ['code' => '5120', 'name' => 'Direct Labor', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'description' => 'Direct labor costs in production'],
            ['code' => '5130', 'name' => 'Direct Materials', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'description' => 'Direct materials used in production'],
            ['code' => '5140', 'name' => 'Freight In', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'description' => 'Shipping costs on purchases'],
            ['code' => '5150', 'name' => 'Purchase Discounts', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'credit', 'is_contra' => true, 'description' => 'Supplier discounts reduce cost of goods'],
            ['code' => '5160', 'name' => 'Inventory Shrinkage', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'description' => 'Loss from inventory shrinkage/theft'],
            ['code' => '5170', 'name' => 'Inventory Write-off', 'type' => 'cogs', 'subtype' => 'cogs', 'normal_balance' => 'debit', 'description' => 'Obsolete or damaged inventory write-offs'],

            // ═══════════════════════════════════════════════════════════════
            // OPERATING EXPENSES (6xxx) - Normal balance: Debit
            // ═══════════════════════════════════════════════════════════════

            // General Expense (6100) - SYSTEM ACCOUNT per contract
            ['code' => '6100', 'name' => 'General & Administrative', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Expenses incurred for the general daily operations of the business.'],

            // Payroll & Benefits (6200-6299)
            ['code' => '6200', 'name' => 'Salaries & Wages', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Salaries and wages paid to employees.'],
            ['code' => '6210', 'name' => 'Payroll Taxes', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Employer portion of payroll taxes'],
            ['code' => '6220', 'name' => 'Employee Benefits', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Benefits provided to employees such as health insurance, retirement plans, etc.'],
            ['code' => '6230', 'name' => 'Contract Labor', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Independent contractor payments'],

            // Facilities (6300-6399)
            ['code' => '6300', 'name' => 'Rent Expense', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Office and facility rent'],
            ['code' => '6310', 'name' => 'Utilities', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Electricity, water, gas'],
            ['code' => '6320', 'name' => 'Repairs & Maintenance', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Building and equipment maintenance'],
            ['code' => '6330', 'name' => 'Cleaning & Janitorial', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Cleaning services'],

            // Office & Admin (6400-6499)
            ['code' => '6400', 'name' => 'Office Supplies', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Office supplies and consumables'],
            ['code' => '6410', 'name' => 'Postage & Shipping', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Mailing and shipping costs'],
            ['code' => '6420', 'name' => 'Printing & Reproduction', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Printing and copying costs'],
            ['code' => '6430', 'name' => 'Telephone & Internet', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Communications expenses'],
            ['code' => '6440', 'name' => 'Software & Subscriptions', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Software licenses and SaaS subscriptions'],

            // Professional Services (6500-6599)
            ['code' => '6500', 'name' => 'Accounting & Legal', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Professional accounting and legal fees'],
            ['code' => '6510', 'name' => 'Consulting Fees', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'External consulting services'],
            ['code' => '6520', 'name' => 'Bank Charges & Fees', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Bank service charges and fees'],
            ['code' => '6530', 'name' => 'Payment Processing Fees', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Credit card and payment gateway fees'],

            // Insurance (6600-6649)
            ['code' => '6600', 'name' => 'Insurance - General Liability', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Insurance coverage for general liability claims.'],
            ['code' => '6610', 'name' => 'Insurance - Property', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Property and contents insurance'],
            ['code' => '6620', 'name' => 'Insurance - Vehicle', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Vehicle insurance'],

            // Marketing & Sales (6700-6799)
            ['code' => '6700', 'name' => 'Advertising & Marketing', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Advertising and marketing campaigns'],
            ['code' => '6710', 'name' => 'Trade Shows & Events', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Trade show and event costs'],
            ['code' => '6720', 'name' => 'Client Entertainment', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Client meals and entertainment'],

            // Travel (6800-6849)
            ['code' => '6800', 'name' => 'Travel - Transportation', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Airfare, taxi, rideshare'],
            ['code' => '6810', 'name' => 'Travel - Lodging', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Hotel and accommodation'],
            ['code' => '6820', 'name' => 'Travel - Meals', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Meals while traveling'],
            ['code' => '6830', 'name' => 'Vehicle Expense', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Fuel, parking, tolls'],

            // Depreciation & Amortization (6900-6949)
            ['code' => '6900', 'name' => 'Depreciation Expense', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Depreciation of fixed assets'],
            ['code' => '6910', 'name' => 'Amortization Expense', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Amortization of intangible assets'],

            // Other Operating (6950-6999)
            ['code' => '6950', 'name' => 'Bad Debt Expense', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Uncollectible accounts written off'],
            ['code' => '6960', 'name' => 'Training & Education', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Employee training and development'],
            ['code' => '6970', 'name' => 'Licenses & Permits', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Business licenses and permits'],
            ['code' => '6980', 'name' => 'Dues & Memberships', 'type' => 'expense', 'subtype' => 'expense', 'normal_balance' => 'debit', 'description' => 'Professional memberships'],

            // ═══════════════════════════════════════════════════════════════
            // OTHER INCOME (7xxx) - Normal balance: Credit
            // ═══════════════════════════════════════════════════════════════

            ['code' => '7000', 'name' => 'Interest Income', 'type' => 'other_income', 'subtype' => 'other_income', 'normal_balance' => 'credit', 'description' => 'Interest earned on bank accounts'],
            ['code' => '7010', 'name' => 'Dividend Income', 'type' => 'other_income', 'subtype' => 'other_income', 'normal_balance' => 'credit', 'description' => 'Dividend income from investments'],
            ['code' => '7020', 'name' => 'Gain on Sale of Assets', 'type' => 'other_income', 'subtype' => 'other_income', 'normal_balance' => 'credit', 'description' => 'Gain on disposal of fixed assets'],
            ['code' => '7030', 'name' => 'Foreign Exchange Gain', 'type' => 'other_income', 'subtype' => 'other_income', 'normal_balance' => 'credit', 'description' => 'Realized and unrealized FX gains'],
            ['code' => '7090', 'name' => 'Other Income', 'type' => 'other_income', 'subtype' => 'other_income', 'normal_balance' => 'credit', 'description' => 'Miscellaneous other income'],

            // ═══════════════════════════════════════════════════════════════
            // OTHER EXPENSES (8xxx) - Normal balance: Debit
            // ═══════════════════════════════════════════════════════════════

            ['code' => '8000', 'name' => 'Interest Expense', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'description' => 'Interest on loans and credit'],
            ['code' => '8010', 'name' => 'Loss on Sale of Assets', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'description' => 'Loss on disposal of fixed assets'],
            ['code' => '8020', 'name' => 'Foreign Exchange Loss', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'description' => 'Realized and unrealized FX losses'],
            ['code' => '8025', 'name' => 'FX Rounding', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'description' => 'Foreign exchange rounding differences'],
            ['code' => '8030', 'name' => 'Penalties & Fines', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'description' => 'Late payment penalties and fines'],
            ['code' => '8040', 'name' => 'Income Tax Expense', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'description' => 'Corporate income tax expense'],
            ['code' => '8050', 'name' => 'Cash Short & Over', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'description' => 'Cash register/till discrepancies (debit=short, credit=over)'],
            ['code' => '8090', 'name' => 'Other Expense', 'type' => 'other_expense', 'subtype' => 'other_expense', 'normal_balance' => 'debit', 'description' => 'Miscellaneous other expenses'],
        ];

        foreach ($templates as $template) {
            DB::table('acct.account_templates')->updateOrInsert(
                ['code' => $template['code']],
                array_merge([
                    'is_contra' => false, // default, can be overridden by template
                ], $template, [
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}
