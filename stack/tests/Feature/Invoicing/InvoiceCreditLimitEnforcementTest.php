<?php

namespace Tests\Feature\Invoicing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Services\CustomerCreditService;
use Modules\Invoicing\Services\InvoiceService;
use Tests\TestCase;

class InvoiceCreditLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private InvoiceService $invoiceService;

    private CustomerCreditService $creditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->customer = Customer::factory()->create([
            'credit_limit' => 1000.00,
            'credit_limit_effective_at' => now(),
            'status' => 'active',
        ]);

        $this->invoiceService = app(InvoiceService::class);
        $this->creditService = app(CustomerCreditService::class);
    }

    /** @test */
    public function it_allows_invoice_creation_within_credit_limit()
    {
        // Arrange
        $invoiceData = $this->createInvoiceData(500.00); // Within limit

        // Act
        $result = $this->invoiceService->createInvoice($invoiceData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('invoice', $result);
    }

    /** @test */
    public function it_blocks_invoice_creation_exceeding_credit_limit()
    {
        // Arrange
        $invoiceData = $this->createInvoiceData(1500.00); // Exceeds 1000 limit

        // Act
        $result = $this->invoiceService->createInvoice($invoiceData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('credit_limit_exceeded', $result['error_code']);
        $this->assertStringContains('credit limit', $result['message']);
    }

    /** @test */
    public function it_considers_existing_outstanding_invoices_when_checking_credit_limit()
    {
        // Arrange - Create existing invoice
        $existingInvoiceData = $this->createInvoiceData(600.00);
        $this->invoiceService->createInvoice($existingInvoiceData);

        // New invoice would exceed limit when combined with existing
        $newInvoiceData = $this->createInvoiceData(500.00); // 600 + 500 = 1100 > 1000

        // Act
        $result = $this->invoiceService->createInvoice($newInvoiceData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('credit_limit_exceeded', $result['error_code']);
    }

    /** @test */
    public function it_calculates_correct_exposure_amount()
    {
        // Arrange
        $invoices = [
            $this->createInvoiceData(300.00),
            $this->createInvoiceData(400.00),
        ];

        foreach ($invoices as $invoiceData) {
            $this->invoiceService->createInvoice($invoiceData);
        }

        // Act
        $exposure = $this->creditService->calculateExposure($this->customer);

        // Assert
        $this->assertEquals(700.00, $exposure);
    }

    /** @test */
    public function it_handles_override_with_proper_authorization()
    {
        // Arrange
        $userWithOverride = User::factory()->create();
        $userWithOverride->givePermissionTo('accounting.customers.override_credit_limits');
        $this->actingAs($userWithOverride);

        $invoiceData = $this->createInvoiceData(1500.00);
        $invoiceData['override_credit_limit'] = true;
        $invoiceData['override_reason'] = 'Urgent customer requirement';

        // Act
        $result = $this->invoiceService->createInvoice($invoiceData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('invoice', $result);

        // Verify audit log for override
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'credit_limit_override',
            'subject_type' => Customer::class,
            'subject_id' => $this->customer->id,
            'user_id' => $userWithOverride->id,
        ]);
    }

    /** @test */
    public function it_blocks_override_without_proper_authorization()
    {
        // Arrange
        $invoiceData = $this->createInvoiceData(1500.00);
        $invoiceData['override_credit_limit'] = true;
        $invoiceData['override_reason'] = 'Trying to bypass limit';

        // Act
        $result = $this->invoiceService->createInvoice($invoiceData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('insufficient_permissions', $result['error_code']);
        $this->assertStringContains('permission', $result['message']);
    }

    /** @test */
    public function it_handles_inactive_customer_credit_enforcement()
    {
        // Arrange
        $this->customer->update(['status' => 'blocked']);
        $invoiceData = $this->createInvoiceData(100.00); // Would normally be allowed

        // Act
        $result = $this->invoiceService->createInvoice($invoiceData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('customer_blocked', $result['error_code']);
    }

    /** @test */
    public function it_handles_customers_with_no_credit_limit()
    {
        // Arrange
        $this->customer->update(['credit_limit' => null]);
        $invoiceData = $this->createInvoiceData(5000.00); // Would normally exceed limits

        // Act
        $result = $this->invoiceService->createInvoice($invoiceData);

        // Assert
        $this->assertTrue($result['success']); // No limit means unlimited credit
        $this->assertArrayHasKey('invoice', $result);
    }

    /** @test */
    public function it_provides_detailed_error_information()
    {
        // Arrange
        // Create existing exposure
        $existingInvoiceData = $this->createInvoiceData(800.00);
        $this->invoiceService->createInvoice($existingInvoiceData);

        $newInvoiceData = $this->createInvoiceData(500.00); // Would make total 1300

        // Act
        $result = $this->invoiceService->createInvoice($newInvoiceData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('credit_limit_exceeded', $result['error_code']);
        $this->assertArrayHasKey('details', $result);

        $details = $result['details'];
        $this->assertEquals(1000.00, $details['credit_limit']);
        $this->assertEquals(800.00, $details['current_exposure']);
        $this->assertEquals(500.00, $details['invoice_amount']);
        $this->assertEquals(1300.00, $details['total_exposure']);
        $this->assertEquals(300.00, $details['excess_amount']);
    }

    private function createInvoiceData(float $amount): array
    {
        return [
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-'.uniqid(),
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total' => $amount,
                ],
            ],
            'total' => $amount,
            'subtotal' => $amount,
            'tax_amount' => 0,
            'company_id' => $this->customer->company_id,
            'created_by_user_id' => $this->user->id,
        ];
    }
}
