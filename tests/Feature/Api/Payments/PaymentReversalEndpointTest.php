<?php

namespace Tests\Feature\Api\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Invoice;

class PaymentReversalEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        
        $this->actingAs($this->user);
        $_ENV['APP_COMPANY_ID'] = $this->company->id;
    }

    protected function createTestPayment(array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'id' => 'test-payment-' . uniqid(),
            'company_id' => $this->company->id,
            'customer_id' => 'test-customer-uuid',
            'payment_number' => 'PAY-2025-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT),
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'bank_transfer',
            'amount' => 1000.00,
            'currency' => 'USD',
            'status' => 'completed',
            'created_by_user_id' => $this->user->id,
        ], $overrides));
    }

    protected function createTestAllocation(Payment $payment, Invoice $invoice, array $overrides = []): PaymentAllocation
    {
        return PaymentAllocation::create(array_merge([
            'id' => 'test-allocation-' . uniqid(),
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 500.00,
            'allocation_date' => now(),
            'allocation_method' => 'manual',
            'status' => 'active',
            'created_by_user_id' => $this->user->id,
        ], $overrides));
    }

    protected function createTestInvoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'id' => 'test-invoice-' . uniqid(),
            'company_id' => $this->company->id,
            'customer_id' => 'test-customer-uuid',
            'invoice_number' => 'INV-2025-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'balance_due' => 500.00,
            'status' => 'open',
        ], $overrides));
    }

    /** @test */
    public function it_can_reverse_an_allocation_via_api()
    {
        // Arrange
        $payment = $this->createTestPayment();
        $invoice = $this->createTestInvoice();
        $allocation = $this->createTestAllocation($payment, $invoice);

        // Act
        $response = $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'reason' => 'Customer requested reallocation',
            'refund_amount' => 500.00,
        ]);

        // Assert
        $response->assertStatus(202)
                 ->assertJsonStructure([
                     'allocation_id',
                     'status',
                     'reversal_id',
                     'message',
                     'reversed_at'
                 ]);

        $response->assertJson([
            'allocation_id' => $allocation->id,
            'status' => 'pending',
            'message' => 'Allocation reversal initiated'
        ]);

        // Verify database changes
        $this->assertDatabaseHas('payment_allocations', [
            'id' => $allocation->id,
            'reversed_at' => now()->format('Y-m-d H:i:s'),
            'reversal_reason' => 'Customer requested reallocation',
            'reversed_by_user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_reverse_a_payment_via_api()
    {
        // Arrange
        $payment = $this->createTestPayment(['amount' => 1000.00]);

        // Act
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE', // Using POST with _method for reversal
            'reason' => 'Bounced check returned',
            'method' => 'void',
            'amount' => 1000.00,
            'metadata' => [
                'bank_reference' => 'CHK12345',
                'return_code' => 'R01'
            ]
        ]);

        // Assert
        $response->assertStatus(202)
                 ->assertJsonStructure([
                     'reversal_id',
                     'status',
                     'payment_id',
                     'message',
                     'scheduled_at'
                 ]);

        $response->assertJson([
            'payment_id' => $payment->id,
            'status' => 'pending',
            'message' => 'Payment reversal initiated'
        ]);

        // Verify payment status change
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'reversed',
        ]);

        // Verify reversal record creation
        $this->assertDatabaseHas('payment_reversals', [
            'payment_id' => $payment->id,
            'reason' => 'Bounced check returned',
            'reversal_method' => 'void',
            'reversed_amount' => 1000.00,
            'initiated_by_user_id' => $this->user->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_validates_required_fields_for_allocation_reversal()
    {
        // Arrange
        $payment = $this->createTestPayment();
        $invoice = $this->createTestInvoice();
        $allocation = $this->createTestAllocation($payment, $invoice);

        // Act - missing reason
        $response = $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'refund_amount' => 100.00,
        ]);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['reason']);
    }

    /** @test */
    public function it_validates_required_fields_for_payment_reversal()
    {
        // Arrange
        $payment = $this->createTestPayment();

        // Act - missing reason
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'method' => 'void',
        ]);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['reason']);
    }

    /** @test */
    public function it_validates_reversal_method_for_payment_reversal()
    {
        // Arrange
        $payment = $this->createTestPayment();

        // Act - invalid method
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Invalid method test',
            'method' => 'invalid_method',
        ]);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['method']);
    }

    /** @test */
    public function it_validates_refund_amount_for_allocation_reversal()
    {
        // Arrange
        $payment = $this->createTestPayment();
        $invoice = $this->createTestInvoice();
        $allocation = $this->createTestAllocation($payment, $invoice, ['allocated_amount' => 500.00]);

        // Act - amount exceeds allocation
        $response = $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'reason' => 'Excessive refund',
            'refund_amount' => 600.00,
        ]);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['refund_amount']);
    }

    /** @test */
    public function it_validates_refund_amount_for_payment_reversal()
    {
        // Arrange
        $payment = $this->createTestPayment(['amount' => 500.00]);

        // Act - amount exceeds payment
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Excessive refund',
            'amount' => 600.00,
        ]);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_payment()
    {
        // Act
        $response = $this->postJson("/api/accounting/payments/nonexistent-payment", [
            '_method' => 'DELETE',
            'reason' => 'Test reversal',
        ]);

        // Assert
        $response->assertStatus(404);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_allocation()
    {
        // Arrange
        $payment = $this->createTestPayment();

        // Act
        $response = $this->postJson("/api/accounting/payments/{$payment->id}/allocations/nonexistent-allocation/reverse", [
            'reason' => 'Test reversal',
        ]);

        // Assert
        $response->assertStatus(404);
    }

    /** @test */
    public function it_returns_409_for_already_reversed_allocation()
    {
        // Arrange
        $payment = $this->createTestPayment();
        $invoice = $this->createTestInvoice();
        $allocation = $this->createTestAllocation($payment, $invoice, [
            'reversed_at' => now()->subDay(),
            'reversal_reason' => 'Previously reversed',
        ]);

        // Act
        $response = $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'reason' => 'Second reversal attempt',
        ]);

        // Assert
        $response->assertStatus(409)
                 ->assertJson([
                     'error' => 'Conflict',
                     'message' => 'Allocation has already been reversed'
                 ]);
    }

    /** @test */
    public function it_returns_409_for_already_reversed_payment()
    {
        // Arrange
        $payment = $this->createTestPayment(['status' => 'reversed']);

        // Act
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Second reversal attempt',
        ]);

        // Assert
        $response->assertStatus(409)
                 ->assertJson([
                     'error' => 'Conflict',
                     'message' => 'Payment has already been reversed'
                 ]);
    }

    /** @test */
    public function it_handles_idempotency_for_reversal_requests()
    {
        // Arrange
        $payment = $this->createTestPayment();
        $idempotencyKey = 'test-key-' . uniqid();

        // Act - First request
        $firstResponse = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Idempotency test',
            'method' => 'void',
        ]);

        // Act - Second request with same key
        $secondResponse = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Idempotency test - second attempt',
            'method' => 'void',
        ]);

        // Assert
        $firstResponse->assertStatus(202);
        $secondResponse->assertStatus(202);
        
        // Both responses should have the same reversal_id
        $this->assertEquals(
            $firstResponse->json('reversal_id'),
            $secondResponse->json('reversal_id')
        );
    }

    /** @test */
    public function it_enforces_rbac_permissions_for_allocation_reversal()
    {
        // Arrange - User without reversal permission
        $unauthorizedUser = User::factory()->create();
        $payment = $this->createTestPayment();
        $invoice = $this->createTestInvoice();
        $allocation = $this->createTestAllocation($payment, $invoice);

        // Act
        $response = $this->actingAs($unauthorizedUser)
                        ->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
                            'reason' => 'Unauthorized reversal',
                        ]);

        // Assert
        $response->assertStatus(403);
    }

    /** @test */
    public function it_enforces_rbac_permissions_for_payment_reversal()
    {
        // Arrange - User without reversal permission
        $unauthorizedUser = User::factory()->create();
        $payment = $this->createTestPayment();

        // Act
        $response = $this->actingAs($unauthorizedUser)
                        ->postJson("/api/accounting/payments/{$payment->id}", [
                            '_method' => 'DELETE',
                            'reason' => 'Unauthorized reversal',
                        ]);

        // Assert
        $response->assertStatus(403);
    }

    /** @test */
    public function it_enforces_company_isolation_for_allocation_reversal()
    {
        // Arrange - Create allocation in different company
        $otherCompany = Company::factory()->create();
        $payment = $this->createTestPayment(['company_id' => $otherCompany->id]);
        $invoice = $this->createTestInvoice(['company_id' => $otherCompany->id]);
        $allocation = $this->createTestAllocation($payment, $invoice);

        // Act
        $response = $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'reason' => 'Cross-company reversal attempt',
        ]);

        // Assert
        $response->assertStatus(404);
    }

    /** @test */
    public function it_enforces_company_isolation_for_payment_reversal()
    {
        // Arrange - Create payment in different company
        $otherCompany = Company::factory()->create();
        $payment = $this->createTestPayment(['company_id' => $otherCompany->id]);

        // Act
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Cross-company reversal attempt',
        ]);

        // Assert
        $response->assertStatus(404);
    }

    /** @test */
    public function it_returns_detailed_error_responses()
    {
        // Arrange
        $payment = $this->createTestPayment();

        // Act - Multiple validation errors
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'method' => 'invalid_method',
            'amount' => -100.00,
        ]);

        // Assert
        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'error',
                     'message',
                     'errors' => [
                         'method',
                         'amount',
                         'reason'
                     ]
                 ]);

        $response->assertJson([
            'error' => 'Validation failed',
            'message' => 'The given data was invalid.'
        ]);
    }

    /** @test */
    public function it_includes_proper_cors_headers_for_reversal_endpoints()
    {
        // Arrange
        $payment = $this->createTestPayment();

        // Act
        $response = $this->withHeaders([
            'Origin' => 'https://app.haasib.com',
        ])->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'CORS test',
        ]);

        // Assert
        $response->assertStatus(202)
                 ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    /** @test */
    public function it_handles_partial_payment_reversal_via_api()
    {
        // Arrange
        $payment = $this->createTestPayment(['amount' => 1000.00]);

        // Act
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Partial refund due to dispute',
            'amount' => 300.00,
            'method' => 'refund',
        ]);

        // Assert
        $response->assertStatus(202);

        // Verify partial reversal record
        $this->assertDatabaseHas('payment_reversals', [
            'payment_id' => $payment->id,
            'reversed_amount' => 300.00,
            'reversal_method' => 'refund',
        ]);

        // Payment should still exist with appropriate status
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'amount' => 1000.00,
        ]);
    }

    /** @test */
    public function it_accepts_optional_metadata_for_reversals()
    {
        // Arrange
        $payment = $this->createTestPayment();

        // Act
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Reversal with metadata',
            'method' => 'void',
            'metadata' => [
                'bank_reference' => 'BANK123',
                'return_code' => 'R01',
                'return_reason' => 'Insufficient Funds',
                'processing_date' => '2025-01-15'
            ]
        ]);

        // Assert
        $response->assertStatus(202);

        // Verify metadata is stored
        $this->assertDatabaseHas('payment_reversals', [
            'payment_id' => $payment->id,
            'metadata' => json_encode([
                'bank_reference' => 'BANK123',
                'return_code' => 'R01',
                'return_reason' => 'Insufficient Funds',
                'processing_date' => '2025-01-15'
            ])
        ]);
    }
}