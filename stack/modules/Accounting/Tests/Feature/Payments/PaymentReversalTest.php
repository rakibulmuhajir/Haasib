<?php

namespace Modules\Accounting\Tests\Feature\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Invoice;
use Modules\Accounting\Domain\Payments\Events\PaymentCreated;
use Modules\Accounting\Domain\Payments\Events\PaymentAllocated;
use Modules\Accounting\Domain\Payments\Events\PaymentAudited;
use Modules\Accounting\Domain\Payments\Events\AllocationReversed;

class PaymentReversalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Company $company;
    protected Payment $payment;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user and company with proper tenancy
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        
        // Set company context for RLS
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

    /** @test */
    public function it_reverses_an_allocation_and_restores_invoice_balance()
    {
        Event::fake();
        
        // Arrange: Create payment and allocation
        $payment = $this->createTestPayment();
        $invoice = $this->createTestInvoice(['balance_due' => 500.00]);
        $allocation = $this->createTestAllocation($payment, $invoice);
        
        // Update invoice balance (simulate allocation effect)
        $invoice->balance_due = 0.00;
        $invoice->save();

        // Act: Reverse the allocation
        $response = $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'reason' => 'Customer requested reallocation',
            'refund_amount' => 500.00,
        ]);

        // Assert
        $response->assertStatus(202);
        $response->assertJsonStructure([
            'allocation_id',
            'status',
            'reversal_id',
            'message'
        ]);

        // Check that allocation is marked as reversed
        $this->assertDatabaseHas('payment_allocations', [
            'id' => $allocation->id,
            'reversed_at' => now(),
            'reversal_reason' => 'Customer requested reallocation',
            'reversed_by_user_id' => $this->user->id,
        ]);

        // Check that invoice balance is restored (this would be handled by domain logic)
        $invoice->refresh();
        $this->assertEquals(500.00, $invoice->balance_due);

        // Assert audit events are fired
        Event::assertDispatched(AllocationReversed::class, function ($event) use ($allocation, $payment) {
            return $event->data['allocation_id'] === $allocation->id &&
                   $event->data['payment_id'] === $payment->id &&
                   $event->data['reason'] === 'Customer requested reallocation';
        });

        Event::assertDispatched(PaymentAudited::class, function ($event) use ($payment) {
            return $event->data['payment_id'] === $payment->id &&
                   $event->data['action'] === 'allocation_reversed';
        });
    }

    /** @test */
    public function it_reverses_a_payment_and_all_associated_allocations()
    {
        Event::fake();
        
        // Arrange: Create payment with multiple allocations
        $payment = $this->createTestPayment(['amount' => 1000.00]);
        $invoice1 = $this->createTestInvoice(['invoice_number' => 'INV-001']);
        $invoice2 = $this->createTestInvoice(['invoice_number' => 'INV-002']);
        
        $allocation1 = $this->createTestAllocation($payment, $invoice1, ['allocated_amount' => 600.00]);
        $allocation2 = $this->createTestAllocation($payment, $invoice2, ['allocated_amount' => 400.00]);

        // Act: Reverse the entire payment
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE', // Using POST with _method for reversal
            'reason' => 'Bounced check returned',
            'method' => 'void',
            'amount' => 1000.00,
        ]);

        // Assert
        $response->assertStatus(202);
        $response->assertJsonStructure([
            'reversal_id',
            'status',
            'payment_id',
            'message'
        ]);

        // Check payment status is changed to reversed
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'reversed',
        ]);

        // Check all allocations are marked as reversed
        $this->assertDatabaseHas('payment_allocations', [
            'id' => $allocation1->id,
            'reversed_at' => now(),
        ]);
        
        $this->assertDatabaseHas('payment_allocations', [
            'id' => $allocation2->id,
            'reversed_at' => now(),
        ]);

        // Check reversal record is created
        $this->assertDatabaseHas('payment_reversals', [
            'payment_id' => $payment->id,
            'reason' => 'Bounced check returned',
            'reversal_method' => 'void',
            'reversed_amount' => 1000.00,
            'initiated_by_user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        // Assert audit events
        Event::assertDispatched(PaymentAudited::class, function ($event) use ($payment) {
            return $event->data['payment_id'] === $payment->id &&
                   $event->data['action'] === 'payment_reversed';
        });
    }

    /** @test */
    public function it_prevents_reversal_of_already_reversed_allocation()
    {
        // Arrange: Create already reversed allocation
        $payment = $this->createTestPayment();
        $invoice = $this->createTestInvoice();
        $allocation = $this->createTestAllocation($payment, $invoice, [
            'reversed_at' => now()->subDay(),
            'reversal_reason' => 'Previous reversal',
        ]);

        // Act: Try to reverse again
        $response = $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'reason' => 'Second reversal attempt',
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['allocation_id']);
    }

    /** @test */
    public function it_prevents_reversal_of_already_reversed_payment()
    {
        // Arrange: Create already reversed payment
        $payment = $this->createTestPayment(['status' => 'reversed']);

        // Act: Try to reverse again
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Second reversal attempt',
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_id']);
    }

    /** @test */
    public function it_validates_reversal_reason_is_required()
    {
        // Arrange
        $payment = $this->createTestPayment();

        // Act: Try to reverse without reason
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    /** @test */
    public function it_validates_refund_amount_does_not_exceed_original_amount()
    {
        // Arrange
        $payment = $this->createTestPayment(['amount' => 500.00]);

        // Act: Try to reverse with larger amount
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Over refund attempt',
            'amount' => 600.00,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    /** @test */
    public function it_enforces_company_isolation_for_reversals()
    {
        // Arrange: Create payment in different company
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        
        $payment = $this->createTestPayment(['company_id' => $otherCompany->id]);

        // Act: Try to reverse with different company context
        $_ENV['APP_COMPANY_ID'] = $this->company->id;
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Cross-company reversal attempt',
        ]);

        // Assert
        $response->assertStatus(404);
    }

    /** @test */
    public function it_emits_telemetry_events_for_reversals()
    {
        Event::fake();
        
        // Arrange
        $payment = $this->createTestPayment();
        $invoice = $this->createTestInvoice();
        $allocation = $this->createTestAllocation($payment, $invoice);

        // Act
        $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'reason' => 'Test reversal for telemetry',
        ]);

        // Assert telemetry events
        Event::assertDispatched('payment.reversal.attempted');
        Event::assertDispatched('allocation.reversed');
    }

    /** @test */
    public function it_handles_partial_payment_reversal()
    {
        Event::fake();
        
        // Arrange
        $payment = $this->createTestPayment(['amount' => 1000.00]);
        $invoice1 = $this->createTestInvoice(['invoice_number' => 'INV-001']);
        $invoice2 = $this->createTestInvoice(['invoice_number' => 'INV-002']);
        
        $allocation1 = $this->createTestAllocation($payment, $invoice1, ['allocated_amount' => 600.00]);
        $allocation2 = $this->createTestAllocation($payment, $invoice2, ['allocated_amount' => 400.00]);

        // Act: Partial reversal of 500.00
        $response = $this->postJson("/api/accounting/payments/{$payment->id}", [
            '_method' => 'DELETE',
            'reason' => 'Partial refund due to dispute',
            'amount' => 500.00,
            'method' => 'refund',
        ]);

        // Assert
        $response->assertStatus(202);
        
        // Check reversal record
        $this->assertDatabaseHas('payment_reversals', [
            'payment_id' => $payment->id,
            'reversed_amount' => 500.00,
            'reversal_method' => 'refund',
        ]);

        // Payment should still be completed but with remaining amount for potential refund allocation
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'completed', // Status might remain completed until fully reversed
        ]);
    }

    /** @test */
    public function it_provides_audit_trail_for_reversal_operations()
    {
        Event::fake();
        
        // Arrange
        $payment = $this->createTestPayment();
        $invoice = $this->createTestInvoice();
        $allocation = $this->createTestAllocation($payment, $invoice);

        // Act
        $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'reason' => 'Audit trail test reversal',
            'refund_amount' => 250.00,
        ]);

        // Assert comprehensive audit trail
        Event::assertDispatched(PaymentAudited::class, function ($event) use ($payment, $allocation) {
            $metadata = $event->data['metadata'];
            return $event->data['payment_id'] === $payment->id &&
                   $event->data['action'] === 'allocation_reversed' &&
                   $metadata['reason'] === 'Audit trail test reversal' &&
                   $metadata['refund_amount'] === 250.00 &&
                   $metadata['original_amount'] === $allocation->allocated_amount &&
                   isset($metadata['ip_address']) &&
                   isset($metadata['user_agent']);
        });
    }

    /** @test */
    public function it_handles_concurrent_reversal_attempts_with_optimistic_locking()
    {
        // Arrange
        $payment = $this->createTestPayment();
        $invoice = $this->createTestInvoice();
        $allocation = $this->createTestAllocation($payment, $invoice);

        // Act: Simulate concurrent reversal attempts
        $firstResponse = $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'reason' => 'First reversal',
        ]);

        // Update allocation to be reversed before second attempt
        $allocation->update(['reversed_at' => now()]);

        $secondResponse = $this->postJson("/api/accounting/payments/{$payment->id}/allocations/{$allocation->id}/reverse", [
            'reason' => 'Second concurrent reversal',
        ]);

        // Assert
        $firstResponse->assertStatus(202);
        $secondResponse->assertStatus(422);
        $secondResponse->assertJsonValidationErrors(['allocation_id']);
    }
}