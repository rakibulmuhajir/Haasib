<?php

namespace Tests\Unit\Accounting\Customers;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Services\CustomerAgingService;
use Tests\TestCase;

class CustomerAgingServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomerAgingService $agingService;

    private Company $company;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agingService = app(CustomerAgingService::class);
        $this->company = Company::factory()->create();
        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'default_currency' => 'USD',
        ]);
    }

    /** @test */
    public function it_calculates_aging_buckets_for_customer_with_no_invoices()
    {
        $result = $this->agingService->calculateAgingBuckets($this->customer);

        $this->assertEquals(0.0, $result['bucket_current']);
        $this->assertEquals(0.0, $result['bucket_1_30']);
        $this->assertEquals(0.0, $result['bucket_31_60']);
        $this->assertEquals(0.0, $result['bucket_61_90']);
        $this->assertEquals(0.0, $result['bucket_90_plus']);
        $this->assertEquals(0.0, $result['total_outstanding']);
        $this->assertEquals(0, $result['total_invoices']);
    }

    /** @test */
    public function it_calculates_current_bucket_for_recent_invoices()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 1000.00,
            'balance_due' => 1000.00,
            'issue_date' => now()->subDays(5),
            'due_date' => now()->addDays(25),
            'status' => 'sent',
        ]);

        $result = $this->agingService->calculateAgingBuckets($this->customer);

        $this->assertEquals(1000.0, $result['bucket_current']);
        $this->assertEquals(0.0, $result['bucket_1_30']);
        $this->assertEquals(0.0, $result['bucket_31_60']);
        $this->assertEquals(0.0, $result['bucket_61_90']);
        $this->assertEquals(0.0, $result['bucket_90_plus']);
        $this->assertEquals(1000.0, $result['total_outstanding']);
        $this->assertEquals(1, $result['total_invoices']);
    }

    /** @test */
    public function it_calculates_1_30_bucket_for_slightly_overdue_invoices()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 1500.00,
            'balance_due' => 1500.00,
            'issue_date' => now()->subDays(35),
            'due_date' => now()->subDays(5),
            'status' => 'sent',
        ]);

        $result = $this->agingService->calculateAgingBuckets($this->customer);

        $this->assertEquals(0.0, $result['bucket_current']);
        $this->assertEquals(1500.0, $result['bucket_1_30']);
        $this->assertEquals(0.0, $result['bucket_31_60']);
        $this->assertEquals(0.0, $result['bucket_61_90']);
        $this->assertEquals(0.0, $result['bucket_90_plus']);
        $this->assertEquals(1500.0, $result['total_outstanding']);
        $this->assertEquals(1, $result['total_invoices']);
    }

    /** @test */
    public function it_calculates_31_60_bucket_for_modately_overdue_invoices()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 2000.00,
            'balance_due' => 2000.00,
            'issue_date' => now()->subDays(65),
            'due_date' => now()->subDays(35),
            'status' => 'sent',
        ]);

        $result = $this->agingService->calculateAgingBuckets($this->customer);

        $this->assertEquals(0.0, $result['bucket_current']);
        $this->assertEquals(0.0, $result['bucket_1_30']);
        $this->assertEquals(2000.0, $result['bucket_31_60']);
        $this->assertEquals(0.0, $result['bucket_61_90']);
        $this->assertEquals(0.0, $result['bucket_90_plus']);
        $this->assertEquals(2000.0, $result['total_outstanding']);
        $this->assertEquals(1, $result['total_invoices']);
    }

    /** @test */
    public function it_calculates_61_90_bucket_for_significantly_overdue_invoices()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 2500.00,
            'balance_due' => 2500.00,
            'issue_date' => now()->subDays(95),
            'due_date' => now()->subDays(65),
            'status' => 'sent',
        ]);

        $result = $this->agingService->calculateAgingBuckets($this->customer);

        $this->assertEquals(0.0, $result['bucket_current']);
        $this->assertEquals(0.0, $result['bucket_1_30']);
        $this->assertEquals(0.0, $result['bucket_31_60']);
        $this->assertEquals(2500.0, $result['bucket_61_90']);
        $this->assertEquals(0.0, $result['bucket_90_plus']);
        $this->assertEquals(2500.0, $result['total_outstanding']);
        $this->assertEquals(1, $result['total_invoices']);
    }

    /** @test */
    public function it_calculates_90_plus_bucket_for_severely_overdue_invoices()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 3000.00,
            'balance_due' => 3000.00,
            'issue_date' => now()->subDays(125),
            'due_date' => now()->subDays(95),
            'status' => 'sent',
        ]);

        $result = $this->agingService->calculateAgingBuckets($this->customer);

        $this->assertEquals(0.0, $result['bucket_current']);
        $this->assertEquals(0.0, $result['bucket_1_30']);
        $this->assertEquals(0.0, $result['bucket_31_60']);
        $this->assertEquals(0.0, $result['bucket_61_90']);
        $this->assertEquals(3000.0, $result['bucket_90_plus']);
        $this->assertEquals(3000.0, $result['total_outstanding']);
        $this->assertEquals(1, $result['total_invoices']);
    }

    /** @test */
    public function it_handles_mixed_invoices_across_multiple_buckets()
    {
        $invoices = [
            Invoice::factory()->create([
                'customer_id' => $this->customer->id,
                'company_id' => $this->company->id,
                'total' => 1000.00,
                'balance_due' => 1000.00,
                'issue_date' => now()->subDays(5),
                'due_date' => now()->addDays(25),
                'status' => 'sent',
            ]),
            Invoice::factory()->create([
                'customer_id' => $this->customer->id,
                'company_id' => $this->company->id,
                'total' => 1500.00,
                'balance_due' => 1500.00,
                'issue_date' => now()->subDays(35),
                'due_date' => now()->subDays(5),
                'status' => 'sent',
            ]),
            Invoice::factory()->create([
                'customer_id' => $this->customer->id,
                'company_id' => $this->company->id,
                'total' => 2000.00,
                'balance_due' => 2000.00,
                'issue_date' => now()->subDays(95),
                'due_date' => now()->subDays(65),
                'status' => 'sent',
            ]),
        ];

        $result = $this->agingService->calculateAgingBuckets($this->customer);

        $this->assertEquals(1000.0, $result['bucket_current']);
        $this->assertEquals(1500.0, $result['bucket_1_30']);
        $this->assertEquals(0.0, $result['bucket_31_60']);
        $this->assertEquals(2000.0, $result['bucket_61_90']);
        $this->assertEquals(0.0, $result['bucket_90_plus']);
        $this->assertEquals(4500.0, $result['total_outstanding']);
        $this->assertEquals(3, $result['total_invoices']);
    }

    /** @test */
    public function it_excludes_paid_invoices_from_aging_calculations()
    {
        $paidInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 1000.00,
            'balance_due' => 0.00, // Fully paid
            'issue_date' => now()->subDays(35),
            'due_date' => now()->subDays(5),
            'status' => 'paid',
        ]);

        $unpaidInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 2000.00,
            'balance_due' => 2000.00,
            'issue_date' => now()->subDays(65),
            'due_date' => now()->subDays(35),
            'status' => 'sent',
        ]);

        $result = $this->agingService->calculateAgingBuckets($this->customer);

        $this->assertEquals(0.0, $result['bucket_current']);
        $this->assertEquals(0.0, $result['bucket_1_30']);
        $this->assertEquals(2000.0, $result['bucket_31_60']);
        $this->assertEquals(0.0, $result['bucket_61_90']);
        $this->assertEquals(0.0, $result['bucket_90_plus']);
        $this->assertEquals(2000.0, $result['total_outstanding']);
        $this->assertEquals(1, $result['total_invoices']); // Only unpaid invoices counted
    }

    /** @test */
    public function it_applies_partial_payments_correctly()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 3000.00,
            'balance_due' => 1000.00, // Partially paid
            'issue_date' => now()->subDays(65),
            'due_date' => now()->subDays(35),
            'status' => 'sent',
        ]);

        $result = $this->agingService->calculateAgingBuckets($this->customer);

        $this->assertEquals(0.0, $result['bucket_current']);
        $this->assertEquals(0.0, $result['bucket_1_30']);
        $this->assertEquals(1000.0, $result['bucket_31_60']); // Only balance due counts
        $this->assertEquals(0.0, $result['bucket_61_90']);
        $this->assertEquals(0.0, $result['bucket_90_plus']);
        $this->assertEquals(1000.0, $result['total_outstanding']);
        $this->assertEquals(1, $result['total_invoices']);
    }

    /** @test */
    public function it_filters_invoices_by_company_scope()
    {
        $otherCompany = Company::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'company_id' => $otherCompany->id,
            'default_currency' => 'USD',
        ]);

        // Create invoice for our customer
        $validInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 1000.00,
            'balance_due' => 1000.00,
            'status' => 'sent',
        ]);

        // Create invoice for other company's customer
        $otherInvoice = Invoice::factory()->create([
            'customer_id' => $otherCustomer->id,
            'company_id' => $otherCompany->id,
            'total' => 5000.00,
            'balance_due' => 5000.00,
            'status' => 'sent',
        ]);

        $result = $this->agingService->calculateAgingBuckets($this->customer);

        // Should only include invoices from our company
        $this->assertEquals(1000.0, $result['total_outstanding']);
        $this->assertEquals(1, $result['total_invoices']);
    }

    /** @test */
    public function it_provides_currency_specific_aging_calculations()
    {
        $customerEUR = Customer::factory()->create([
            'company_id' => $this->company->id,
            'default_currency' => 'EUR',
        ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customerEUR->id,
            'company_id' => $this->company->id,
            'total' => 1500.00,
            'balance_due' => 1500.00,
            'currency' => 'EUR',
            'status' => 'sent',
        ]);

        $result = $this->agingService->calculateAgingBuckets($customerEUR);

        $this->assertEquals(1500.0, $result['bucket_current']);
        $this->assertEquals('EUR', $result['currency']);
        $this->assertEquals(1500.0, $result['total_outstanding']);
    }

    /** @test */
    public function it_handles_date_filtering_for_aging_reports()
    {
        $oldInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 2000.00,
            'balance_due' => 2000.00,
            'issue_date' => now()->subDays(150),
            'due_date' => now()->subDays(120),
            'status' => 'sent',
        ]);

        $newInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 1000.00,
            'balance_due' => 1000.00,
            'issue_date' => now()->subDays(5),
            'due_date' => now()->addDays(25),
            'status' => 'sent',
        ]);

        $asOfDate = now()->subDays(30);
        $result = $this->agingService->calculateAgingBuckets($this->customer, $asOfDate);

        // As of 30 days ago, the old invoice should be in a different bucket
        $this->assertGreaterThan(0, $result['total_outstanding']);
        $this->assertEquals(2, $result['total_invoices']);
    }
}
