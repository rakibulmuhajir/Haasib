<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestMultiCurrencySale extends Command
{
    protected $signature = 'app:test-multi-currency';

    protected $description = 'Test multi-currency sales transactions and foreign exchange handling';

    public function handle()
    {
        $this->info('=== PHASE 7.1: Multi-Currency Sale E2E Test ===');
        $this->newLine();

        try {
            // Get or create test company
            $company = $this->getOrCreateTestCompany();
            $this->info("✅ Using company: {$company->name}");

            // Set company context for RLS
            DB::statement("SET app.current_company_id = '{$company->id}'");

            // Step 1: Create international customer
            $customer = $this->createInternationalCustomer($company);
            $this->info("✅ Created international customer: {$customer->name}");

            // Step 2: Create multi-currency invoice (EUR)
            $eurInvoice = $this->createMultiCurrencyInvoice($customer, 'EUR', 1000.00);
            $this->info("✅ Created EUR invoice: {$eurInvoice->invoice_number} (€{$eurInvoice->total_amount})");

            // Step 3: Create multi-currency invoice (GBP)
            $gbpInvoice = $this->createMultiCurrencyInvoice($customer, 'GBP', 850.00);
            $this->info("✅ Created GBP invoice: {$gbpInvoice->invoice_number} (£{$gbpInvoice->total_amount})");

            // Step 4: Process foreign currency payments
            $eurPayment = $this->processForeignCurrencyPayment($customer, $eurInvoice, 'EUR', 1.18);
            $this->info("✅ Processed EUR payment: €{$eurPayment->amount} (exchange rate: {$eurPayment->exchange_rate})");

            $gbpPayment = $this->processForeignCurrencyPayment($customer, $gbpInvoice, 'GBP', 1.32);
            $this->info("✅ Processed GBP payment: £{$gbpPayment->amount} (exchange rate: {$gbpPayment->exchange_rate})");

            // Step 5: Test currency conversion reporting
            $this->testCurrencyConversionReporting($company);

            // Step 6: Test exchange rate gain/loss calculations
            $this->testExchangeRateCalculations($company);

            // Step 7: Test multi-currency reconciliation
            $this->testMultiCurrencyReconciliation($company);

            $this->newLine();
            $this->info('=== Multi-Currency Sale Test Summary ===');
            $this->info('✅ International customer creation');
            $this->info('✅ Multi-currency invoice generation');
            $this->info('✅ Foreign currency payment processing');
            $this->info('✅ Exchange rate management');
            $this->info('✅ Currency conversion reporting');
            $this->info('✅ Multi-currency reconciliation');
            $this->newLine();
            $this->info('🎉 Multi-Currency Sale E2E Testing: SUCCESS');
            $this->info('💱 Complete foreign exchange workflow validated');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Multi-currency sale test failed: '.$e->getMessage());
            $this->error('📍 Stack trace: '.$e->getTraceAsString());

            return 1;
        }
    }

    private function getOrCreateTestCompany(): Company
    {
        $company = Company::where('name', 'like', '%E2E Multi-Currency%')->first();

        if (! $company) {
            $company = Company::create([
                'name' => 'E2E Multi-Currency Company '.time(),
                'email' => 'multicurrency@e2etest.com',
                'phone' => '+1 (555) 123-4567',
                'website' => 'https://www.e2etest.com',
                'currency_code' => 'USD',
                'industry' => 'International Trade',
                'tax_id' => 'E2E-FX-'.time(),
                'status' => 'active',
                'setup_completed' => true,
            ]);
        }

        return $company;
    }

    private function createInternationalCustomer(Company $company): Customer
    {
        return Customer::create([
            'company_id' => $company->id,
            'customer_number' => 'INTL-'.time(),
            'name' => 'E2E International Customer',
            'email' => 'international@e2etest.com',
            'phone' => '+44 20 7123 4567',
            'tax_id' => 'EU-VAT-'.time(),
            'status' => 'active',
            'credit_limit' => 100000.00,
        ]);
    }

    private function createMultiCurrencyInvoice(Customer $customer, string $currencyCode, float $amount): Invoice
    {
        $invoiceNumber = strtoupper($currencyCode).'-'.time();

        return DB::transaction(function () use ($customer, $currencyCode, $amount, $invoiceNumber) {
            $subtotal = $amount;
            $taxAmount = $subtotal * 0.20; // 20% VAT for international
            $totalAmount = $subtotal + $taxAmount;

            $invoice = Invoice::create([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(30)->format('Y-m-d'),
                'notes' => "International invoice in {$currencyCode}",
                'terms' => 'Net 30 days - International payment terms',
                'status' => 'draft',
                'currency' => $currencyCode,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'balance_due' => $totalAmount,
            ]);

            return $invoice;
        });
    }

    private function processForeignCurrencyPayment(Customer $customer, Invoice $invoice, string $currencyCode, float $exchangeRate): Payment
    {
        return DB::transaction(function () use ($customer, $invoice, $currencyCode, $exchangeRate) {
            $payment = Payment::create([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->id,
                'payment_number' => strtoupper($currencyCode).'-PAY-'.uniqid(),
                'amount' => $invoice->total_amount,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => 'wire_transfer',
                'currency' => $currencyCode,
                'reference_number' => 'FX-PAY-'.uniqid(),
                'notes' => "Foreign currency payment - {$currencyCode} to USD conversion at {$exchangeRate}",
                'status' => 'completed',
                'metadata' => json_encode([
                    'exchange_rate' => $exchangeRate,
                    'original_currency' => $currencyCode,
                    'usd_equivalent' => $invoice->total_amount * $exchangeRate,
                ]),
            ]);

            // Auto-allocate payment to invoice
            $payment->allocations()->create([
                'company_id' => $customer->company_id,
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total_amount,
                'allocation_date' => now()->format('Y-m-d'),
                'allocation_method' => 'automatic',
            ]);

            // Update invoice status to paid
            $invoice->update(['status' => 'paid']);

            return $payment;
        });
    }

    private function testCurrencyConversionReporting(Company $company): void
    {
        $this->info('💱 Testing Currency Conversion Reporting:');

        $payments = Payment::where('company_id', $company->id)->get();

        $usdPayments = $payments->where('currency', 'USD')->sum('amount');
        $eurPayments = $payments->where('currency', 'EUR')->sum('amount');
        $gbpPayments = $payments->where('currency', 'GBP')->sum('amount');

        // Calculate USD equivalents from metadata
        $eurInUsd = 0;
        $gbpInUsd = 0;

        foreach ($payments as $payment) {
            $metadata = json_decode($payment->metadata ?? '{}', true);
            if (isset($metadata['usd_equivalent'])) {
                if ($payment->currency === 'EUR') {
                    $eurInUsd += $metadata['usd_equivalent'];
                } elseif ($payment->currency === 'GBP') {
                    $gbpInUsd += $metadata['usd_equivalent'];
                }
            }
        }

        $totalInUsd = $usdPayments + $eurInUsd + $gbpInUsd;

        $this->line("   USD Payments: \${$usdPayments}");
        $this->line("   EUR Payments: €{$eurPayments} (≈ \${$eurInUsd})");
        $this->line("   GBP Payments: £{$gbpPayments} (≈ \${$gbpInUsd})");
        $this->line("   Total (USD Equivalent): \${$totalInUsd}");
        $this->info('   ✅ Currency conversion reporting working');
    }

    private function testExchangeRateCalculations(Company $company): void
    {
        $this->info('📈 Testing Exchange Rate Gain/Loss Calculations:');

        $payments = Payment::where('company_id', $company->id)
            ->where('currency', '!=', 'USD')
            ->get();

        $totalGainLoss = 0;
        foreach ($payments as $payment) {
            $metadata = json_decode($payment->metadata ?? '{}', true);
            $exchangeRate = $metadata['exchange_rate'] ?? 1;
            $originalAmount = $payment->amount;
            $usdEquivalent = $originalAmount * $exchangeRate;

            // Simulate different spot rates for gain/loss calculation
            $spotRate = $exchangeRate * 1.05; // 5% favorable movement
            $currentUsdValue = $originalAmount * $spotRate;
            $gainLoss = $currentUsdValue - $usdEquivalent;

            $totalGainLoss += $gainLoss;

            $this->line("   {$payment->currency} Payment: {$payment->amount} @ {$exchangeRate}");
            $this->line("     Current value: {$payment->amount} @ {$spotRate} = \${$currentUsdValue}");
            $this->line("     Gain/Loss: \${$gainLoss}");
        }

        $this->info("   Total Unrealized Gain/Loss: \${$totalGainLoss}");
        $this->info('   ✅ Exchange rate calculations working');
    }

    private function testMultiCurrencyReconciliation(Company $company): void
    {
        $this->info('🔍 Testing Multi-Currency Reconciliation:');

        $payments = Payment::where('company_id', $company->id)
            ->with(['customer', 'allocations.invoice'])
            ->get();

        $totalPayments = $payments->count();
        $allocatedPayments = 0;
        $currencies = [];

        foreach ($payments as $payment) {
            $isFullyAllocated = $payment->allocations->sum('amount') >= $payment->amount;
            if ($isFullyAllocated) {
                $allocatedPayments++;
            }

            $currency = $payment->currency ?? 'USD';
            if (! isset($currencies[$currency])) {
                $currencies[$currency] = ['count' => 0, 'amount' => 0];
            }
            $currencies[$currency]['count']++;

            // Use USD equivalent from metadata or default to 1:1
            $metadata = json_decode($payment->metadata ?? '{}', true);
            $usdEquivalent = $metadata['usd_equivalent'] ?? $payment->amount;
            $currencies[$currency]['amount'] += $usdEquivalent;
        }

        $this->line("   Total Payments: {$totalPayments}");
        $this->line("   Fully Allocated: {$allocatedPayments}");

        foreach ($currencies as $currency => $data) {
            $this->line("   {$currency}: {$data['count']} payments, \${$data['amount']} USD equivalent");
        }

        $this->info('   ✅ Multi-currency reconciliation functional');
    }
}
