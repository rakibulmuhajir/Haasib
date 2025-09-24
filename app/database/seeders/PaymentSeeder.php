<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        $currencies = Currency::all();

        $paymentMethods = [
            'bank_transfer',
            'credit_card',
            'debit_card',
            'check',
            'cash',
            'paypal',
            'stripe',
            'wire_transfer',
        ];

        foreach ($companies as $company) {
            $customers = Customer::where('company_id', $company->id)->get();
            $invoices = Invoice::where('company_id', $company->id)
                ->whereIn('status', ['sent', 'paid'])
                ->where('balance_due', '>', 0)
                ->get();

            if ($invoices->isEmpty()) {
                continue;
            }

            // Create 5-10 payments per company
            $paymentCount = rand(5, 10);

            for ($i = 0; $i < $paymentCount; $i++) {
                $customer = $customers->random();
                $currency = $currencies->random();
                $paymentMethod = $paymentMethods[array_rand($paymentMethods)];
                $paymentDate = now()->subDays(rand(0, 180));

                // Find invoices for this customer that have balance due
                $customerInvoices = $invoices->where('customer_id', $customer->customer_id);

                if ($customerInvoices->isEmpty()) {
                    continue;
                }

                // Determine payment amount
                $totalBalanceDue = $customerInvoices->sum('balance_due');
                $paymentAmount = min(
                    $totalBalanceDue,
                    rand(1000, 15000) // Random payment between 1k-15k
                );

                $status = rand(0, 10) > 1 ? 'completed' : 'pending'; // 80% completed

                $payment = Payment::create([
                    'company_id' => $company->id,
                    'payment_type' => 'customer_payment',
                    'entity_type' => 'customer',
                    'payment_method' => $paymentMethod,
                    'payment_date' => $paymentDate,
                    'amount' => $paymentAmount,
                    'currency_id' => $currency->id,
                    'exchange_rate' => 1.0,
                    'status' => $status,
                ]);

                // Allocate payment to invoices
                if ($status === 'completed') {
                    $remainingAmount = $paymentAmount;
                    $allocatedInvoices = $customerInvoices->shuffle();

                    foreach ($allocatedInvoices as $invoice) {
                        if ($remainingAmount <= 0) {
                            break;
                        }

                        $allocatableAmount = min($remainingAmount, $invoice->balance_due);

                        PaymentAllocation::create([
                            'payment_id' => $payment->payment_id,
                            'invoice_id' => $invoice->invoice_id,
                            'allocated_amount' => $allocatableAmount,
                        ]);

                        $remainingAmount -= $allocatableAmount;
                    }
                }
            }
        }
    }
}
