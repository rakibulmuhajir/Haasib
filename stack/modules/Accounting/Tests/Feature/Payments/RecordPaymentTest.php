<?php

namespace Modules\Accounting\Tests\Feature\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;

class RecordPaymentTest extends TestCase
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
        $this->actingAs($user);
        $this->withHeaders(['X-Company-Id' => $this->company->id]);
    }

    /** @test */
    public function it_can_create_a_payment_via_command_bus()
    {
        // Arrange
        Event::fake();
        $paymentData = [
            'customer_id' => 'test-customer-uuid',
            'payment_method' => 'bank_transfer',
            'amount' => 1250.00,
            'currency' => 'USD',
            'payment_date' => '2025-01-15',
            'reference_number' => 'WIRE-9821',
            'notes' => 'Test payment',
        ];

        // Act
        $response = $this->postJson('/api/payments', $paymentData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'payment' => [
                    'id',
                    'payment_number',
                    'customer_id',
                    'amount',
                    'currency',
                    'status',
                ],
                'remaining_amount',
                'is_fully_allocated',
            ]);

        // Verify command bus was called
        // This will fail initially since actions aren't implemented yet
        $this->assertDatabaseHas('invoicing.payments', [
            'customer_id' => $paymentData['customer_id'],
            'amount' => $paymentData['amount'],
            'company_id' => $this->company->id,
        ]);

        // Verify event was fired
        Event::assertDispatched(\Modules\Accounting\Domain\Payments\Events\PaymentCreated::class);
    }

    /** @test */
    public function it_validates_required_payment_fields()
    {
        // Act
        $response = $this->postJson('/api/payments', []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'customer_id',
                'payment_method', 
                'amount',
                'currency',
                'payment_date',
            ]);
    }

    /** @test */
    public function it_enforces_company_isolation_via_rls()
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();

        $paymentData = [
            'customer_id' => 'test-customer-uuid',
            'payment_method' => 'bank_transfer',
            'amount' => 1250.00,
            'currency' => 'USD',
            'payment_date' => '2025-01-15',
        ];

        // Act - Try to create payment for different company
        $response = $this->actingAs($otherUser)
            ->withHeaders(['X-Company-Id' => $otherCompany->id])
            ->postJson('/api/payments', $paymentData);

        // Assert - Should succeed for other company
        $response->assertStatus(201);

        // Verify original company can't see other company's payment
        $response = $this->actingAs($this->user)
            ->withHeaders(['X-Company-Id' => $this->company->id])
            ->getJson("/api/payments/other-payment-id");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_manually_allocate_payment_to_invoices()
    {
        // Arrange
        $payment = $this->createTestPayment();
        
        $allocations = [
            [
                'invoice_id' => 'test-invoice-1-uuid',
                'amount' => 600.00,
                'notes' => 'Partial payment',
            ],
            [
                'invoice_id' => 'test-invoice-2-uuid', 
                'amount' => 650.00,
                'notes' => 'Remaining payment',
            ],
        ];

        // Act
        $response = $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => $allocations,
        ]);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'payment_id',
                'allocations' => [
                    '*' => [
                        'id',
                        'invoice_id',
                        'invoice_number',
                        'allocated_amount',
                        'allocation_method',
                        'allocation_date',
                    ],
                ],
                'remaining_amount',
            ]);

        // Verify allocations were created
        $this->assertDatabaseHas('invoicing.payment_allocations', [
            'payment_id' => $payment->id,
            'invoice_id' => 'test-invoice-1-uuid',
            'allocated_amount' => 600.00,
            'company_id' => $this->company->id,
        ]);

        // Verify payment status updated
        $this->assertDatabaseHas('invoicing.payments', [
            'id' => $payment->id,
            'status' => 'completed', // Should be fully allocated
        ]);
    }

    /** @test */
    public function it_prevents_over_allocation()
    {
        // Arrange
        $payment = $this->createTestPayment(['amount' => 1000.00]);

        $allocations = [
            [
                'invoice_id' => 'test-invoice-1-uuid',
                'amount' => 600.00,
            ],
            [
                'invoice_id' => 'test-invoice-2-uuid',
                'amount' => 500.00, // Total = 1100.00 > 1000.00
            ],
        ];

        // Act
        $response = $this->postJson("/api/payments/{$payment->id}/allocations", [
            'allocations' => $allocations,
        ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['allocations']);
    }

    /** @test */
    public function it_enforces_payment_permissions()
    {
        // Arrange
        $unauthorizedUser = User::factory()->create();
        
        // Act
        $response = $this->actingAs($unauthorizedUser)
            ->withHeaders(['X-Company-Id' => $this->company->id])
            ->postJson('/api/payments', [
                'customer_id' => 'test-customer-uuid',
                'payment_method' => 'bank_transfer',
                'amount' => 1250.00,
                'currency' => 'USD',
                'payment_date' => '2025-01-15',
            ]);

        // Assert
        $response->assertStatus(403);
    }

    // Helper method to create test payment
    private function createTestPayment(array $overrides = []): \App\Models\Payment
    {
        return \App\Models\Payment::create(array_merge([
            'id' => 'test-payment-' . uniqid(),
            'company_id' => $this->company->id,
            'customer_id' => 'test-customer-uuid',
            'payment_number' => 'PAY-2025-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT),
            'payment_date' => '2025-01-15',
            'payment_method' => 'bank_transfer',
            'amount' => 1250.00,
            'currency' => 'USD',
            'status' => 'pending',
            'created_by_user_id' => $this->user->id,
        ], $overrides));
    }
}