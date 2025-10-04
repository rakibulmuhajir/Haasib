<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();
        $currencies = Currency::all();

        $invoiceServices = [
            ['name' => 'Web Development', 'description' => 'Custom website development services', 'unit_price' => 5000.00, 'quantity' => 1],
            ['name' => 'UI/UX Design', 'description' => 'User interface and experience design', 'unit_price' => 2500.00, 'quantity' => 1],
            ['name' => 'Mobile App Development', 'description' => 'iOS and Android app development', 'unit_price' => 10000.00, 'quantity' => 1],
            ['name' => 'Database Design', 'description' => 'Database architecture and optimization', 'unit_price' => 3000.00, 'quantity' => 1],
            ['name' => 'Cloud Infrastructure Setup', 'description' => 'AWS/Azure cloud setup and configuration', 'unit_price' => 4500.00, 'quantity' => 1],
            ['name' => 'SEO Optimization', 'description' => 'Search engine optimization services', 'unit_price' => 1500.00, 'quantity' => 1],
            ['name' => 'Content Management System', 'description' => 'CMS implementation and training', 'unit_price' => 3500.00, 'quantity' => 1],
            ['name' => 'E-commerce Integration', 'description' => 'Payment gateway and shopping cart setup', 'unit_price' => 4000.00, 'quantity' => 1],
        ];

        $statuses = ['draft', 'sent', 'paid', 'cancelled'];

        foreach ($companies as $company) {
            $customers = Customer::where('company_id', $company->id)->get();
            $currency = $currencies->random();

            // Create 8-12 invoices per company
            $invoiceCount = rand(8, 12);

            for ($i = 0; $i < $invoiceCount; $i++) {
                $customer = $customers->random();
                $invoiceDate = now()->subDays(rand(0, 365));
                $dueDate = $invoiceDate->copy()->addDays(rand(15, 60));
                $status = $statuses[array_rand($statuses)];

                // Generate random services for this invoice
                $selectedServices = collect($invoiceServices)->random(rand(1, 4))->values();

                $subtotal = $selectedServices->sum(function ($service) {
                    return $service['unit_price'] * $service['quantity'];
                });

                $taxRate = rand(0, 10) / 100; // 0-10% tax
                $totalTax = $subtotal * $taxRate;
                $totalAmount = $subtotal + $totalTax;

                // If status is paid or partially paid, calculate paid amount
                $amountPaid = 0;
                if ($status === 'paid') {
                    $amountPaid = $totalAmount;
                } elseif ($status === 'sent' && rand(0, 1)) {
                    $amountPaid = $totalAmount * (rand(20, 80) / 100); // Partial payment
                }

                $balanceDue = $totalAmount - $amountPaid;

                $invoice = Invoice::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->customer_id,
                    'invoice_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'currency_id' => $currency->id,
                    'subtotal' => $subtotal,
                    'tax_amount' => $totalTax,
                    'discount_amount' => 0,
                    'shipping_amount' => 0,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $amountPaid,
                    'balance_due' => $balanceDue,
                    'status' => $status,
                    'payment_status' => $status === 'paid' ? 'paid' : ($amountPaid > 0 ? 'partial' : 'unpaid'),
                    'notes' => 'Thank you for your business!',
                    'created_by' => null,
                    'updated_by' => null,
                ]);

                // Create invoice items
                foreach ($selectedServices as $service) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->invoice_id,
                        'description' => $service['name'],
                        'quantity' => $service['quantity'],
                        'unit_price' => $service['unit_price'],
                        'line_total' => $service['unit_price'] * $service['quantity'],
                        'sort_order' => rand(1, 100),
                    ]);
                }
            }
        }
    }
}
