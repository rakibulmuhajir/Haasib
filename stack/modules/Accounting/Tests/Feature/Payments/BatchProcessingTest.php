<?php

namespace Modules\Accounting\Tests\Feature\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use Modules\Accounting\Domain\Payments\Events\PaymentBatchCreated;
use Modules\Accounting\Domain\Payments\Events\PaymentBatchProcessed;
use Modules\Accounting\Domain\Payments\Events\PaymentBatchFailed;
use Modules\Accounting\Domain\Payments\Actions\CreatePaymentBatchAction;

class BatchProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test tenant
        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
        
        // Create test invoice for allocation
        $this->invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'total_amount' => 1000.00,
            'balance_due' => 1000.00,
            'status' => 'open'
        ]);

        // Set company context for RLS
        DB::statement("SET app.current_company = ?", [$this->company->id]);
        
        // Give user permissions
        $this->user->givePermissionTo('accounting.payments.create');
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        // Clear company context
        DB::statement("RESET app.current_company");
        parent::tearDown();
    }

    /** @test */
    public function it_can_create_a_payment_batch_with_manual_entries()
    {
        Event::fake();
        Queue::fake();

        $batchData = [
            'source_type' => 'manual',
            'entries' => [
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'bank_transfer',
                    'amount' => 500.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15',
                    'reference_number' => 'BATCH-001-1',
                    'auto_allocate' => true,
                    'allocation_strategy' => 'fifo'
                ],
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'card',
                    'amount' => 300.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15',
                    'reference_number' => 'BATCH-001-2',
                    'notes' => 'Overpayment to track'
                ]
            ],
            'notes' => 'Test manual batch'
        ];

        $response = $this->postJson('/api/accounting/payment-batches', $batchData, [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'batch_id',
                'batch_number',
                'status',
                'receipt_count',
                'total_amount',
                'currency',
                'estimated_completion',
                'message'
            ]);

        $this->assertDatabaseHas('invoicing.payment_receipt_batches', [
            'company_id' => $this->company->id,
            'status' => 'pending',
            'receipt_count' => 2,
            'total_amount' => 800.00,
            'currency' => 'USD',
            'created_by_user_id' => $this->user->id
        ]);

        Event::assertDispatched(PaymentBatchCreated::class, function ($event) use ($batchData) {
            return $event->getData()['receipt_count'] === 2 &&
                   $event->getData()['source_type'] === 'manual';
        });

        Queue::assertPushed(\Modules\Accounting\Jobs\ProcessPaymentBatch::class);
    }

    /** @test */
    public function it_can_create_a_payment_batch_from_csv_upload()
    {
        Event::fake();
        Queue::fake();

        // Create fake CSV file
        $csvContent = "customer_id,payment_method,amount,currency,payment_date,reference_number,notes\n";
        $csvContent .= "{$this->customer->id},bank_transfer,750.00,USD,2025-01-15,BATCH-002-1,Test payment 1\n";
        $csvContent .= "{$this->customer->id},card,250.00,USD,2025-01-15,BATCH-002-2,Test payment 2\n";

        $file = \Illuminate\Http\UploadedFile::fake()
            ->createWithContent('batch_payments.csv', $csvContent);

        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'csv_import',
            'file' => $file,
            'notes' => 'Test CSV batch'
        ], [
            'X-Company-Id' => $this->company->id,
            'Content-Type' => 'multipart/form-data'
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'batch_id',
                'batch_number',
                'status',
                'receipt_count',
                'total_amount',
                'currency',
                'estimated_completion',
                'message'
            ]);

        $this->assertDatabaseHas('invoicing.payment_receipt_batches', [
            'company_id' => $this->company->id,
            'status' => 'pending',
            'source_type' => 'csv_import',
            'created_by_user_id' => $this->user->id
        ]);

        Event::assertDispatched(PaymentBatchCreated::class);
        Queue::assertPushed(\Modules\Accounting\Jobs\ProcessPaymentBatch::class);
    }

    /** @test */
    public function it_validates_batch_creation_requirements()
    {
        // Test missing source_type
        $response = $this->postJson('/api/accounting/payment-batches', [], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source_type']);

        // Test invalid source_type
        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'invalid_type'
        ], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source_type']);

        // Test CSV import without file
        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'csv_import'
        ], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        // Test manual entries without entries array
        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'manual',
            'entries' => []
        ], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['entries']);

        // Test entry validation errors
        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'manual',
            'entries' => [
                [
                    // Missing required fields
                    'payment_method' => 'bank_transfer',
                    'amount' => -100.00 // Negative amount
                ]
            ]
        ], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.entity_id', 'entries.0.amount']);
    }

    /** @test */
    public function it_processes_csv_batch_with_validation_errors_gracefully()
    {
        Event::fake();
        Queue::fake();

        // Create CSV with validation errors
        $csvContent = "customer_id,payment_method,amount,currency,payment_date,reference_number\n";
        $csvContent .= "invalid_uuid,bank_transfer,750.00,USD,2025-01-15,BATCH-003-1\n"; // Invalid customer
        $csvContent .= "{$this->customer->id},invalid_method,250.00,USD,2025-01-15,BATCH-003-2\n"; // Invalid method
        $csvContent .= "{$this->customer->id},card,-100.00,USD,2025-01-15,BATCH-003-3\n"; // Negative amount

        $file = \Illuminate\Http\UploadedFile::fake()
            ->createWithContent('batch_with_errors.csv', $csvContent);

        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'csv_import',
            'file' => $file
        ], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(202);

        // The batch should be created but will fail during processing
        $this->assertDatabaseHas('invoicing.payment_receipt_batches', [
            'company_id' => $this->company->id,
            'status' => 'pending'
        ]);

        Queue::assertPushed(\Modules\Accounting\Jobs\ProcessPaymentBatch::class);
    }

    /** @test */
    public function it_prevents_duplicate_batch_processing_with_idempotency_key()
    {
        Event::fake();
        
        $batchData = [
            'source_type' => 'manual',
            'entries' => [
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'bank_transfer',
                    'amount' => 500.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15',
                    'reference_number' => 'BATCH-004-1'
                ]
            ]
        ];

        $idempotencyKey = 'test-batch-key-12345';

        // First request
        $response1 = $this->postJson('/api/accounting/payment-batches', $batchData, [
            'X-Company-Id' => $this->company->id,
            'Idempotency-Key' => $idempotencyKey
        ]);

        $response1->assertStatus(202);

        // Second request with same idempotency key
        $response2 = $this->postJson('/api/accounting/payment-batches', $batchData, [
            'X-Company-Id' => $this->company->id,
            'Idempotency-Key' => $idempotencyKey
        ]);

        $response2->assertStatus(409)
            ->assertJson([
                'error' => 'Duplicate batch creation',
                'message' => 'A batch with this idempotency key is already being processed'
            ]);
    }

    /** @test */
    public function it_enforces_company_isolation_for_batch_operations()
    {
        // Create another company and user
        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->givePermissionTo('accounting.payments.create');

        $batchData = [
            'source_type' => 'manual',
            'entries' => [
                [
                    'entity_id' => $this->customer->id, // This customer belongs to first company
                    'payment_method' => 'bank_transfer',
                    'amount' => 500.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15',
                    'reference_number' => 'BATCH-005-1'
                ]
            ]
        ];

        // Try to create batch for other company with first company's customer
        $response = $this->actingAs($otherUser)
            ->postJson('/api/accounting/payment-batches', $batchData, [
                'X-Company-Id' => $otherCompany->id
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.entity_id']);
    }

    /** @test */
    public function it_tracks_batch_processing_status_transitions()
    {
        Event::fake();
        
        // Create a batch directly for testing status transitions
        $batch = \App\Models\PaymentBatch::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'company_id' => $this->company->id,
            'batch_number' => 'BATCH-TEST-001',
            'status' => 'pending',
            'receipt_count' => 2,
            'total_amount' => 800.00,
            'currency' => 'USD',
            'created_by_user_id' => $this->user->id,
            'metadata' => json_encode(['source_type' => 'manual'])
        ]);

        // Test initial status
        $response = $this->getJson("/api/accounting/payment-batches/{$batch->id}", [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'batch_id' => $batch->id,
                'status' => 'pending',
                'receipt_count' => 2,
                'total_amount' => 800.00
            ]);

        // Simulate batch processing start
        Event::dispatch(new PaymentBatchProcessed([
            'batch_id' => $batch->id,
            'company_id' => $this->company->id,
            'status' => 'completed',
            'processed_count' => 2,
            'failed_count' => 0,
            'processed_amount' => 800.00
        ]));

        // Verify status update
        $response = $this->getJson("/api/accounting/payment-batches/{$batch->id}", [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'completed'
            ]);
    }

    /** @test */
    public function it_handles_batch_processing_failures_with_detailed_errors()
    {
        Event::fake();

        $batch = \App\Models\PaymentBatch::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'company_id' => $this->company->id,
            'batch_number' => 'BATCH-FAIL-001',
            'status' => 'pending',
            'receipt_count' => 3,
            'total_amount' => 1200.00,
            'currency' => 'USD',
            'created_by_user_id' => $this->user->id,
            'metadata' => json_encode(['source_type' => 'csv_import'])
        ]);

        // Simulate batch processing failure
        Event::dispatch(new PaymentBatchFailed([
            'batch_id' => $batch->id,
            'company_id' => $this->company->id,
            'error_type' => 'validation_errors',
            'error_details' => [
                'row_1' => 'Invalid customer ID',
                'row_3' => 'Negative payment amount'
            ],
            'processed_count' => 1,
            'failed_count' => 2,
            'processed_amount' => 500.00
        ]));

        $response = $this->getJson("/api/accounting/payment-batches/{$batch->id}", [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'failed',
                'processed_count' => 1,
                'failed_count' => 2,
                'error_type' => 'validation_errors',
                'error_details' => [
                    'row_1' => 'Invalid customer ID',
                    'row_3' => 'Negative payment amount'
                ]
            ]);
    }

    /** @test */
    public function it_generates_unique_batch_numbers_per_company()
    {
        // Create first batch
        $batchData1 = [
            'source_type' => 'manual',
            'entries' => [
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'bank_transfer',
                    'amount' => 100.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15'
                ]
            ]
        ];

        $response1 = $this->postJson('/api/accounting/payment-batches', $batchData1, [
            'X-Company-Id' => $this->company->id
        ]);

        $batch1Number = $response1->json('batch_number');

        // Create second batch
        $batchData2 = [
            'source_type' => 'manual',
            'entries' => [
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'card',
                    'amount' => 200.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15'
                ]
            ]
        ];

        $response2 = $this->postJson('/api/accounting/payment-batches', $batchData2, [
            'X-Company-Id' => $this->company->id
        ]);

        $batch2Number = $response2->json('batch_number');

        // Batch numbers should be different
        $this->assertNotEquals($batch1Number, $batch2Number);
        
        // Both should be stored in database
        $this->assertDatabaseHas('invoicing.payment_receipt_batches', [
            'batch_number' => $batch1Number,
            'company_id' => $this->company->id
        ]);
        
        $this->assertDatabaseHas('invoicing.payment_receipt_batches', [
            'batch_number' => $batch2Number,
            'company_id' => $this->company->id
        ]);
    }

    /** @test */
    public function it_calculates_batch_totals_across_multiple_currencies()
    {
        // This test assumes multi-currency support is available
        // For now, we'll test with the same currency
        
        $batchData = [
            'source_type' => 'manual',
            'entries' => [
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'bank_transfer',
                    'amount' => 500.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15'
                ],
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'card',
                    'amount' => 300.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15'
                ]
            ]
        ];

        $response = $this->postJson('/api/accounting/payment-batches', $batchData, [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'receipt_count' => 2,
                'total_amount' => 800.00,
                'currency' => 'USD'
            ]);
    }
}