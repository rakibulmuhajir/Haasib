<?php

namespace Database\Seeders;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;

class RetailCompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'RetailMart Egypt')->first();
        if (! $company) {
            $this->command->error('RetailMart Egypt not found. Run SetupSeeder first.');

            return;
        }

        $this->command->info('Creating retail company demo data...');

        DB::beginTransaction();

        try {
            $this->createRetailCustomers($company);
            $this->createRetailInvoices($company);
            $this->createRetailPayments($company);

            DB::commit();
            $this->command->info('✓ Retail company demo data created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Retail demo data creation failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function createRetailCustomers(Company $company): void
    {
        $customers = [
            [
                'name' => 'B2B Wholesale Ltd',
                'email' => 'wholesale@b2b-egypt.com',
                'phone' => '+20 2 2734 5678',
                'industry_type' => 'wholesale',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Mariam Ahmed',
                'email' => 'mariam.ahmed@email.com',
                'phone' => '+20 11 2345 6789',
                'industry_type' => 'retail',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Cairo Distributors',
                'email' => 'orders@cairodistributors.com',
                'phone' => '+20 2 2842 9100',
                'industry_type' => 'distribution',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Omar Khalid',
                'email' => 'omar.k@email.com',
                'phone' => '+20 10 8765 4321',
                'industry_type' => 'retail',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Alexandria Retail Group',
                'email' => 'purchasing@alexretail.com',
                'phone' => '+20 3 4861 2345',
                'industry_type' => 'retail',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Electronics Superstore',
                'email' => 'bulk@electrostore.com',
                'phone' => '+20 2 2742 1000',
                'industry_type' => 'retail',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Fashion Boutique Chain',
                'email' => 'orders@fashionboutique.com',
                'phone' => '+20 2 2780 9000',
                'industry_type' => 'fashion',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Home Goods Egypt',
                'email' => 'wholesale@homegoods.com',
                'phone' => '+20 2 2720 5000',
                'industry_type' => 'home_goods',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Toy Land Distributors',
                'email' => 'orders@toyland.com',
                'phone' => '+20 3 4801 0000',
                'industry_type' => 'toys',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Sports Equipment Co',
                'email' => 'bulk@sportsequipment.com',
                'phone' => '+20 2 2528 1000',
                'industry_type' => 'sports',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Bookshop Chain',
                'email' => 'purchasing@bookshop.com',
                'phone' => '+20 2 2742 2000',
                'industry_type' => 'books',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Beauty Products Ltd',
                'email' => 'wholesale@beautyproducts.com',
                'phone' => '+20 2 2742 3000',
                'industry_type' => 'cosmetics',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Pet Supply Store',
                'email' => 'orders@petsupply.com',
                'phone' => '+20 2 2742 4000',
                'industry_type' => 'pet_supplies',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Office Equipment Egypt',
                'email' => 'bulk@officeeq.com',
                'phone' => '+20 2 2742 5000',
                'industry_type' => 'office',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Garden Center Chain',
                'email' => 'orders@gardencenter.com',
                'phone' => '+20 2 2742 6000',
                'industry_type' => 'gardening',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Auto Parts Supplier',
                'email' => 'wholesale@autoparts.com',
                'phone' => '+20 2 2742 7000',
                'industry_type' => 'automotive',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Baby Products Egypt',
                'email' => 'orders@babyproducts.com',
                'phone' => '+20 2 2742 8000',
                'industry_type' => 'baby_products',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Hardware Store Chain',
                'email' => 'bulk@hardwarestore.com',
                'phone' => '+20 2 2742 9000',
                'industry_type' => 'hardware',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Kitchen Supplies Co',
                'email' => 'orders@kitchensupply.com',
                'phone' => '+20 2 2743 1000',
                'industry_type' => 'kitchen',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Pharmacy Chain',
                'email' => 'wholesale@pharmacy.com',
                'phone' => '+20 2 2743 2000',
                'industry_type' => 'pharmacy',
                'company_id' => $company->id,
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::updateOrCreate(
                ['company_id' => $company->id, 'email' => $customerData['email']],
                array_merge($customerData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✓ Created 20 retail customers');
    }

    private function createRetailInvoices(Company $company): void
    {
        $startDate = Carbon::now()->subMonths(3)->startOfMonth();
        $totalInvoices = 90; // 30 per month for 3 months

        $invoiceTypes = [
            'product_sales' => [
                'weight' => 70,
                'categories' => [
                    'Electronics and accessories',
                    'Clothing and fashion items',
                    'Home furniture and decor',
                    'Grocery and food items',
                    'Sports equipment',
                    'Books and stationery',
                    'Beauty and personal care',
                    'Toys and games',
                    'Pet supplies',
                    'Office equipment',
                ],
                'base_amounts' => [3000, 2500, 4000, 2000, 3500, 1500, 1800, 2200, 2800, 5000],
            ],
            'returns' => [
                'weight' => 15,
                'reasons' => [
                    'Defective product returns',
                    'Customer change of mind',
                    'Damaged in shipping',
                    'Wrong product delivered',
                    'Expired items',
                ],
                'base_amounts' => [-800, -500, -1200, -600, -300],
            ],
            'bulk_orders' => [
                'weight' => 10,
                'quantities' => [
                    'Bulk purchase - 100+ units',
                    'Wholesale order - 500 units',
                    'Large quantity order - 1000 units',
                    'Mass distribution order',
                ],
                'base_amounts' => [15000, 25000, 40000, 60000],
            ],
            'services' => [
                'weight' => 5,
                'services' => [
                    'Delivery and installation',
                    'Extended warranty plans',
                    'Assembly services',
                    'Technical support contracts',
                    'Storage services',
                ],
                'base_amounts' => [500, 800, 1200, 2000, 300],
            ],
        ];

        $invoicesCreated = 0;

        for ($month = 0; $month < 3; $month++) {
            $monthDate = $startDate->copy()->addMonths($month);
            $invoicesThisMonth = 30;

            for ($i = 0; $i < $invoicesThisMonth; $i++) {
                $invoiceType = $this->selectWeightedType($invoiceTypes);
                $customer = $this->getRandomCustomer($company);

                if (! $customer) {
                    continue;
                }

                $invoiceDate = $monthDate->copy()->addDays(rand(1, 28));
                $dueDate = $invoiceDate->copy()->addDays(30);

                $typeData = $invoiceTypes[$invoiceType];
                $description = $this->generateDescription($typeData, $invoiceType);
                $baseAmount = $typeData['base_amounts'][array_rand($typeData['base_amounts'])];

                // Calculate amount with seasonal trends and growth
                $amount = $this->calculateAmount($baseAmount, $invoiceDate, $month);

                $invoice = Invoice::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'invoice_number' => $this->generateInvoiceNumber($company, $invoiceDate),
                    'issue_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'total_amount' => $amount,
                    'status' => $this->selectInvoiceStatus($invoiceDate),
                    'line_items' => json_encode([[
                        'description' => $description,
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'total' => $amount,
                    ]]),
                    'created_at' => $invoiceDate,
                    'updated_at' => $invoiceDate,
                ]);

                $invoicesCreated++;
            }
        }

        $this->command->info("✓ Created {$invoicesCreated} retail invoices");
    }

    private function createRetailPayments(Company $company): void
    {
        $invoices = Invoice::where('company_id', $company->id)->get();
        $paymentsCreated = 0;

        foreach ($invoices as $invoice) {
            // Create payment with 78% probability
            if (rand(1, 100) <= 78) {
                Payment::create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->total_amount,
                    'payment_date' => $invoice->issue_date->copy()->addDays(rand(1, 25)),
                    'method' => $this->selectPaymentMethod(),
                    'status' => 'completed',
                    'created_at' => $invoice->issue_date,
                    'updated_at' => $invoice->issue_date,
                ]);

                $paymentsCreated++;

                // Update invoice status to paid
                $invoice->status = 'paid';
                $invoice->save();
            }
        }

        $this->command->info("✓ Created {$paymentsCreated} retail payments (78% payment rate)");
    }

    private function selectWeightedType(array $types): string
    {
        $totalWeight = array_sum(array_column($types, 'weight'));
        $random = rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($types as $type => $data) {
            $currentWeight += $data['weight'];
            if ($random <= $currentWeight) {
                return $type;
            }
        }

        return array_key_first($types);
    }

    private function generateDescription(array $typeData, string $type): string
    {
        if ($type === 'product_sales') {
            return $typeData['categories'][array_rand($typeData['categories'])];
        } elseif ($type === 'returns') {
            return $typeData['reasons'][array_rand($typeData['reasons'])];
        } elseif ($type === 'bulk_orders') {
            return $typeData['quantities'][array_rand($typeData['quantities'])];
        } elseif ($type === 'services') {
            return $typeData['services'][array_rand($typeData['services'])];
        }

        return 'Retail services';
    }

    private function getRandomCustomer(Company $company): ?Customer
    {
        return Customer::where('company_id', $company->id)
            ->inRandomOrder()
            ->first();
    }

    private function calculateAmount(float $baseAmount, Carbon $date, int $monthOffset): float
    {
        // Growth trend: increase 3% each month
        $growthFactor = 1 + ($monthOffset * 0.03);

        // Seasonal factors for retail
        $seasonFactor = 1.0;
        if ($date->month == 12) {
            $seasonFactor = 1.4;
        } // Holiday season peak
        elseif ($date->month == 11) {
            $seasonFactor = 1.2;
        } // Pre-holiday
        elseif ($date->month == 1) {
            $seasonFactor = 0.8;
        } // Post-holiday slump
        elseif ($date->month == 7 || $date->month == 8) {
            $seasonFactor = 1.1;
        } // Summer sales

        // Random variation (±20%)
        $randomFactor = 0.8 + (rand(0, 40) / 100);

        return round($baseAmount * $growthFactor * $seasonFactor * $randomFactor, 2);
    }

    private function generateInvoiceNumber(Company $company, Carbon $date): string
    {
        $prefix = 'RTL'; // Retail prefix
        $year = $date->format('Y');
        $month = $date->format('m');
        $sequence = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$month}-{$sequence}";
    }

    private function selectInvoiceStatus(Carbon $issueDate): string
    {
        $daysSinceIssued = $issueDate->diffInDays(now());

        if ($daysSinceIssued > 45 && rand(1, 100) <= 75) {
            return 'overdue';
        }
        if ($daysSinceIssued > 30 && rand(1, 100) <= 65) {
            return 'paid';
        }
        if ($daysSinceIssued > 15 && rand(1, 100) <= 45) {
            return 'sent';
        }

        return 'draft';
    }

    private function selectPaymentMethod(): string
    {
        $methods = [
            'bank_transfer' => 40,
            'credit_card' => 35,
            'cash' => 15,
            'check' => 7,
            'mobile_payment' => 3,
        ];

        $totalWeight = array_sum($methods);
        $random = rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($methods as $method => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $method;
            }
        }

        return 'bank_transfer';
    }
}
