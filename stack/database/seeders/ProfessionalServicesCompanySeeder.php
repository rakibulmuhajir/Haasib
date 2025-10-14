<?php

namespace Database\Seeders;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;

class ProfessionalServicesCompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'ConsultPro Solutions')->first();
        if (! $company) {
            $this->command->error('ConsultPro Solutions not found. Run SetupSeeder first.');

            return;
        }

        $this->command->info('Creating professional services company demo data...');

        DB::beginTransaction();

        try {
            $this->createProfessionalServicesCustomers($company);
            $this->createProfessionalServicesInvoices($company);
            $this->createProfessionalServicesPayments($company);

            DB::commit();
            $this->command->info('✓ Professional services company demo data created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Professional services demo data creation failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function createProfessionalServicesCustomers(Company $company): void
    {
        $customers = [
            [
                'name' => 'Ministry of Technology',
                'email' => 'projects@mot.gov.eg',
                'phone' => '+20 2 2742 1000',
                'industry_type' => 'government',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Cairo University',
                'email' => 'consulting@cu.edu.eg',
                'phone' => '+20 2 3567 2000',
                'industry_type' => 'education',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Nile Bank',
                'email' => 'it.consulting@nilebank.com',
                'phone' => '+20 2 2780 9000',
                'industry_type' => 'banking',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Egypt Telecom',
                'email' => 'projects@telecom.eg',
                'phone' => '+20 2 2720 5000',
                'industry_type' => 'telecom',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Alexandria Port Authority',
                'email' => 'consulting@apaport.com',
                'phone' => '+20 3 4801 0000',
                'industry_type' => 'government',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Cairo International Airport',
                'email' => 'it.projects@caiport.com',
                'phone' => '+20 2 2265 5000',
                'industry_type' => 'aviation',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Egyptian Pharmaceutical Company',
                'email' => 'digital@pharma.com',
                'phone' => '+20 2 2742 1000',
                'industry_type' => 'pharmaceutical',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Cairo Metro Authority',
                'email' => 'automation@cairometro.gov.eg',
                'phone' => '+20 2 2742 2000',
                'industry_type' => 'transportation',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Arab Contractors',
                'email' => 'erp@arabcont.com',
                'phone' => '+20 2 2742 3000',
                'industry_type' => 'construction',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Orascom Construction',
                'email' => 'systems@orascom.com',
                'phone' => '+20 2 2742 4000',
                'industry_type' => 'construction',
                'company_id' => $company->id,
            ],
            [
                'name' => 'American University in Cairo',
                'email' => 'technology@aucegypt.edu',
                'phone' => '+20 2 2742 5000',
                'industry_type' => 'education',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Egyptian Stock Exchange',
                'email' => 'trading@egyptse.com',
                'phone' => '+20 2 2742 6000',
                'industry_type' => 'finance',
                'company_id' => $company->id,
            ],
            [
                'name' => 'Banque Misr',
                'email' => 'digital@banquemisr.com',
                'phone' => '+20 2 2742 7000',
                'industry_type' => 'banking',
                'company_id' => $company->id,
            ],
            [
                'name' => 'National Bank of Egypt',
                'email' => 'innovation@nbe.com',
                'phone' => '+20 2 2742 8000',
                'industry_type' => 'banking',
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

        $this->command->info('✓ Created 12 professional services customers');
    }

    private function createProfessionalServicesInvoices(Company $company): void
    {
        $startDate = Carbon::now()->subMonths(3)->startOfMonth();
        $totalInvoices = 36; // 12 per month for 3 months

        $invoiceTypes = [
            'hourly_billing' => [
                'weight' => 50,
                'services' => [
                    'Strategic IT consulting',
                    'Digital transformation advisory',
                    'Business process optimization',
                    'Technical architecture design',
                    'Cybersecurity assessment',
                    'Cloud migration consulting',
                    'Data analytics strategy',
                    'Software development consulting',
                ],
                'base_amounts' => [8000, 12000, 6000, 10000, 15000, 9000, 7000, 11000],
                'hours' => [40, 60, 80, 100, 120],
            ],
            'project_milestones' => [
                'weight' => 30,
                'milestones' => [
                    'Phase 1: Requirements analysis complete',
                    'Phase 2: System design delivered',
                    'Phase 3: Development milestone achieved',
                    'Phase 4: Testing and QA completed',
                    'Phase 5: Deployment and training',
                    'Final project delivery and handover',
                ],
                'base_amounts' => [25000, 35000, 40000, 30000, 20000, 15000],
            ],
            'retainers' => [
                'weight' => 15,
                'periods' => [
                    'Monthly retainer - ongoing support',
                    'Quarterly retainer - strategic advisory',
                    'Annual retainer - CTO as a service',
                    'Monthly technical support retainer',
                ],
                'base_amounts' => [15000, 45000, 180000, 12000],
            ],
            'expenses' => [
                'weight' => 5,
                'expense_types' => [
                    'Travel expenses - client site visits',
                    'Software licenses and tools',
                    'Research materials and reports',
                    'Third-party consultant fees',
                    'Training and certification costs',
                ],
                'base_amounts' => [2000, 5000, 1500, 8000, 3000],
            ],
        ];

        $invoicesCreated = 0;

        for ($month = 0; $month < 3; $month++) {
            $monthDate = $startDate->copy()->addMonths($month);
            $invoicesThisMonth = 12;

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

                // Calculate amount with professional services growth patterns
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

        $this->command->info("✓ Created {$invoicesCreated} professional services invoices");
    }

    private function createProfessionalServicesPayments(Company $company): void
    {
        $invoices = Invoice::where('company_id', $company->id)->get();
        $paymentsCreated = 0;

        foreach ($invoices as $invoice) {
            // Create payment with 94% probability (high payment rate for professional services)
            if (rand(1, 100) <= 94) {
                Payment::create([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->total_amount,
                    'payment_date' => $invoice->issue_date->copy()->addDays(rand(1, 15)),
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

        $this->command->info("✓ Created {$paymentsCreated} professional services payments (94% payment rate)");
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
        if ($type === 'hourly_billing') {
            $service = $typeData['services'][array_rand($typeData['services'])];
            $hours = $typeData['hours'][array_rand($typeData['hours'])];

            return "{$service} - {$hours} hours";
        } elseif ($type === 'project_milestones') {
            return $typeData['milestones'][array_rand($typeData['milestones'])];
        } elseif ($type === 'retainers') {
            return $typeData['periods'][array_rand($typeData['periods'])];
        } elseif ($type === 'expenses') {
            return $typeData['expense_types'][array_rand($typeData['expense_types'])];
        }

        return 'Professional consulting services';
    }

    private function getRandomCustomer(Company $company): ?Customer
    {
        return Customer::where('company_id', $company->id)
            ->inRandomOrder()
            ->first();
    }

    private function calculateAmount(float $baseAmount, Carbon $date, int $monthOffset): float
    {
        // Growth trend: increase 8% each month (professional services growth faster)
        $growthFactor = 1 + ($monthOffset * 0.08);

        // Seasonal factors for professional services
        $seasonFactor = 1.0;
        if ($date->month == 1 || $date->month == 2) {
            $seasonFactor = 1.3;
        } // New year projects
        elseif ($date->month == 9 || $date->month == 10) {
            $seasonFactor = 1.2;
        } // End of year projects
        elseif ($date->month == 12) {
            $seasonFactor = 0.9;
        } // Holiday slowdown

        // Random variation (±25% - more variation in professional services)
        $randomFactor = 0.75 + (rand(0, 50) / 100);

        return round($baseAmount * $growthFactor * $seasonFactor * $randomFactor, 2);
    }

    private function generateInvoiceNumber(Company $company, Carbon $date): string
    {
        $prefix = 'PS'; // Professional Services prefix
        $year = $date->format('Y');
        $month = $date->format('m');
        $sequence = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$month}-{$sequence}";
    }

    private function selectInvoiceStatus(Carbon $issueDate): string
    {
        $daysSinceIssued = $issueDate->diffInDays(now());

        // Professional services have higher payment rates
        if ($daysSinceIssued > 45 && rand(1, 100) <= 70) {
            return 'overdue';
        }
        if ($daysSinceIssued > 30 && rand(1, 100) <= 85) {
            return 'paid';
        }
        if ($daysSinceIssued > 15 && rand(1, 100) <= 60) {
            return 'sent';
        }

        return 'draft';
    }

    private function selectPaymentMethod(): string
    {
        $methods = [
            'bank_transfer' => 60,  // Higher for professional services
            'credit_card' => 20,
            'cash' => 5,           // Lower cash usage
            'check' => 12,
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
