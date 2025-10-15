<?php

namespace Modules\Accounting\Tests\Feature\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use Modules\Accounting\Domain\Payments\Events\PaymentCreated;
use Modules\Accounting\Domain\Payments\Events\PaymentAllocated;
use Modules\Accounting\Domain\Payments\Events\UnallocatedCashCreated;

class OverpaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user and company with proper tenancy
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        
        // Set company context for RLS
        $this->actingAs($this->user);
        $this->withHeaders(['X-Company-Id' => $this->company->id]);
        
        // Set up test storage for receipts
        Storage::fake('local');
    }

    /** @test */
    public function it_can_handle_overpayment_and_create_unallocated_cash()
    {
        // Arrange
        Event::fake();
        
        $paymentData = [
            'customer_id' => 'test-customer-uuid',
            'payment_method' => 'bank_transfer',
            'amount' => 2000.00,
            'currency' => 'USD',
            'payment_date' => '2025-01-15',
            'reference_number' => 'OVERPAY-001',
            'notes' => 'Overpayment test',
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'payment' => [
                    'id',
                    'payment_number',
                    'amount',
                    'remaining_amount',
                    'is_fully_allocated',
                ],
            ]);

        $paymentId = $response->json('payment.id');

        // Create partial allocation (less than full amount)
        $allocations = [
            [
                'invoice_id' => 'test-invoice-uuid',
                'amount' => 1500.00, // Partial allocation
                'notes' => 'Partial payment from overpayment',
            ],
        ];

        $allocationResponse = $this->postJson("/api/payments/{$paymentId}/allocations", [
            'allocations' => $allocations,
        ]);

        // Assert
        $allocationResponse->assertStatus(201);

        // Verify unallocated cash was tracked
        $this->assertDatabaseHas('invoicing.payments', [
            'id' => $paymentId,
            'amount' => 2000.00,
            'total_allocated' => 1500.00,
            'remaining_amount' => 500.00,
            'is_fully_allocated' => false,
        ]);

        // Verify unallocated cash entry was created
        $this->assertDatabaseHas('invoicing.unallocated_cash', [
            'payment_id' => $paymentId,
            'customer_id' => 'test-customer-uuid',
            'amount' => 500.00,
            'currency' => 'USD',
            'company_id' => $this->company->id,
        ]);

        // Verify events were fired
        Event::assertDispatched(PaymentCreated::class);
        Event::assertDispatched(PaymentAllocated::class);
        Event::assertDispatched(UnallocatedCashCreated::class);
    }

    /** @test */
    public function it_calculates_early_payment_discounts_during_allocation()
    {
        // Arrange
        $payment = $this->createTestPayment(['amount' => 1200.00]);
        
        // Create invoice that's eligible for early payment discount
        $this->createTestInvoice([
            'id' => 'discount-invoice-uuid',
            'invoice_number' => 'INV-2025-001',
            'total_amount' => 1100.00,
            'balance_due' => 1100.00,
            'due_date' => now()->addDays(10), // Due in 10 days
            'early_payment_discount_percent' => 2.0, // 2% discount
            'early_payment_discount_days' => 15,
        ]);

        $allocations = [
            [
                'invoice_id' => 'discount-invoice-uuid',
                'amount' => 1078.00, // 1100 - 2% discount
                'apply_early_payment_discount' => true,
                'notes' => 'Payment with early payment discount',
            ],
        ];

        // Act
        $response = $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => $allocations,
        ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonPath('allocations.0.discount_applied', 22.00)
            ->assertJsonPath('allocations.0.discount_percent', 2.0)
            ->assertJsonPath('allocations.0.original_amount', 1100.00)
            ->assertJsonPath('allocations.0.final_allocated_amount', 1078.00);

        // Verify allocation record includes discount information
        $this->assertDatabaseHas('invoicing.payment_allocations', [
            'payment_id' => $payment->id,
            'invoice_id' => 'discount-invoice-uuid',
            'allocated_amount' => 1078.00,
            'discount_amount' => 22.00,
            'discount_percent' => 2.0,
            'company_id' => $this->company->id,
        ]);

        // Verify remaining amount reflects the discount
        $this->assertDatabaseHas('invoicing.payments', [
            'id' => $payment->id,
            'total_allocated' => 1078.00,
            'remaining_amount' => 122.00, // 1200 - 1078
        ]);
    }

    /** @test */
    public function it_generates_receipt_with_overpayment_and_discount_details()
    {
        // Arrange
        $payment = $this->createTestPayment(['amount' => 1500.00]);
        
        // Create invoice with discount
        $this->createTestInvoice([
            'id' => 'receipt-invoice-uuid',
            'invoice_number' => 'INV-2025-002',
            'total_amount' => 1200.00,
            'balance_due' => 1200.00,
            'due_date' => now()->addDays(5),
            'early_payment_discount_percent' => 3.0,
            'early_payment_discount_days' => 10,
        ]);

        // Apply payment with discount and overpayment
        $allocations = [
            [
                'invoice_id' => 'receipt-invoice-uuid',
                'amount' => 1164.00, // 1200 - 3% discount
                'apply_early_payment_discount' => true,
                'notes' => 'Payment with discount',
            ],
        ];

        $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => $allocations,
        ]);

        // Act - Get receipt as JSON
        $response = $this->getJson("/api/payments/{$payment->id}/receipt?format=json");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'receipt_number',
                'payment_number',
                'payment_date',
                'amount',
                'currency_code',
                'total_allocated',
                'remaining_amount',
                'allocations' => [
                    '*' => [
                        'invoice_number',
                        'allocation_date',
                        'allocated_amount',
                        'discount_amount',
                        'discount_percent',
                        'notes',
                    ],
                ],
                'company_details',
                'customer_details',
                'generated_at',
            ])
            ->assertJsonPath('remaining_amount', 336.00) // 1500 - 1164
            ->assertJsonPath('allocations.0.discount_amount', 36.00)
            ->assertJsonPath('allocations.0.discount_percent', 3.0)
            ->assertJsonPath('allocations.0.allocated_amount', 1164.00);
    }

    /** @test */
    public function it_generates_pdf_receipt_for_payment_with_overpayments()
    {
        // Arrange
        $payment = $this->createTestPayment([
            'amount' => 2000.00,
            'payment_number' => 'PAY-2025-042',
        ]);

        // Create allocation
        $this->createTestInvoice([
            'id' => 'pdf-invoice-uuid',
            'invoice_number' => 'INV-2025-003',
            'total_amount' => 1500.00,
            'balance_due' => 1500.00,
        ]);

        $allocations = [
            [
                'invoice_id' => 'pdf-invoice-uuid',
                'amount' => 1500.00,
                'notes' => 'Full payment',
            ],
        ];

        $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => $allocations,
        ]);

        // Act - Get receipt as PDF
        $response = $this->getJson("/api/payments/{$payment->id}/receipt?format=pdf");

        // Assert
        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/pdf');
        
        // Verify PDF contains key information
        $pdfContent = $response->getContent();
        $this->assertStringContains('PAYMENT RECEIPT', $pdfContent);
        $this->assertStringContains('PAY-2025-042', $pdfContent);
        $this->assertStringContains('$2,000.00', $pdfContent);
        $this->assertStringContains('$500.00', $pdfContent); // Remaining amount
    }

    /** @test */
    public function it_validates_discount_eligibility_rules()
    {
        // Arrange
        $payment = $this->createTestPayment(['amount' => 1000.00]);
        
        // Create invoice that's NOT eligible for discount (past due date)
        $this->createTestInvoice([
            'id' => 'no-discount-invoice-uuid',
            'invoice_number' => 'INV-2025-004',
            'total_amount' => 800.00,
            'balance_due' => 800.00,
            'due_date' => now()->subDays(5), // Overdue
            'early_payment_discount_percent' => 5.0,
            'early_payment_discount_days' => 10,
        ]);

        $allocations = [
            [
                'invoice_id' => 'no-discount-invoice-uuid',
                'amount' => 760.00, // Attempting to apply 5% discount
                'apply_early_payment_discount' => true,
                'notes' => 'Discount attempt on overdue invoice',
            ],
        ];

        // Act
        $response = $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => $allocations,
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations.0.apply_early_payment_discount']);
    }

    /** @test */
    public function it_tracks_unallocated_cash_by_customer_for_future_use()
    {
        // Arrange
        $customerId = 'loyal-customer-uuid';
        
        // First payment with overpayment
        $payment1 = $this->createTestPayment([
            'amount' => 1200.00,
            'customer_id' => $customerId,
        ]);

        $this->postJson("/api/payments/{$payment1->id}/allocations", [
            'allocations' => [
                [
                    'invoice_id' => 'invoice-1-uuid',
                    'amount' => 800.00,
                ],
            ],
        ]);

        // Second payment from same customer with more overpayment
        $payment2 = $this->createTestPayment([
            'amount' => 900.00,
            'customer_id' => $customerId,
            'payment_number' => 'PAY-2025-043',
        ]);

        $this->postJson("/api/payments/{$payment2->id}/allocations", [
            'allocations' => [
                [
                    'invoice_id' => 'invoice-2-uuid',
                    'amount' => 600.00,
                ],
            ],
        ]);

        // Act - Check total unallocated cash for customer
        $response = $this->getJson("/api/customers/{$customerId}/unallocated-cash");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'customer_id' => $customerId,
                'total_unallocated' => 700.00, // 400 + 300
                'currency' => 'USD',
                'payments_count' => 2,
            ]);

        // Verify database records
        $this->assertDatabaseHas('invoicing.unallocated_cash', [
            'payment_id' => $payment1->id,
            'customer_id' => $customerId,
            'amount' => 400.00,
        ]);

        $this->assertDatabaseHas('invoicing.unallocated_cash', [
            'payment_id' => $payment2->id,
            'customer_id' => $customerId,
            'amount' => 300.00,
        ]);
    }

    /** @test */
    public function it_enforces_rls_on_unallocated_cash_queries()
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $customerId = 'test-customer-uuid';

        // Create payment in other company
        $otherPayment = $this->createTestPayment([
            'company_id' => $otherCompany->id,
            'customer_id' => $customerId,
            'amount' => 1000.00,
        ]);

        // Create allocation that generates unallocated cash
        $this->actingAs($otherUser)
            ->withHeaders(['X-Company-Id' => $otherCompany->id])
            ->postJson("/api/payments/{$otherPayment->id}/allocations", [
                'allocations' => [
                    [
                        'invoice_id' => 'other-invoice-uuid',
                        'amount' => 600.00,
                    ],
                ],
            ]);

        // Act - Try to access unallocated cash from different company
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Company-Id' => $this->company->id])
            ->getJson("/api/customers/{$customerId}/unallocated-cash");

        // Assert
        $response->assertStatus(404);
    }

    // Helper methods
    private function createTestPayment(array $overrides = []): \App\Models\Payment
    {
        return \App\Models\Payment::create(array_merge([
            'id' => 'test-payment-' . uniqid(),
            'company_id' => $this->company->id,
            'customer_id' => 'test-customer-uuid',
            'payment_number' => 'PAY-2025-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT),
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'bank_transfer',
            'amount' => 1000.00,
            'currency' => 'USD',
            'status' => 'pending',
            'created_by_user_id' => $this->user->id,
        ], $overrides));
    }

    private function createTestInvoice(array $overrides = []): \App\Models\Invoice
    {
        return \App\Models\Invoice::create(array_merge([
            'id' => 'test-invoice-' . uniqid(),
            'company_id' => $this->company->id,
            'customer_id' => 'test-customer-uuid',
            'invoice_number' => 'INV-2025-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT),
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'total_amount' => 1000.00,
            'balance_due' => 1000.00,
            'currency' => 'USD',
            'status' => 'sent',
            'early_payment_discount_percent' => 0.0,
            'early_payment_discount_days' => 0,
        ], $overrides));
    }
}