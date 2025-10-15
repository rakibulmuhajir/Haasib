<?php

namespace Tests\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;

class PaymentAllocateCommandTest extends TestCase
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
    }

    /** @test */
    public function it_can_allocate_payment_via_cli_with_json_output()
    {
        // Arrange
        $payment = $this->createTestPayment();
        
        // Act
        $exitCode = Artisan::call('payment:allocate', [
            'payment' => $payment->payment_number,
            '--invoices' => 'INV-2025-109,INV-2025-112',
            '--amounts' => '600,650',
            '--format' => 'json'
        ]);

        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        
        // Verify JSON output structure
        $result = json_decode($output, true);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment_id', $result);
        $this->assertArrayHasKey('allocations_created', $result);
        $this->assertArrayHasKey('message', $result);
        
        // Verify allocations were created in database
        $this->assertDatabaseHas('invoicing.payment_allocations', [
            'payment_id' => $payment->id,
            'company_id' => $this->company->id,
        ]);
    }

    /** @test */
    public function it_handles_invalid_payment_number_gracefully()
    {
        // Act
        $exitCode = Artisan::call('payment:allocate', [
            'payment' => 'INVALID-PAYMENT',
            '--invoices' => 'INV-2025-109',
            '--amounts' => '600',
            '--format' => 'json'
        ]);

        $output = Artisan::output();

        // Assert
        $this->assertEquals(1, $exitCode);
        
        // Verify error JSON output
        $result = json_decode($output, true);
        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('message', $result);
    }

    /** @test */
    public function it_validates_invoice_and_amount_arrays_match()
    {
        // Arrange
        $payment = $this->createTestPayment();
        
        // Act - Mismatched arrays (2 invoices, 1 amount)
        $exitCode = Artisan::call('payment:allocate', [
            'payment' => $payment->payment_number,
            '--invoices' => 'INV-2025-109,INV-2025-112',
            '--amounts' => '1000',
            '--format' => 'json'
        ]);

        $output = Artisan::output();

        // Assert
        $this->assertEquals(1, $exitCode);
        
        $result = json_decode($output, true);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('must match', $result['message']);
    }

    /** @test */
    public function it_supports_dry_run_mode()
    {
        // Arrange
        $payment = $this->createTestPayment();
        
        // Act
        $exitCode = Artisan::call('payment:allocate', [
            'payment' => $payment->payment_number,
            '--invoices' => 'INV-2025-109,INV-2025-112',
            '--amounts' => '600,650',
            '--dry-run' => true,
            '--format' => 'json'
        ]);

        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        
        $result = json_decode($output, true);
        $this->assertArrayHasKey('dry_run', $result);
        $this->assertTrue($result['dry_run']);
        $this->assertArrayHasKey('proposed_allocations', $result);
        
        // Verify no actual allocations were created
        $this->assertDatabaseMissing('invoicing.payment_allocations', [
            'payment_id' => $payment->id,
        ]);
    }

    /** @test */
    public function it_enforces_company_isolation_in_cli()
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        $payment = $this->createTestPayment();
        
        // Act - Try to allocate with different company context
        $exitCode = Artisan::call('payment:allocate', [
            'payment' => $payment->payment_number,
            '--invoices' => 'INV-2025-109',
            '--amounts' => '600',
            '--format' => 'json'
        ], [
            'X-Company-Id' => $otherCompany->id
        ]);

        $output = Artisan::output();

        // Assert
        $this->assertEquals(1, $exitCode);
        
        $result = json_decode($output, true);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('not found', $result['message']);
    }

    /** @test */
    public function it_emits_telemetry_events_on_successful_allocation()
    {
        // Arrange
        Event::fake();
        $payment = $this->createTestPayment();
        
        // Act
        $exitCode = Artisan::call('payment:allocate', [
            'payment' => $payment->payment_number,
            '--invoices' => 'INV-2025-109',
            '--amounts' => '1000',
            '--format' => 'json'
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        
        // Verify telemetry events were fired
        Event::assertDispatched(\Modules\Accounting\Domain\Payments\Events\PaymentAllocated::class);
    }

    /** @test */
    public function it_handles_allocation_strategies_via_cli()
    {
        // Arrange
        $payment = $this->createTestPayment();
        
        // Act
        $exitCode = Artisan::call('payment:allocate', [
            'payment' => $payment->payment_number,
            '--auto' => true,
            '--strategy' => 'fifo',
            '--format' => 'json'
        ]);

        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        
        $result = json_decode($output, true);
        $this->assertArrayHasKey('strategy_used', $result);
        $this->assertEquals('fifo', $result['strategy_used']);
        $this->assertArrayHasKey('allocations_created', $result);
    }

    /** @test */
    public function it_outputs_human_readable_format_by_default()
    {
        // Arrange
        $payment = $this->createTestPayment();
        
        // Act
        $exitCode = Artisan::call('payment:allocate', [
            'payment' => $payment->payment_number,
            '--invoices' => 'INV-2025-109',
            '--amounts' => '1000'
        ]);

        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        
        // Should contain human-readable output, not JSON
        $this->assertStringContains('Payment allocated successfully', $output);
        $this->assertStringNotContains('{', $output);
        $this->assertStringNotContains('}', $output);
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