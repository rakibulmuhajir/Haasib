<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BusinessLogicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first company for testing
        $company = DB::table('auth.companies')->first();

        if (!$company) {
            $this->command->error('No company found. Please run basic seeders first.');
            return;
        }

        $this->command->info('Seeding business logic data for company: ' . $company->name);

        // Create a test customer
        $customerId = Str::uuid();
        DB::table('acct.customers')->insert([
            'id' => $customerId,
            'company_id' => $company->id,
            'customer_number' => 'CUST-001',
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'phone' => '+1-555-0123',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'TS',
            'postal_code' => '12345',
            'country' => 'US',
            'credit_limit' => 10000.00,
            'currency' => 'USD',
            'status' => 'active',
            'opening_balance' => 0.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Created test customer: CUST-001');

        // Create a test invoice
        $invoiceId = Str::uuid();
        DB::table('acct.invoices')->insert([
            'id' => $invoiceId,
            'company_id' => $company->id,
            'customer_id' => $customerId,
            'invoice_number' => 'INV-2024-001',
            'invoice_date' => now()->subDays(30),
            'due_date' => now()->subDays(15),
            'subtotal' => 1000.00,
            'tax_amount' => 80.00,
            'discount_amount' => 0.00,
            'total_amount' => 1080.00,
            'paid_amount' => 0.00,
            'balance_due' => 1080.00,
            'currency' => 'USD',
            'status' => 'sent',
            'notes' => 'Test invoice for business logic verification',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Created test invoice: INV-2024-001');

        // Create a test payment
        $paymentId = Str::uuid();
        DB::table('acct.payments')->insert([
            'id' => $paymentId,
            'company_id' => $company->id,
            'customer_id' => $customerId,
            'payment_number' => 'PAY-2024-001',
            'payment_date' => now()->subDays(20),
            'amount' => 500.00,
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'BANK-REF-001',
            'status' => 'completed',
            'notes' => 'Test payment for invoice INV-2024-001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Created test payment: PAY-2024-001');

        // Create a payment allocation
        DB::table('acct.payment_allocations')->insert([
            'id' => Str::uuid(),
            'company_id' => $company->id,
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'amount' => 500.00,
            'allocation_date' => now()->subDays(20),
            'notes' => 'Partial payment allocation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Created payment allocation: $500.00');

        // Create a test journal entry
        $journalId = Str::uuid();
        DB::table('acct.journal_entries')->insert([
            'id' => $journalId,
            'company_id' => $company->id,
            'entry_number' => 'JE-2024-001',
            'entry_date' => now()->subDays(30),
            'description' => 'Test journal entry for business logic verification',
            'reference' => 'TEST-REF-001',
            'status' => 'posted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Created test journal entry: JE-2024-001');

        // Create journal lines (debit and credit)
        DB::table('acct.journal_lines')->insert([
            [
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'journal_entry_id' => $journalId,
                'account_number' => '4000',
                'account_name' => 'Sales Revenue',
                'description' => 'Revenue from test invoice',
                'debit_amount' => 0.00,
                'credit_amount' => 1080.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'company_id' => $company->id,
                'journal_entry_id' => $journalId,
                'account_number' => '1200',
                'account_name' => 'Accounts Receivable',
                'description' => 'Accounts receivable from test customer',
                'debit_amount' => 1080.00,
                'credit_amount' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info('Created journal lines for balanced entry');

        $this->command->info('Business logic seeding completed successfully!');
    }
}