<?php

namespace Tests\Feature\Accounting\Customers;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Domain\Customers\Actions\GenerateCustomerStatementAction;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerAgingSnapshot;
use Modules\Accounting\Domain\Customers\Models\CustomerStatement;
use Modules\Accounting\Domain\Customers\Services\CustomerStatementService;
use Tests\TestCase;

class GenerateCustomerStatementTest extends TestCase
{
    use RefreshDatabase;

    private GenerateCustomerStatementAction $action;

    private CustomerStatementService $statementService;

    private Company $company;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->action = app(GenerateCustomerStatementAction::class);
        $this->statementService = app(CustomerStatementService::class);
        $this->company = Company::factory()->create();
        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'default_currency' => 'USD',
        ]);
    }

    /** @test */
    public function it_generates_statement_for_customer_with_no_activity()
    {
        $periodStart = now()->subDays(30)->startOfDay();
        $periodEnd = now()->startOfDay();

        $statement = $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );

        $this->assertInstanceOf(CustomerStatement::class, $statement);
        $this->assertEquals($this->customer->id, $statement->customer_id);
        $this->assertEquals($this->company->id, $statement->company_id);
        $this->assertEquals($periodStart->format('Y-m-d'), $statement->period_start->format('Y-m-d'));
        $this->assertEquals($periodEnd->format('Y-m-d'), $statement->period_end->format('Y-m-d'));
        $this->assertEquals(0.0, $statement->opening_balance);
        $this->assertEquals(0.0, $statement->total_invoiced);
        $this->assertEquals(0.0, $statement->total_paid);
        $this->assertEquals(0.0, $statement->total_credit_notes);
        $this->assertEquals(0.0, $statement->closing_balance);
        $this->assertNotNull($statement->document_path);
        $this->assertNotNull($statement->checksum);
    }

    /** @test */
    public function it_generates_statement_with_invoices_and_payments()
    {
        // Create opening balance invoice (before period)
        $openingInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 2000.00,
            'balance_due' => 500.00,
            'issue_date' => now()->subDays(45),
            'due_date' => now()->subDays(15),
            'status' => 'sent',
        ]);

        // Create invoices within period
        $periodInvoice1 = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 3000.00,
            'balance_due' => 3000.00,
            'issue_date' => now()->subDays(25),
            'due_date' => now()->addDays(5),
            'status' => 'sent',
        ]);

        $periodInvoice2 = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 1500.00,
            'balance_due' => 1500.00,
            'issue_date' => now()->subDays(10),
            'due_date' => now()->addDays(20),
            'status' => 'sent',
        ]);

        // Create payment within period
        $payment = Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'amount' => 1000.00,
            'payment_date' => now()->subDays(5),
            'status' => 'completed',
        ]);

        $periodStart = now()->subDays(30)->startOfDay();
        $periodEnd = now()->startOfDay();

        $statement = $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );

        $this->assertEquals(500.0, $statement->opening_balance);
        $this->assertEquals(4500.0, $statement->total_invoiced); // 3000 + 1500
        $this->assertEquals(1000.0, $statement->total_paid);
        $this->assertEquals(0.0, $statement->total_credit_notes);
        $this->assertEquals(4000.0, $statement->closing_balance); // 500 + 4500 - 1000
    }

    /** @test */
    public function it_includes_credit_notes_in_statement_calculations()
    {
        // Create invoice within period
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 3000.00,
            'balance_due' => 3000.00,
            'issue_date' => now()->subDays(20),
            'status' => 'sent',
        ]);

        // Create credit note within period
        $creditNote = CreditNote::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 500.00,
            'issue_date' => now()->subDays(15),
            'status' => 'sent',
        ]);

        $periodStart = now()->subDays(30)->startOfDay();
        $periodEnd = now()->startOfDay();

        $statement = $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );

        $this->assertEquals(3000.0, $statement->total_invoiced);
        $this->assertEquals(500.0, $statement->total_credit_notes);
        $this->assertEquals(2500.0, $statement->closing_balance);
    }

    /** @test */
    public function it_creates_aging_snapshot_when_generating_statement()
    {
        // Create some aging invoices
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 2000.00,
            'balance_due' => 2000.00,
            'issue_date' => now()->subDays(65),
            'due_date' => now()->subDays(35),
            'status' => 'sent',
        ]);

        $periodStart = now()->subDays(30)->startOfDay();
        $periodEnd = now()->startOfDay();

        $statement = $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );

        // Verify aging snapshot was created
        $snapshot = CustomerAgingSnapshot::where('customer_id', $this->customer->id)
            ->where('snapshot_date', $periodEnd->format('Y-m-d'))
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertEquals($this->company->id, $snapshot->company_id);
        $this->assertEquals(0.0, $snapshot->bucket_current);
        $this->assertEquals(0.0, $snapshot->bucket_1_30);
        $this->assertEquals(2000.0, $snapshot->bucket_31_60);
        $this->assertEquals(0.0, $snapshot->bucket_61_90);
        $this->assertEquals(0.0, $snapshot->bucket_90_plus);
        $this->assertEquals('on_demand', $snapshot->generated_via);
        $this->assertEquals($this->customer->created_by_user_id, $snapshot->generated_by_user_id);

        // Verify statement references the aging summary
        $this->assertNotNull($statement->aging_bucket_summary);
        $this->assertIsArray($statement->aging_bucket_summary);
        $this->assertEquals(2000.0, $statement->aging_bucket_summary['bucket_31_60']);
    }

    /** @test */
    public function it_generates_pdf_document_for_statement()
    {
        $periodStart = now()->subDays(30)->startOfDay();
        $periodEnd = now()->startOfDay();

        $statement = $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            [
                'generated_by_user_id' => $this->customer->created_by_user_id,
                'format' => 'pdf',
            ]
        );

        $this->assertNotNull($statement->document_path);
        $this->assertStringContains('.pdf', $statement->document_path);

        // Verify file exists in storage
        $this->assertTrue(Storage::exists($statement->document_path));

        // Verify file is not empty
        $this->assertGreaterThan(0, Storage::size($statement->document_path));
    }

    /** @test */
    public function it_generates_csv_document_for_statement()
    {
        $periodStart = now()->subDays(30)->startOfDay();
        $periodEnd = now()->startOfDay();

        $statement = $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            [
                'generated_by_user_id' => $this->customer->created_by_user_id,
                'format' => 'csv',
            ]
        );

        $this->assertNotNull($statement->document_path);
        $this->assertStringContains('.csv', $statement->document_path);

        // Verify file exists in storage
        $this->assertTrue(Storage::exists($statement->document_path));

        // Verify CSV content
        $content = Storage::get($statement->document_path);
        $this->assertStringContains('Customer Statement', $content);
        $this->assertStringContains($this->customer->name, $content);
    }

    /** @test */
    public function it_prevents_duplicate_statements_for_same_period()
    {
        $periodStart = now()->subDays(30)->startOfDay();
        $periodEnd = now()->startOfDay();

        // Generate first statement
        $statement1 = $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );

        // Try to generate duplicate statement
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Statement already exists for this period');

        $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );
    }

    /** @test */
    public function it_calculates_opening_balance_from_previous_period()
    {
        // Create invoice from long ago
        $oldInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 5000.00,
            'balance_due' => 2000.00,
            'issue_date' => now()->subDays(100),
            'due_date' => now()->subDays(70),
            'status' => 'sent',
        ]);

        // Generate statement for previous period
        $previousPeriodStart = now()->subDays(60)->startOfDay();
        $previousPeriodEnd = now()->subDays(31)->startOfDay();

        $previousStatement = $this->action->execute(
            $this->customer,
            $previousPeriodStart,
            $previousPeriodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );

        // Generate statement for current period
        $currentPeriodStart = now()->subDays(30)->startOfDay();
        $currentPeriodEnd = now()->startOfDay();

        $currentStatement = $this->action->execute(
            $this->customer,
            $currentPeriodStart,
            $currentPeriodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );

        // Opening balance should equal previous statement's closing balance
        $this->assertEquals($previousStatement->closing_balance, $currentStatement->opening_balance);
    }

    /** @test */
    public function it_enforces_company_isolation_for_statements()
    {
        $otherCompany = Company::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'company_id' => $otherCompany->id,
            'default_currency' => 'USD',
        ]);

        // Create invoice for our customer
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'company_id' => $this->company->id,
            'total' => 3000.00,
            'balance_due' => 3000.00,
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

        $periodStart = now()->subDays(30)->startOfDay();
        $periodEnd = now()->startOfDay();

        $statement = $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );

        // Should only include invoices from our company
        $this->assertEquals(3000.0, $statement->total_invoiced);
        $this->assertEquals($this->company->id, $statement->company_id);
    }

    /** @test */
    public function it_validates_period_date_ranges()
    {
        $periodStart = now()->startOfDay();
        $periodEnd = now()->subDays(10)->startOfDay(); // End before start

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Period end date must be after or equal to start date');

        $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );
    }

    /** @test */
    public function it_limits_period_to_maximum_one_year()
    {
        $periodStart = now()->subDays(400)->startOfDay();
        $periodEnd = now()->startOfDay(); // More than 1 year

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Statement period cannot exceed 365 days');

        $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            ['generated_by_user_id' => $this->customer->created_by_user_id]
        );
    }

    /** @test */
    public function it_includes_audit_metadata_in_statement()
    {
        $periodStart = now()->subDays(30)->startOfDay();
        $periodEnd = now()->startOfDay();
        $userId = $this->customer->created_by_user_id;

        $statement = $this->action->execute(
            $this->customer,
            $periodStart,
            $periodEnd,
            [
                'generated_by_user_id' => $userId,
                'approval_reference' => 'REQ-001',
                'reason' => 'Monthly statement request',
            ]
        );

        $this->assertEquals($userId, $statement->generated_by_user_id);
        $this->assertNotNull($statement->generated_at);
        $this->assertNotNull($statement->checksum);
        $this->assertNotEmpty($statement->checksum);

        // Verify checksum includes key data
        $expectedChecksumData = [
            'customer_id' => $this->customer->id,
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'opening_balance' => $statement->opening_balance,
            'total_invoiced' => $statement->total_invoiced,
            'total_paid' => $statement->total_paid,
            'total_credit_notes' => $statement->total_credit_notes,
            'closing_balance' => $statement->closing_balance,
        ];

        $this->assertStringContains(
            hash('sha256', json_encode($expectedChecksumData)),
            $statement->checksum
        );
    }
}
