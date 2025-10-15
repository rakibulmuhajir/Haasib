<?php

namespace Tests\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use Modules\Accounting\Domain\Payments\Events\PaymentCreated;
use Modules\Accounting\Domain\Payments\Events\PaymentAllocated;
use Modules\Accounting\Domain\Payments\Events\PaymentAudited;

class PaymentAllocationReportCommandTest extends TestCase
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
        $_ENV['APP_COMPANY_ID'] = $this->company->id;
    }

    /** @test */
    public function it_generates_allocation_report_with_json_output()
    {
        // Arrange
        $payment1 = $this->createTestPayment(['payment_number' => 'PAY-2025-001', 'amount' => 1000.00]);
        $payment2 = $this->createTestPayment(['payment_number' => 'PAY-2025-002', 'amount' => 1500.00]);
        
        // Create allocations
        $this->createAllocation($payment1->id, 'INV-2025-001', 800.00);
        $this->createAllocation($payment1->id, 'INV-2025-002', 150.00);
        $this->createAllocation($payment2->id, 'INV-2025-003', 1000.00);

        // Act
        $exitCode = Artisan::call('payment:allocation:report', [
            '--format' => 'json',
            '--start-date' => now()->subDays(7)->toDateString(),
            '--end-date' => now()->addDay()->toDateString()
        ]);

        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        
        $reportData = json_decode($output, true);
        
        // Verify JSON output structure
        $this->assertArrayHasKey('report_metadata', $reportData);
        $this->assertArrayHasKey('summary', $reportData);
        $this->assertArrayHasKey('allocations', $reportData);
        $this->assertArrayHasKey('audit_trail_summary', $reportData);
        
        // Verify report metadata
        $this->assertArrayHasKey('generated_at', $reportData['report_metadata']);
        $this->assertArrayHasKey('report_period', $reportData['report_metadata']);
        $this->assertArrayHasKey('total_payments', $reportData['report_metadata']);
        $this->assertEquals('USD', $reportData['report_metadata']['currency']);
        
        // Verify summary data
        $this->assertArrayHasKey('total_allocations', $reportData['summary']);
        $this->assertArrayHasKey('total_amount_allocated', $reportData['summary']);
        $this->assertArrayHasKey('total_discounts_applied', $reportData['summary']);
        $this->assertArrayHasKey('total_unallocated_cash', $reportData['summary']);
        
        $this->assertEquals(3, $reportData['summary']['total_allocations']);
        $this->assertEquals(1950.00, $reportData['summary']['total_amount_allocated']);
    }

    /** @test */
    public function it_filters_report_by_payment_number()
    {
        // Arrange
        $payment1 = $this->createTestPayment(['payment_number' => 'PAY-2025-001', 'amount' => 1000.00]);
        $payment2 = $this->createTestPayment(['payment_number' => 'PAY-2025-002', 'amount' => 1500.00]);
        
        $this->createAllocation($payment1->id, 'INV-2025-001', 800.00);
        $this->createAllocation($payment2->id, 'INV-2025-003', 1000.00);

        // Act
        $exitCode = Artisan::call('payment:allocation:report', [
            '--payment' => 'PAY-2025-001',
            '--format' => 'json'
        ]);

        $output = Artisan::output();
        $reportData = json_decode($output, true);

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertEquals(1, $reportData['report_metadata']['total_payments']);
        $this->assertEquals(1, $reportData['summary']['total_allocations']);
        $this->assertEquals(800.00, $reportData['summary']['total_amount_allocated']);
    }

    /** @test */
    public function it_filters_report_by_customer()
    {
        // Arrange
        $payment1 = $this->createTestPayment(['payment_number' => 'PAY-2025-001', 'customer_id' => 'customer-1-uuid']);
        $payment2 = $this->createTestPayment(['payment_number' => 'PAY-2025-002', 'customer_id' => 'customer-2-uuid']);
        
        $this->createAllocation($payment1->id, 'INV-2025-001', 800.00);
        $this->createAllocation($payment2->id, 'INV-2025-002', 1000.00);

        // Act
        $exitCode = Artisan::call('payment:allocation:report', [
            '--customer' => 'customer-1-uuid',
            '--format' => 'json'
        ]);

        $output = Artisan::output();
        $reportData = json_decode($output, true);

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertEquals(1, $reportData['summary']['total_allocations']);
        $this->assertEquals('customer-1-uuid', $reportData['summary']['customer_id']);
    }

    /** @test */
    public function it_includes_reconciliation_filtering_options()
    {
        // Arrange
        $payment1 = $this->createTestPayment(['payment_number' => 'PAY-2025-001']);
        $payment2 = $this->createTestPayment(['payment_number' => 'PAY-2025-002']);
        
        // Create allocations and mark one as reconciled
        $allocation1 = $this->createAllocation($payment1->id, 'INV-2025-001', 800.00);
        $allocation2 = $this->createAllocation($payment2->id, 'INV-2025-002', 1000.00);
        
        // Mark first payment as reconciled
        $this->markPaymentReconciled($payment1->id);

        // Act - Filter for unreconciled allocations
        $exitCode = Artisan::call('payment:allocation:report', [
            '--reconciled' => 'false',
            '--format' => 'json'
        ]);

        $output = Artisan::output();
        $reportData = json_decode($output, true);

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertEquals(1, $reportData['summary']['total_allocations']);
        $this->assertFalse($reportData['summary']['all_reconciled']);
        $this->assertEquals(1000.00, $reportData['summary']['total_amount_allocated']);
    }

    /** @test */
    public function it_includes_audit_trail_summary()
    {
        // Arrange
        Event::fake();
        $payment = $this->createTestPayment();
        
        // Create allocation to trigger audit events
        $this->createAllocation($payment->id, 'INV-2025-001', 800.00);

        // Act
        $exitCode = Artisan::call('payment:allocation:report', [
            '--format' => 'json',
            '--include-audit' => 'true'
        ]);

        $output = Artisan::output();
        $reportData = json_decode($output, true);

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertArrayHasKey('audit_trail_summary', $reportData);
        
        $auditSummary = $reportData['audit_trail_summary'];
        $this->assertArrayHasKey('total_audit_events', $auditSummary);
        $this->assertArrayHasKey('payment_created_events', $auditSummary);
        $this->assertArrayHasKey('payment_allocated_events', $auditSummary);
        $this->assertArrayHasKey('bank_reconciled_events', $auditSummary);
        $this->assertArrayHasKey('allocation_reversed_events', $auditSummary);
        
        $this->assertGreaterThanOrEqual(1, $auditSummary['payment_created_events']);
        $this->assertGreaterThanOrEqual(1, $auditSummary['payment_allocated_events']);
    }

    /** @test */
    public function it_generates_table_format_output()
    {
        // Arrange
        $payment = $this->createTestPayment(['payment_number' => 'PAY-2025-001', 'amount' => 1000.00]);
        $this->createAllocation($payment->id, 'INV-2025-001', 800.00);

        // Act
        $exitCode = Artisan::call('payment:allocation:report', [
            '--format' => 'table'
        ]);

        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContains('Payment Allocation Report', $output);
        $this->assertStringContains('PAY-2025-001', $output);
        $this->assertStringContains('INV-2025-001', $output);
        $this->assertStringContains('800.00', $output);
    }

    /** @test */
    public function it_supports_export_to_csv_format()
    {
        // Arrange
        $payment = $this->createTestPayment(['payment_number' => 'PAY-2025-001']);
        $this->createAllocation($payment->id, 'INV-2025-001', 800.00);

        // Act
        $exitCode = Artisan::call('payment:allocation:report', [
            '--format' => 'csv',
            '--output' => '/tmp/allocation_report.csv'
        ]);

        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContains('CSV report exported to:', $output);
        $this->assertFileExists('/tmp/allocation_report.csv');
        
        // Verify CSV content
        $csvContent = file_get_contents('/tmp/allocation_report.csv');
        $this->assertStringContains('payment_number', $csvContent);
        $this->assertStringContains('invoice_number', $csvContent);
        $this->assertStringContains('allocated_amount', $csvContent);
    }

    /** @test */
    public function it_enforces_company_isolation()
    {
        // Arrange
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        
        $payment = $this->createTestPayment();

        // Act - Try to generate report from different company
        $_ENV['APP_COMPANY_ID'] = $otherCompany->id;
        $exitCode = Artisan::call('payment:allocation:report', [
            '--format' => 'json'
        ]);

        // Assert
        $this->assertEquals(0, $exitCode);
        
        $output = Artisan::output();
        $reportData = json_decode($output, true);
        
        // Report should be empty for different company
        $this->assertEquals(0, $reportData['report_metadata']['total_payments']);
        $this->assertEquals(0, $reportData['summary']['total_allocations']);
    }

    /** @test */
    public function it_handles_date_range_validation()
    {
        // Act
        $exitCode = Artisan::call('payment:allocation:report', [
            '--start-date' => '2025-13-32', // Invalid date
            '--format' => 'json'
        ]);

        // Assert
        $this->assertEquals(1, $exitCode);
        $output = Artisan::output();
        $this->assertStringContains('Invalid date format', $output);
    }

    /** @test */
    public function it_includes_performance_metrics()
    {
        // Arrange
        $payment = $this->createTestPayment();
        
        // Create multiple allocations for better metrics
        for ($i = 0; $i < 5; $i++) {
            $this->createAllocation($payment->id, "INV-2025-00" . ($i + 1), 100.00);
        }

        // Act
        $exitCode = Artisan::call('payment:allocation:report', [
            '--format' => 'json',
            '--include-metrics' => 'true'
        ]);

        $output = Artisan::output();
        $reportData = json_decode($output, true);

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertArrayHasKey('performance_metrics', $reportData);
        
        $metrics = $reportData['performance_metrics'];
        $this->assertArrayHasKey('query_execution_time', $metrics);
        $this->assertArrayHasKey('memory_usage_mb', $metrics);
        $this->assertArrayHasKey('records_processed', $metrics);
        $this->assertArrayHasKey('average_allocation_time_ms', $metrics);
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
            'status' => 'completed',
            'created_by_user_id' => $this->user->id,
        ], $overrides));
    }

    private function createAllocation(string $paymentId, string $invoiceId, float $amount): \App\Models\PaymentAllocation
    {
        return \App\Models\PaymentAllocation::create([
            'id' => 'allocation-' . uniqid(),
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'allocated_amount' => $amount,
            'allocation_date' => now(),
            'allocation_method' => 'manual',
            'notes' => 'Test allocation for reporting',
            'created_by_user_id' => $this->user->id,
        ]);
    }

    private function markPaymentReconciled(string $paymentId): void
    {
        // This would typically create a reconciliation record
        // For testing, we'll simulate it
        \DB::table('invoicing.payment_audit_log')->insert([
            'id' => uniqid(),
            'payment_id' => $paymentId,
            'company_id' => $this->company->id,
            'action' => 'bank_reconciled',
            'actor_id' => $this->user->id,
            'actor_type' => 'user',
            'metadata' => json_encode([
                'reconciliation_date' => now()->toDateString(),
                'reconciliation_method' => 'automated'
            ]),
            'timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}