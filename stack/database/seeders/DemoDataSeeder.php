<?php

namespace Database\Seeders;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating demo data...');

        DB::beginTransaction();

        try {
            $this->createHospitalityData();
            $this->createRetailData();
            $this->createProfessionalServicesData();

            DB::commit();
            $this->command->info('Demo data created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Demo data creation failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function createHospitalityData(): void
    {
        $company = Company::where('name', 'Grand Hotel Alexandria')->first();
        if (! $company) {
            return;
        }

        $this->command->info('Creating hospitality demo data...');

        // Create customers
        $customers = [
            ['name' => 'Corporate Travel Ltd', 'email' => 'bookings@corptravel.com', 'phone' => '+20 2 2461 2345', 'industry_type' => 'corporate'],
            ['name' => 'John Smith', 'email' => 'john.smith@email.com', 'phone' => '+20 12 3456 7890', 'industry_type' => 'individual'],
            ['name' => 'Egypt Tourism Board', 'email' => 'events@egypttourism.gov.eg', 'phone' => '+20 2 2735 4000', 'industry_type' => 'government'],
            ['name' => 'Sarah Johnson', 'email' => 'sarah.j@email.com', 'phone' => '+20 10 9876 5432', 'industry_type' => 'individual'],
            ['name' => 'Global Events Co', 'email' => 'conferences@globalevents.com', 'phone' => '+20 2 2528 1000', 'industry_type' => 'corporate'],
        ];

        $customerIds = [];
        foreach ($customers as $customerData) {
            $customer = Customer::updateOrCreate(
                ['company_id' => $company->id, 'email' => $customerData['email']],
                array_merge($customerData, [
                    'company_id' => $company->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
            $customerIds[] = $customer->id;
        }

        // Create invoices over 3 months
        $this->createHospitalityInvoices($company, $customerIds);

        $this->command->info('✓ Hospitality demo data created');
    }

    private function createHospitalityInvoices(Company $company, array $customerIds): void
    {
        $invoiceTypes = [
            'room_booking' => ['weight' => 40, 'base_amount' => 1500, 'description_template' => 'Room booking - {nights} nights'],
            'restaurant' => ['weight' => 30, 'base_amount' => 800, 'description_template' => 'Restaurant services - {guests} guests'],
            'event' => ['weight' => 20, 'base_amount' => 5000, 'description_template' => 'Event services - {event_type}'],
            'miscellaneous' => ['weight' => 10, 'base_amount' => 300, 'description_template' => 'Miscellaneous services - {service_type}'],
        ];

        $startDate = Carbon::now()->subMonths(3)->startOfMonth();

        for ($month = 0; $month < 3; $month++) {
            $monthDate = $startDate->copy()->addMonths($month);
            $invoicesThisMonth = 20; // 20 invoices per month

            for ($i = 0; $i < $invoicesThisMonth; $i++) {
                $invoiceType = $this->selectWeightedType($invoiceTypes);
                $customer = $customerIds[array_rand($customerIds)];

                $invoiceDate = $monthDate->copy()->addDays(rand(1, 28));
                $dueDate = $invoiceDate->copy()->addDays(30);

                $amount = $this->generateAmount($invoiceType, $invoiceDate);
                $description = $this->generateDescription($invoiceType);

                $invoice = Invoice::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer,
                    'invoice_number' => $this->generateInvoiceNumber($company, $invoiceDate),
                    'issue_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'total_amount' => $amount,
                    'status' => $this->selectPaymentStatus(),
                    'line_items' => json_encode([[
                        'description' => $description,
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'total' => $amount,
                    ]]),
                    'created_at' => $invoiceDate,
                    'updated_at' => $invoiceDate,
                ]);

                // Create payment for paid invoices
                if ($invoice->status === 'paid' && rand(1, 100) <= 75) { // 75% payment rate
                    Payment::create([
                        'company_id' => $company->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $amount,
                        'payment_date' => $invoiceDate->copy()->addDays(rand(1, 20)),
                        'method' => $this->selectPaymentMethod(),
                        'status' => 'completed',
                        'created_at' => $invoiceDate,
                        'updated_at' => $invoiceDate,
                    ]);
                }
            }
        }
    }

    private function createRetailData(): void
    {
        $company = Company::where('name', 'RetailMart Egypt')->first();
        if (! $company) {
            return;
        }

        $this->command->info('Creating retail demo data...');

        // Create customers
        $customers = [
            ['name' => 'B2B Wholesale Ltd', 'email' => 'wholesale@b2b-egypt.com', 'phone' => '+20 2 2734 5678', 'industry_type' => 'wholesale'],
            ['name' => 'Mariam Ahmed', 'email' => 'mariam.ahmed@email.com', 'phone' => '+20 11 2345 6789', 'industry_type' => 'retail'],
            ['name' => 'Cairo Distributors', 'email' => 'orders@cairodistributors.com', 'phone' => '+20 2 2842 9100', 'industry_type' => 'distribution'],
            ['name' => 'Omar Khalid', 'email' => 'omar.k@email.com', 'phone' => '+20 10 8765 4321', 'industry_type' => 'retail'],
            ['name' => 'Alexandria Retail Group', 'email' => 'purchasing@alexretail.com', 'phone' => '+20 3 4861 2345', 'industry_type' => 'retail'],
        ];

        $customerIds = [];
        foreach ($customers as $customerData) {
            $customer = Customer::updateOrCreate(
                ['company_id' => $company->id, 'email' => $customerData['email']],
                array_merge($customerData, [
                    'company_id' => $company->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
            $customerIds[] = $customer->id;
        }

        // Create invoices over 3 months
        $this->createRetailInvoices($company, $customerIds);

        $this->command->info('✓ Retail demo data created');
    }

    private function createRetailInvoices(Company $company, array $customerIds): void
    {
        $invoiceTypes = [
            'product_sales' => ['weight' => 70, 'base_amount' => 2500, 'description_template' => 'Product sales - {category}'],
            'returns' => ['weight' => 15, 'base_amount' => -800, 'description_template' => 'Product returns - {reason}'],
            'bulk_orders' => ['weight' => 10, 'base_amount' => 15000, 'description_template' => 'Bulk order - {quantity} units'],
            'services' => ['weight' => 5, 'base_amount' => 500, 'description_template' => 'Service charges - {service_type}'],
        ];

        $startDate = Carbon::now()->subMonths(3)->startOfMonth();

        for ($month = 0; $month < 3; $month++) {
            $monthDate = $startDate->copy()->addMonths($month);
            $invoicesThisMonth = 30; // 30 invoices per month

            for ($i = 0; $i < $invoicesThisMonth; $i++) {
                $invoiceType = $this->selectWeightedType($invoiceTypes);
                $customer = $customerIds[array_rand($customerIds)];

                $invoiceDate = $monthDate->copy()->addDays(rand(1, 28));
                $dueDate = $invoiceDate->copy()->addDays(30);

                $amount = $this->generateAmount($invoiceType, $invoiceDate);
                $description = $this->generateDescription($invoiceType);

                $invoice = Invoice::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer,
                    'invoice_number' => $this->generateInvoiceNumber($company, $invoiceDate),
                    'issue_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'total_amount' => $amount,
                    'status' => $this->selectPaymentStatus(),
                    'line_items' => json_encode([[
                        'description' => $description,
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'total' => $amount,
                    ]]),
                    'created_at' => $invoiceDate,
                    'updated_at' => $invoiceDate,
                ]);

                // Create payment for paid invoices
                if ($invoice->status === 'paid' && rand(1, 100) <= 78) { // 78% payment rate
                    Payment::create([
                        'company_id' => $company->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $amount,
                        'payment_date' => $invoiceDate->copy()->addDays(rand(1, 25)),
                        'method' => $this->selectPaymentMethod(),
                        'status' => 'completed',
                        'created_at' => $invoiceDate,
                        'updated_at' => $invoiceDate,
                    ]);
                }
            }
        }
    }

    private function createProfessionalServicesData(): void
    {
        $company = Company::where('name', 'ConsultPro Solutions')->first();
        if (! $company) {
            return;
        }

        $this->command->info('Creating professional services demo data...');

        // Create customers
        $customers = [
            ['name' => 'Ministry of Technology', 'email' => 'projects@mot.gov.eg', 'phone' => '+20 2 2742 1000', 'industry_type' => 'government'],
            ['name' => 'Cairo University', 'email' => 'consulting@cu.edu.eg', 'phone' => '+20 2 3567 2000', 'industry_type' => 'education'],
            ['name' => 'Nile Bank', 'email' => 'it.consulting@nilebank.com', 'phone' => '+20 2 2780 9000', 'industry_type' => 'banking'],
            ['name' => 'Egypt Telecom', 'email' => 'projects@telecom.eg', 'phone' => '+20 2 2720 5000', 'industry_type' => 'telecom'],
            ['name' => 'Alexandria Port Authority', 'email' => 'consulting@apaport.com', 'phone' => '+20 3 4801 0000', 'industry_type' => 'government'],
        ];

        $customerIds = [];
        foreach ($customers as $customerData) {
            $customer = Customer::updateOrCreate(
                ['company_id' => $company->id, 'email' => $customerData['email']],
                array_merge($customerData, [
                    'company_id' => $company->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
            $customerIds[] = $customer->id;
        }

        // Create invoices over 3 months
        $this->createProfessionalServicesInvoices($company, $customerIds);

        $this->command->info('✓ Professional services demo data created');
    }

    private function createProfessionalServicesInvoices(Company $company, array $customerIds): void
    {
        $invoiceTypes = [
            'hourly_billing' => ['weight' => 50, 'base_amount' => 8000, 'description_template' => 'Consulting services - {hours} hours'],
            'project_milestones' => ['weight' => 30, 'base_amount' => 25000, 'description_template' => 'Project milestone - {milestone}'],
            'retainers' => ['weight' => 15, 'base_amount' => 15000, 'description_template' => 'Monthly retainer - {period}'],
            'expenses' => ['weight' => 5, 'base_amount' => 2000, 'description_template' => 'Pass-through expenses - {expense_type}'],
        ];

        $startDate = Carbon::now()->subMonths(3)->startOfMonth();

        for ($month = 0; $month < 3; $month++) {
            $monthDate = $startDate->copy()->addMonths($month);
            $invoicesThisMonth = 12; // 12 invoices per month

            for ($i = 0; $i < $invoicesThisMonth; $i++) {
                $invoiceType = $this->selectWeightedType($invoiceTypes);
                $customer = $customerIds[array_rand($customerIds)];

                $invoiceDate = $monthDate->copy()->addDays(rand(1, 28));
                $dueDate = $invoiceDate->copy()->addDays(30);

                $amount = $this->generateAmount($invoiceType, $invoiceDate);
                $description = $this->generateDescription($invoiceType);

                $invoice = Invoice::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer,
                    'invoice_number' => $this->generateInvoiceNumber($company, $invoiceDate),
                    'issue_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'total_amount' => $amount,
                    'status' => $this->selectPaymentStatus(),
                    'line_items' => json_encode([[
                        'description' => $description,
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'total' => $amount,
                    ]]),
                    'created_at' => $invoiceDate,
                    'updated_at' => $invoiceDate,
                ]);

                // Create payment for paid invoices
                if ($invoice->status === 'paid' && rand(1, 100) <= 94) { // 94% payment rate
                    Payment::create([
                        'company_id' => $company->id,
                        'invoice_id' => $invoice->id,
                        'amount' => $amount,
                        'payment_date' => $invoiceDate->copy()->addDays(rand(1, 15)),
                        'method' => $this->selectPaymentMethod(),
                        'status' => 'completed',
                        'created_at' => $invoiceDate,
                        'updated_at' => $invoiceDate,
                    ]);
                }
            }
        }
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

    private function generateAmount(array $type, Carbon $date): float
    {
        $base = $type['base_amount'];

        // Add seasonal variation
        $month = $date->month;
        $seasonalFactor = 1.0;

        if (in_array($month, [11, 12, 1])) { // Holiday season
            $seasonalFactor = 1.2;
        } elseif (in_array($month, [6, 7, 8])) { // Summer
            $seasonalFactor = 0.9;
        }

        // Add random variation (±20%)
        $randomFactor = 0.8 + (rand(0, 40) / 100);

        return round($base * $seasonalFactor * $randomFactor, 2);
    }

    private function generateDescription(string $type): string
    {
        $templates = [
            'room_booking' => ['Room booking - {nights} nights', 'Deluxe suite - {nights} nights', 'Standard room - {nights} nights'],
            'restaurant' => ['Restaurant services - {guests} guests', 'Catering event - {event_type}', 'Room service - {period}'],
            'event' => ['Conference facilities - {event_type}', 'Wedding reception - {guests} guests', 'Corporate meeting - {duration}'],
            'miscellaneous' => ['Laundry services', 'Parking fees', 'Spa services', 'Airport transfer'],
            'product_sales' => ['Electronics - {category}', 'Clothing - {category}', 'Home goods - {category}'],
            'returns' => ['Defective products - {reason}', 'Customer returns - {reason}', 'Overstock returns'],
            'bulk_orders' => ['Bulk purchase - {quantity} units', 'Wholesale order - {quantity} units', 'Large quantity order'],
            'services' => ['Delivery charges', 'Installation services', 'Extended warranty'],
            'hourly_billing' => ['Strategic consulting - {hours} hours', 'Technical support - {hours} hours', 'Training services - {hours} hours'],
            'project_milestones' => ['Phase {phase} completion', 'Deliverable {deliverable}', 'Project milestone - {milestone}'],
            'retainers' => ['Monthly consulting retainer', 'Support retainer - {period}', 'Advisory services retainer'],
            'expenses' => ['Travel expenses - {expense_type}', 'Software licenses', 'Research materials'],
        ];

        $typeTemplates = $templates[$type] ?? ['Service provided'];

        return $typeTemplates[array_rand($typeTemplates)];
    }

    private function generateInvoiceNumber(Company $company, Carbon $date): string
    {
        $year = $date->format('Y');
        $month = $date->format('m');
        $sequence = rand(100, 999);

        return "{$year}-{$month}-{$sequence}";
    }

    private function selectPaymentStatus(): string
    {
        $statuses = ['draft' => 5, 'sent' => 15, 'paid' => 75, 'overdue' => 4, 'void' => 1];

        $totalWeight = array_sum($statuses);
        $random = rand(1, $totalWeight);
        $currentWeight = 0;

        foreach ($statuses as $status => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $status;
            }
        }

        return 'sent';
    }

    private function selectPaymentMethod(): string
    {
        $methods = ['bank_transfer', 'credit_card', 'cash', 'check', 'mobile_payment'];

        return $methods[array_rand($methods)];
    }
}
