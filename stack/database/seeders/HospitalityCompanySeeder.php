<?php

namespace Database\Seeders;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;

class HospitalityCompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'Grand Hotel Alexandria')->first();
        if (! $company) {
            $this->command->error('Grand Hotel Alexandria not found. Run SetupSeeder first.');

            return;
        }

        $this->command->info('Creating hospitality company demo data...');

        DB::beginTransaction();

        try {
            $this->createHospitalityCustomers($company);
            $this->createHospitalityInvoices($company);
            $this->createHospitalityPayments($company);

            DB::commit();
            $this->command->info('✓ Hospitality company demo data created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Hospitality demo data creation failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function createHospitalityCustomers(Company $company): void
    {
        $customers = [
            [
                'name' => 'Corporate Travel Ltd',
                'email' => 'bookings@corptravel.com',
                'phone' => '+20 2 2461 2345',
                'industry_type' => 'corporate',
                'company_id' => $company->id,
            ],
            [
                'name' => 'John Smith',
                'email' => 'john.smith@email.com',
                'phone' => '+20 12 3456 7890',
                'industry_type' => 'individual',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Egypt Tourism Board',
                'email' => 'events@egypttourism.gov.eg',
                'phone' => '+20 2 2735 4000',
                'industry_type' => 'government',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.j@email.com',
                'phone' => '+20 10 9876 5432',
                'industry_type' => 'individual',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Global Events Co',
                'email' => 'conferences@globalevents.com',
                'phone' => '+20 2 2528 1000',
                'industry_type' => 'corporate',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Mediterranean Cruises',
                'email' => 'groups@medcruises.com',
                'phone' => '+20 2 2577 8900',
                'industry_type' => 'tourism',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Dr. Ahmed Hassan',
                'email' => 'ahmed.hassan@medical.com',
                'phone' => '+20 12 2345 6789',
                'industry_type' => 'individual',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Alexandria Business Center',
                'email' => 'events@alexandriabc.com',
                'phone' => '+20 3 4861 2345',
                'industry_type' => 'corporate',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Luxor Tours',
                'email' => 'bookings@luxortours.com',
                'phone' => '+20 2 2780 9000',
                'industry_type' => 'tourism',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Wedding Planners Egypt',
                'email' => 'events@weddingegypt.com',
                'phone' => '+20 10 5432 1098',
                'industry_type' => 'events',
                'company_id' => $company->id,
            ],
            [
                'name' => 'International Conference Center',
                'email' => 'booking@iccegypt.com',
                'phone' => '+20 2 2720 5000',
                'industry_type' => 'corporate',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Royal Family Representative',
                'email' => 'royal.booking@email.com',
                'phone' => '+20 12 8765 4321',
                'industry_type' => 'vip',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Cairo University Guest House',
                'email' => 'accommodation@cu.edu.eg',
                'phone' => '+20 2 3567 2000',
                'industry_type' => 'education',
                'company_id' => $company->id,
            ],
            [
                'name' => 'German Embassy Staff',
                'email' => 'embassy.bookings@germany.eg',
                'phone' => '+20 2 2742 1000',
                'industry_type' => 'diplomatic',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Film Production Crew',
                'email' => 'production@filmsgypt.com',
                'phone' => '+20 12 7654 3210',
                'industry_type' => 'entertainment',
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

        $this->command->info('✓ Created 15 hospitality customers');
    }

    private function createHospitalityInvoices(Company $company): void
    {
        $startDate = Carbon::now()->subMonths(3)->startOfMonth();
        $totalInvoices = 60; // 20 per month for 3 months

        $invoiceTypes = [
            'room_booking' => [
                'weight' => 40,
                'descriptions' => [
                    'Deluxe suite accommodation',
                    'Standard room single occupancy',
                    'Ocean view room double occupancy',
                    'Presidential suite accommodation',
                    'Family room accommodation',
                    'Business class room',
                ],
                'base_amounts' => [1200, 800, 1000, 3000, 1500, 1100],
            ],
            'restaurant' => [
                'weight' => 30,
                'descriptions' => [
                    'Restaurant dinner service',
                    'Breakfast buffet for guests',
                    'Conference catering',
                    'Room service orders',
                    'Special event catering',
                    'Pool bar service',
                ],
                'base_amounts' => [600, 400, 1500, 300, 2000, 500],
            ],
            'event' => [
                'weight' => 20,
                'descriptions' => [
                    'Wedding reception venue',
                    'Corporate conference facilities',
                    'Business meeting room rental',
                    'Exhibition space rental',
                    'Gala dinner event',
                ],
                'base_amounts' => [8000, 5000, 2000, 3000, 10000, 6000],
            ],
            'miscellaneous' => [
                'weight' => 10,
                'descriptions' => [
                    'Laundry and dry cleaning',
                    'Airport transfer services',
                    'Spa and wellness services',
                    'Parking fees',
                    'Business center services',
                ],
                'base_amounts' => [200, 150, 500, 100, 300],
            ],
        ];

        $invoicesCreated = 0;

        for ($month = 0; $month < 3; $month++) {
            $monthDate = $startDate->copy()->addMonths($month);
            $invoicesThisMonth = 20;

            for ($i = 0; $i < $invoicesThisMonth; $i++) {
                $invoiceType = $this->selectWeightedType($invoiceTypes);
                $customer = $this->getRandomCustomer($company);

                if (! $customer) {
                    continue;
                }

                $invoiceDate = $monthDate->copy()->addDays(rand(1, 28));
                $dueDate = $invoiceDate->copy()->addDays(30);

                $typeData = $invoiceTypes[$invoiceType];
                $description = $typeData['descriptions'][array_rand($typeData['descriptions'])];
                $baseAmount = $typeData['base_amounts'][array_rand($typeData['base_amounts'])];

                // Add seasonal variation and growth trends
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

        $this->command->info("✓ Created {$invoicesCreated} hospitality invoices");
    }

    private function createHospitalityPayments(Company $company): void
    {
        $invoices = Invoice::where('company_id', $company->id)->get();
        $paymentsCreated = 0;

        foreach ($invoices as $invoice) {
            // Create payment with 75% probability
            if (rand(1, 100) <= 75) {
                Payment::create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->total_amount,
                    'payment_date' => $invoice->issue_date->copy()->addDays(rand(1, 20)),
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

        $this->command->info("✓ Created {$paymentsCreated} hospitality payments (75% payment rate)");
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

    private function getRandomCustomer(Company $company): ?Customer
    {
        return Customer::where('company_id', $company->id)
            ->inRandomOrder()
            ->first();
    }

    private function calculateAmount(float $baseAmount, Carbon $date, int $monthOffset): float
    {
        // Growth trend: increase 5% each month
        $growthFactor = 1 + ($monthOffset * 0.05);

        // Seasonal factors
        $seasonFactor = 1.0;
        if ($date->month == 12) {
            $seasonFactor = 1.3;
        } // Holiday season
        elseif ($date->month == 7 || $date->month == 8) {
            $seasonFactor = 1.2;
        } // Summer peak

        // Random variation (±15%)
        $randomFactor = 0.85 + (rand(0, 30) / 100);

        return round($baseAmount * $growthFactor * $seasonFactor * $randomFactor, 2);
    }

    private function generateInvoiceNumber(Company $company, Carbon $date): string
    {
        $prefix = 'HOT'; // Hospitality prefix
        $year = $date->format('Y');
        $month = $date->format('m');
        $sequence = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$month}-{$sequence}";
    }

    private function selectInvoiceStatus(Carbon $issueDate): string
    {
        $daysSinceIssued = $issueDate->diffInDays(now());

        if ($daysSinceIssued > 45 && rand(1, 100) <= 80) {
            return 'overdue';
        }
        if ($daysSinceIssued > 30 && rand(1, 100) <= 60) {
            return 'paid';
        }
        if ($daysSinceIssued > 15 && rand(1, 100) <= 40) {
            return 'sent';
        }

        return 'draft';
    }

    private function selectPaymentMethod(): string
    {
        $methods = [
            'bank_transfer' => 35,
            'credit_card' => 30,
            'cash' => 20,
            'check' => 10,
            'mobile_payment' => 5,
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
