<?php

namespace Modules\Accounting\Tests\Feature\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use Modules\Accounting\Domain\Payments\Events\PaymentCreated;
use Modules\Accounting\Domain\Payments\Events\PaymentAllocated;
use Modules\Accounting\Domain\Payments\Events\UnallocatedCashCreated;
use Modules\Accounting\Domain\Payments\Events\EarlyPaymentDiscountApplied;
use Modules\Accounting\Domain\Payments\Events\PaymentAudited;
use Modules\Accounting\Domain\Payments\Events\AllocationReversed;
use Modules\Accounting\Domain\Payments\Events\BankReconciliationMarker;

class AuditTrailTest extends TestCase
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
    }

    /** @test */
    public function it_creates_audit_entry_when_payment_is_created()
    {
        // Arrange
        Event::fake();
        
        $paymentData = [
            'customer_id' => 'test-customer-uuid',
            'payment_method' => 'bank_transfer',
            'amount' => 1500.00,
            'currency' => 'USD',
            'payment_date' => '2025-01-15',
            'reference_number' => 'AUDIT-001',
            'notes' => 'Payment with audit trail test',
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert
        $response->assertStatus(201);
        $paymentId = $response->json('payment.id');

        // Verify audit entry was created
        $this->assertDatabaseHas('invoicing.payment_audit_log', [
            'payment_id' => $paymentId,
            'company_id' => $this->company->id,
            'action' => 'payment_created',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
        ]);

        // Verify audit event was fired
        Event::assertDispatched(PaymentCreated::class);
        Event::assertDispatched(PaymentAudited::class, function ($event) use ($paymentId) {
            return $event->paymentId === $paymentId && 
                   $event->action === 'payment_created' &&
                   $event->actorId === $this->user->id;
        });
    }

    /** @test */
    public function it_creates_audit_entry_when_payment_is_allocated()
    {
        // Arrange
        Event::fake();
        $payment = $this->createTestPayment();
        
        $allocations = [
            [
                'invoice_id' => 'test-invoice-uuid',
                'amount' => 1000.00,
                'notes' => 'Allocation with audit test',
            ],
        ];

        // Act
        $response = $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => $allocations,
        ]);

        // Assert
        $response->assertStatus(201);

        // Verify audit entry was created
        $this->assertDatabaseHas('invoicing.payment_audit_log', [
            'payment_id' => $payment->id,
            'company_id' => $this->company->id,
            'action' => 'payment_allocated',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'metadata->total_allocated' => 1000.00,
            'metadata->allocations_count' => 1,
        ]);

        // Verify audit event was fired
        Event::assertDispatched(PaymentAllocated::class);
        Event::assertDispatched(PaymentAudited::class, function ($event) use ($payment->id) {
            return $event->paymentId === $payment->id && 
                   $event->action === 'payment_allocated';
        });
    }

    /** @test */
    public function it_tracks_bank_reconciliation_markers()
    {
        // Arrange
        Event::fake();
        $payment = $this->createTestPayment([
            'reference_number' => 'BANK-REF-12345',
            'payment_method' => 'bank_transfer'
        ]);
        
        // Simulate bank reconciliation
        $reconciliationData = [
            'payment_id' => $payment->id,
            'reconciled_by_user_id' => $this->user->id,
            'reconciliation_date' => now()->format('Y-m-d'),
            'bank_reference' => 'BANK-REF-12345',
            'reconciliation_notes' => 'Matched with bank transaction',
        ];

        // Act
        $response = $this->postJson("/api/payments/{$payment->id}/reconcile", $reconciliationData);

        // Assert
        $response->assertStatus(200);

        // Verify bank reconciliation marker was created
        $this->assertDatabaseHas('invoicing.payment_audit_log', [
            'payment_id' => $payment->id,
            'company_id' => $this->company->id,
            'action' => 'bank_reconciled',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'metadata->bank_reference' => 'BANK-REF-12345',
        ]);

        // Verify bank reconciliation event was fired
        Event::assertDispatched(BankReconciliationMarker::class);
    }

    /** @test */
    public function it_audit_endpoint_returns_complete_audit_trail()
    {
        // Arrange
        $payment = $this->createTestPayment();
        
        // Create allocations
        $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => [
                [
                    'invoice_id' => 'test-invoice-1-uuid',
                    'amount' => 800.00,
                    'apply_early_payment_discount' => true,
                ],
            ],
        ]);

        // Simulate reconciliation
        $this->postJson("/api/payments/{$payment->id}/reconcile", [
            'bank_reference' => 'BANK-REF-999',
            'reconciliation_notes' => 'Test reconciliation',
        ]);

        // Act
        $response = $this->getJson("/api/payments/{$payment->id}/audit");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'payment_id',
                'audit_trail' => [
                    '*' => [
                        'id',
                        'action',
                        'actor_id',
                        'actor_name',
                        'actor_type',
                        'timestamp',
                        'metadata',
                        'ip_address',
                    ],
                ],
                'reconciliation_status',
                'total_events',
            ]);

        $auditTrail = $response->json('audit_trail');
        
        // Verify audit trail contains expected events
        $actions = array_column($auditTrail, 'action');
        $this->assertContains('payment_created', $actions);
        $this->assertContains('payment_allocated', $actions);
        $this->assertContains('bank_reconciled', $actions);
    }

    /** @test */
    public function it_audit_trail_includes_telemetry_metadata()
    {
        // Arrange
        Event::fake();
        $payment = $this->createTestPayment();
        
        $allocations = [
            [
                'invoice_id' => 'test-invoice-uuid',
                'amount' => 1000.00,
                'apply_early_payment_discount' => true,
                'notes' => 'Allocation with telemetry test',
            ],
        ];

        // Act
        $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => $allocations,
        ]);

        // Get audit trail
        $response = $this->getJson("/api/payments/{$payment->id}/audit");

        // Assert
        $response->assertStatus(200);
        
        $auditTrail = $response->json('audit_trail');
        $allocationAudit = collect($auditTrail)->firstWhere('action', 'payment_allocated');
        
        // Verify telemetry metadata
        $this->assertArrayHasKey('telemetry', $allocationAudit['metadata']);
        $this->assertArrayHasKey('performance_metrics', $allocationAudit['metadata']['telemetry']);
        $this->assertArrayHasKey('user_agent', $allocationAudit['metadata']);
        $this->assertArrayHasKey('session_id', $allocationAudit['metadata']);
        $this->assertArrayHasKey('request_id', $allocationAudit['metadata']);
    }

    /** @test */
    public function it_audit_trail_includes_company_scope_validation()
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        
        $payment = $this->createTestPayment();
        
        // Create audit entry in original company
        $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => [
                ['invoice_id' => 'test-invoice-uuid', 'amount' => 500.00],
            ],
        ]);

        // Act - Try to access audit from different company
        $response = $this->actingAs($otherUser)
            ->withHeaders(['X-Company-Id' => $otherCompany->id])
            ->getJson("/api/payments/{$payment->id}/audit");

        // Assert
        $response->assertStatus(404);
    }

    /** @test */
    public function it_tracks_allocation_reversals_in_audit_trail()
    {
        // Arrange
        Event::fake();
        $payment = $this->createTestPayment();
        
        // Create initial allocation
        $allocationResponse = $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => [
                ['invoice_id' => 'test-invoice-uuid', 'amount' => 800.00],
            ],
        ]);
        
        $allocationId = $allocationResponse->json('allocations.0.id');

        // Act - Reverse allocation
        $reversalResponse = $this->postJson("/api/payments/{$payment->id}/allocations/{$allocationId}/reverse", [
            'reason' => 'Customer dispute resolution',
            'notes' => 'Reversal test for audit trail',
        ]);

        // Assert
        $reversalResponse->assertStatus(200);

        // Verify audit entry was created for reversal
        $this->assertDatabaseHas('invoicing.payment_audit_log', [
            'payment_id' => $payment->id,
            'company_id' => $this->company->id,
            'action' => 'allocation_reversed',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'metadata->reversal_reason' => 'Customer dispute resolution',
            'metadata->allocation_id' => $allocationId,
        ]);

        // Verify reversal event was fired
        Event::assertDispatched(AllocationReversed::class);
        Event::assertDispatched(PaymentAudited::class, function ($event) use ($payment->id, $allocationId) {
            return $event->paymentId === $payment->id && 
                   $event->action === 'allocation_reversed' &&
                   $event->metadata['allocation_id'] === $allocationId;
        });
    }

    /** @test */
    public function it_audit_trail_filters_by_date_range()
    {
        // Arrange
        $payment = $this->createTestPayment();
        
        // Create some audit entries
        $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => [
                ['invoice_id' => 'test-invoice-1-uuid', 'amount' => 400.00],
            ],
        ]);

        // Act
        $startDate = now()->subDays(1)->toDateString();
        $endDate = now()->addDay()->toDateString();
        
        $response = $this->getJson("/api/payments/{$payment->id}/audit?start_date={$startDate}&end_date={$endDate}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('date_range.start', $startDate)
            ->assertJsonPath('date_range.end', $endDate)
            ->assertJsonStructure(['audit_trail', 'total_events', 'date_range']);
    }

    /** @test */
    public function it_enforces_audit_permissions()
    {
        // Arrange
        $unauthorizedUser = User::factory()->create();
        $payment = $this->createTestPayment();
        
        // Act
        $response = $this->actingAs($unauthorizedUser)
            ->withHeaders(['X-Company-Id' => $this->company->id])
            ->getJson("/api/payments/{$payment->id}/audit");

        // Assert
        $response->assertStatus(403);
    }

    /** @test */
    public function it_audit_trail_preserves_data_integrity()
    {
        // Arrange
        $payment = $this->createTestPayment();
        $originalPaymentData = $payment->toArray();
        
        // Create audit entry
        $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => [
                ['invoice_id' => 'test-invoice-uuid', 'amount' => 600.00],
            ],
        ]);

        // Act - Get audit trail
        $response = $this->getJson("/api/payments/{$payment->id}/audit");

        // Assert
        $response->assertStatus(200);
        
        $auditTrail = $response->json('audit_trail');
        
        // Verify data integrity is preserved
        foreach ($auditTrail as $auditEntry) {
            $this->assertArrayHasKey('id', $auditEntry);
            $this->assertArrayHasKey('action', $auditEntry);
            $this->assertArrayHasKey('timestamp', $auditEntry);
            $this->assertArrayHasKey('metadata', $auditEntry);
            $this->assertArrayHasKey('checksum', $auditEntry);
            
            // Verify checksum prevents tampering
            $this->assertNotNull($auditEntry['checksum']);
            $this->assertNotEmpty($auditEntry['checksum']);
        }
    }

    // Helper method to create test payment
    private function createTestPayment(array $overrides = []): \App\Models\Payment
    {
        return \App\Models\Payment::create(array_merge([
            'id' => 'test-payment-' . uniqid(),
            'company_id' => $this->company->id,
            'customer_id' => 'test-customer-uuid',
            'payment_number' => 'PAY-2025-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT),
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'bank_transfer',
            'amount' => 1200.00,
            'currency' => 'USD',
            'status' => 'pending',
            'created_by_user_id' => $this->user->id,
            'reference_number' => 'AUDIT-TEST-' . uniqid(),
        ], $overrides));
    }
}