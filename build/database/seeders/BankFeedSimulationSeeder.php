<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bank;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\BankTransaction;
use App\Modules\Accounting\Models\Customer; // Added
use App\Modules\Accounting\Models\InvoiceLineItem; // Added

class BankFeedSimulationSeeder extends Seeder
{
    public function run(): void
    {
        // Get the first company (or the one from context if running via artisan context)
        $company = Company::first();
        if (!$company) {
            $this->command->error("No company found.");
            return;
        }

        $this->command->info("Seeding Bank Feed for company: {$company->name}");

        // Ensure a GL bank account exists for the company
        $glBankAccount = Account::firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => '1010',
            ],
            [
                'name' => 'Checking Account',
                'type' => 'asset',
                'subtype' => 'bank',
                'normal_balance' => 'debit',
                'currency' => 'USD', // Assuming USD as default for now
                'is_system' => true,
            ]
        );
        $this->command->info("Ensured GL Bank Account '{$glBankAccount->name}' exists.");

        // Ensure a Bank exists (e.g., Chase)
        $bank = Bank::firstOrCreate(
            ['name' => 'Chase Bank'],
            ['swift_code' => 'CHASUS33'] // Example SWIFT
        );
        $this->command->info("Ensured Bank '{$bank->name}' exists.");

        // Ensure a Bank Account exists for the company, linked to the GL account
        $bankAccount = BankAccount::firstOrCreate(
            [
                'company_id' => $company->id,
                'account_name' => 'Chase Checking',
            ],
            [
                'bank_id' => $bank->id,
                'gl_account_id' => $glBankAccount->id,
                'account_number' => '****1234', // Masked example
                'account_type' => 'checking',
                'currency' => 'USD',
                'opening_balance' => 0.00,
                'current_balance' => 0.00, // Will be updated by transactions
                'is_primary' => true,
                'is_active' => true,
            ]
        );
        $this->command->info("Ensured Company Bank Account '{$bankAccount->account_name}' exists.");

        // Ensure a Fiscal Year exists
        $fiscalYear = FiscalYear::where('company_id', $company->id)
            ->where('start_date', now()->startOfYear())
            ->first();

        if (!$fiscalYear) {
            $fiscalYear = FiscalYear::create([
                'company_id' => $company->id,
                'name' => 'FY ' . now()->year,
                'start_date' => now()->startOfYear(),
                'end_date' => now()->endOfYear(),
                'status' => 'open',
            ]);
            $this->command->info("Created Fiscal Year '{$fiscalYear->name}'.");
        } else {
            $this->command->info("Ensured Fiscal Year '{$fiscalYear->name}' exists.");
        }

        // Ensure an Accounting Period exists
        $accountingPeriod = AccountingPeriod::where('company_id', $company->id)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->where('start_date', now()->startOfMonth())
            ->first();

        if (!$accountingPeriod) {
            $accountingPeriod = AccountingPeriod::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $fiscalYear->id,
                'name' => now()->format('M Y'), // e.g., Jan 2025
                'period_number' => now()->month,
                'start_date' => now()->startOfMonth(),
                'end_date' => now()->endOfMonth(),
                'period_type' => 'monthly',
                'is_closed' => false,
            ]);
            $this->command->info("Created Accounting Period for " . now()->format('M Y') . ".");
        } else {
            $this->command->info("Ensured Accounting Period for " . now()->format('M Y') . " exists.");
        }


        // 1. SCENARIO: MATCH (Find an unpaid invoice and simulate a payment)
        $invoice = Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial', 'overdue']) // Only posted/sent invoices can be matched
            ->latest() // Get the most recent one
            ->first();
        
        // If no invoice found, create a dummy one for the match scenario
        if (!$invoice) {
            $this->command->info("No posted invoice found. Creating a dummy one for matching.");
            // We need a customer and an income account to create an invoice
            // Ensure AR Account exists
            $arAccount = Account::firstOrCreate(
                ['company_id' => $company->id, 'code' => '1200'],
                ['name' => 'Accounts Receivable', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit']
            );

            $customer = Customer::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'Acme Corp'],
                [
                    'customer_number' => 'CUST-' . substr(uniqid(), -5), // Generate a unique customer number
                    'ar_account_id' => $arAccount->id
                ]
            );
            $incomeAccount = Account::firstOrCreate(
                ['company_id' => $company->id, 'code' => '4000'],
                ['name' => 'Sales Revenue', 'type' => 'revenue', 'subtype' => 'revenue', 'normal_balance' => 'credit']
            );


            $invoice = Invoice::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'invoice_number' => 'INV-DUMMY-MATCH',
                ],
                [
                    'customer_id' => $customer->id,
                    'invoice_date' => now()->subDays(5),
                    'due_date' => now()->addDays(5),
                    'subtotal' => 250.00,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 250.00,
                    'paid_amount' => 0,
                    'balance' => 250.00,
                    'status' => 'sent', // Changed from 'draft' to 'sent'
                    'currency' => 'USD',
                    'base_currency' => 'USD',
                    'base_amount' => 250.00,
                    'payment_terms' => 30,
                ]
            );
            InvoiceLineItem::firstOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'line_number' => 1,
                ],
                [
                    'company_id' => $company->id,
                    'income_account_id' => $incomeAccount->id,
                    'description' => 'Dummy Service',
                    'quantity' => 1,
                    'unit_price' => 250.00,
                    'line_total' => 250.00,
                    'tax_amount' => 0,
                    'discount_rate' => 0,
                    'total' => 250.00,
                ]
            );
            $invoice->fresh(); // Reload with relationships
        }

        if ($invoice) {
            BankTransaction::create([
                'company_id' => $company->id,
                'bank_account_id' => $bankAccount->id,
                'transaction_date' => now()->subDays(1)->startOfDay(),
                'description' => "Deposit from {$invoice->customer->display_name} for INV#{$invoice->invoice_number}",
                'amount' => $invoice->total_amount, // Positive for deposit
                'payee_name' => $invoice->customer->display_name,
                'transaction_type' => 'credit',
                'is_reconciled' => false,
                'source' => 'import',
            ]);
            $this->command->info("Created Match Scenario for Invoice #{$invoice->invoice_number}");
        }

        // 2. SCENARIO: CREATE (Starbucks Expense)
        BankTransaction::create([
            'company_id' => $company->id,
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => now()->subDays(2)->startOfDay(),
            'description' => "STARBUCKS COFFEE #2934",
            'amount' => -15.50, // Negative for expense
            'payee_name' => 'Starbucks',
            'transaction_type' => 'debit',
            'is_reconciled' => false,
            'source' => 'import',
        ]);
        $this->command->info("Created 'Create' Scenario (Starbucks)");

        // 3. SCENARIO: CREATE (Office Depot - Asset/Expense split candidate)
        BankTransaction::create([
            'company_id' => $company->id,
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => now()->subDays(3)->startOfDay(),
            'description' => "OFFICE DEPOT - Equipment",
            'amount' => -450.00,
            'payee_name' => 'Office Depot',
            'transaction_type' => 'debit',
            'is_reconciled' => false,
            'source' => 'import',
        ]);
        $this->command->info("Created 'Create' Scenario (Office Depot)");

        // 4. SCENARIO: TRANSFER (Internal Transfer) - requires another bank account
        $anotherGlAccount = Account::firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => '1020',
            ],
            [
                'name' => 'Savings Account',
                'type' => 'asset',
                'subtype' => 'bank',
                'normal_balance' => 'debit',
                'currency' => 'USD',
                'is_system' => true,
            ]
        );
        $this->command->info("Ensured GL Savings Account '{$anotherGlAccount->name}' exists.");

        $anotherBankAccount = BankAccount::firstOrCreate(
            [
                'company_id' => $company->id,
                'account_name' => 'Chase Savings',
            ],
            [
                'bank_id' => $bank->id,
                'gl_account_id' => $anotherGlAccount->id,
                'account_number' => '****5678',
                'account_type' => 'savings',
                'currency' => 'USD',
                'opening_balance' => 0.00,
                'current_balance' => 0.00,
                'is_active' => true,
            ]
        );
        $this->command->info("Ensured Company Bank Account '{$anotherBankAccount->account_name}' exists.");

        BankTransaction::create([
            'company_id' => $company->id,
            'bank_account_id' => $bankAccount->id, // Main checking account
            'transaction_date' => now()->subDays(4)->startOfDay(),
            'description' => "Internal Transfer to Chase Savings",
            'amount' => -1000.00,
            'transaction_type' => 'debit',
            'is_reconciled' => false,
            'source' => 'import',
        ]);
        $this->command->info("Created 'Transfer' Scenario (Out of Checking)");

        BankTransaction::create([
            'company_id' => $company->id,
            'bank_account_id' => $anotherBankAccount->id, // Savings account
            'transaction_date' => now()->subDays(4)->startOfDay(),
            'description' => "Internal Transfer from Chase Checking",
            'amount' => 1000.00,
            'transaction_type' => 'credit',
            'is_reconciled' => false,
            'source' => 'import',
        ]);
        $this->command->info("Created 'Transfer' Scenario (Into Savings)");

        // 5. SCENARIO: PARK (Unknown Mystery Charge)
        BankTransaction::create([
            'company_id' => $company->id,
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => now()->subDays(5)->startOfDay(),
            'description' => "UNKNOWN CHARGE 88374",
            'amount' => -99.99,
            'transaction_type' => 'debit',
            'is_reconciled' => false,
            'source' => 'import',
        ]);
        $this->command->info("Created 'Park' Scenario");

        // Set initial balance on the main checking account
        $bankAccount->current_balance = -15.50 - 450.00 - 1000.00 - 99.99 + ($invoice->total_amount ?? 0);
        $bankAccount->save();
        $this->command->info("Updated main bank account current_balance.");
    }
}
