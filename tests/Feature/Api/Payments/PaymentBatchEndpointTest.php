<?php

namespace Tests\Feature\Api\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use App\Models\PaymentBatch;

class PaymentBatchEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = Company::factory()->create();
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);

        DB::statement("SET app.current_company = ?", [$this->company->id]);
        
        $this->user->givePermissionTo('accounting.payments.create');
        $this->user->givePermissionTo('accounting.payments.view');
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        DB::statement("RESET app.current_company");
        parent::tearDown();
    }

    /** @test */
    public function it_creates_payment_batch_with_manual_entries()
    {
        Event::fake();
        Queue::fake();

        $payload = [
            'source_type' => 'manual',
            'entries' => [
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'bank_transfer',
                    'amount' => 500.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15',
                    'reference_number' => 'MANUAL-001',
                    'auto_allocate' => true,
                    'allocation_strategy' => 'fifo'
                ],
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'card',
                    'amount' => 250.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15',
                    'reference_number' => 'MANUAL-002',
                    'notes' => 'Overpayment expected'
                ]
            ],
            'notes' => 'Test manual batch creation'
        ];

        $response = $this->postJson('/api/accounting/payment-batches', $payload, [
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
                'message',
                'created_at'
            ])
            ->assertJson([
                'status' => 'pending',
                'receipt_count' => 2,
                'total_amount' => 750.00,
                'currency' => 'USD',
                'message' => 'Batch accepted for processing'
            ]);

        // Verify database record
        $this->assertDatabaseHas('invoicing.payment_receipt_batches', [
            'company_id' => $this->company->id,
            'status' => 'pending',
            'receipt_count' => 2,
            'total_amount' => 750.00,
            'currency' => 'USD',
            'created_by_user_id' => $this->user->id
        ]);

        // Verify event was dispatched
        Event::assertDispatched(\Modules\Accounting\Domain\Payments\Events\PaymentBatchCreated::class);

        // Verify job was queued
        Queue::assertPushed(\Modules\Accounting\Jobs\ProcessPaymentBatch::class);
    }

    /** @test */
    public function it_creates_payment_batch_from_csv_upload()
    {
        Event::fake();
        Queue::fake();

        $csvContent = "customer_id,payment_method,amount,currency,payment_date,reference_number,notes\n";
        $csvContent .= "{$this->customer->id},bank_transfer,750.50,USD,2025-01-15,CSV-001,Bank transfer\n";
        $csvContent .= "{$this->customer->id},card,250.25,USD,2025-01-15,CSV-002,Card payment\n";

        $file = \Illuminate\Http\UploadedFile::fake()
            ->createWithContent('payments.csv', $csvContent);

        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'csv_import',
            'file' => $file,
            'notes' => 'Test CSV import'
        ], [
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

        $batchId = $response->json('batch_id');

        // Verify database record
        $this->assertDatabaseHas('invoicing.payment_receipt_batches', [
            'id' => $batchId,
            'company_id' => $this->company->id,
            'status' => 'pending',
            'created_by_user_id' => $this->user->id
        ]);

        // Verify metadata contains CSV info
        $batch = PaymentBatch::find($batchId);
        $metadata = json_decode($batch->metadata, true);
        $this->assertEquals('csv_import', $metadata['source_type']);
        $this->assertArrayHasKey('original_filename', $metadata);
        $this->assertArrayHasKey('file_hash', $metadata);
    }

    /** @test */
    public function it_retrieves_batch_status()
    {
        $batch = PaymentBatch::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'company_id' => $this->company->id,
            'batch_number' => 'BATCH-STATUS-001',
            'status' => 'processing',
            'receipt_count' => 5,
            'total_amount' => 2500.00,
            'currency' => 'USD',
            'processed_at' => now(),
            'processing_started_at' => now()->subMinutes(5),
            'created_by_user_id' => $this->user->id,
            'metadata' => json_encode([
                'source_type' => 'csv_import',
                'original_filename' => 'test.csv'
            ])
        ]);

        $response = $this->getJson("/api/accounting/payment-batches/{$batch->id}", [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'batch_id',
                'batch_number',
                'status',
                'receipt_count',
                'total_amount',
                'currency',
                'created_at',
                'processing_started_at',
                'processed_at',
                'estimated_completion',
                'processed_count',
                'failed_count',
                'error_type',
                'error_details',
                'metadata',
                'payments'
            ])
            ->assertJson([
                'batch_id' => $batch->id,
                'batch_number' => 'BATCH-STATUS-001',
                'status' => 'processing',
                'receipt_count' => 5,
                'total_amount' => 2500.00,
                'currency' => 'USD',
                'metadata' => [
                    'source_type' => 'csv_import',
                    'original_filename' => 'test.csv'
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_batch()
    {
        $nonexistentId = \Illuminate\Support\Str::uuid();

        $response = $this->getJson("/api/accounting/payment-batches/{$nonexistentId}", [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Batch not found',
                'message' => 'The requested batch could not be found'
            ]);
    }

    /** @test */
    public function it_enforces_authorization_for_batch_creation()
    {
        $unauthorizedUser = User::factory()->create();
        // Don't give permissions

        $payload = [
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

        $response = $this->actingAs($unauthorizedUser)
            ->postJson('/api/accounting/payment-batches', $payload, [
                'X-Company-Id' => $this->company->id
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'message' => 'This action is unauthorized'
            ]);
    }

    /** @test */
    public function it_enforces_authorization_for_batch_viewing()
    {
        $unauthorizedUser = User::factory()->create();
        // Don't give view permissions

        $batch = PaymentBatch::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'company_id' => $this->company->id,
            'batch_number' => 'BATCH-AUTH-001',
            'status' => 'completed',
            'receipt_count' => 1,
            'total_amount' => 100.00,
            'currency' => 'USD',
            'created_by_user_id' => $this->user->id
        ]);

        $response = $this->actingAs($unauthorizedUser)
            ->getJson("/api/accounting/payment-batches/{$batch->id}", [
                'X-Company-Id' => $this->company->id
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_validates_required_fields_for_batch_creation()
    {
        // Test missing source_type
        $response = $this->postJson('/api/accounting/payment-batches', [], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['source_type']);

        // Test invalid source_type
        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'invalid'
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

        // Test manual without entries
        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'manual',
            'entries' => []
        ], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['entries']);
    }

    /** @test */
    public function it_validates_csv_file_format()
    {
        // Test non-CSV file
        $file = \Illuminate\Http\UploadedFile::fake()
            ->create('payments.txt', 'invalid content');

        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'csv_import',
            'file' => $file
        ], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        // Test empty CSV file
        $emptyFile = \Illuminate\Http\UploadedFile::fake()
            ->createWithContent('empty.csv', '');

        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'csv_import',
            'file' => $emptyFile
        ], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        // Test CSV with invalid headers
        $invalidHeaders = "wrong_headers\n1,2,3";
        $invalidFile = \Illuminate\Http\UploadedFile::fake()
            ->createWithContent('invalid.csv', $invalidHeaders);

        $response = $this->postJson('/api/accounting/payment-batches', [
            'source_type' => 'csv_import',
            'file' => $invalidFile
        ], [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    /** @test */
    public function it_validates_individual_payment_entries()
    {
        $payload = [
            'source_type' => 'manual',
            'entries' => [
                [
                    // Missing required entity_id
                    'payment_method' => 'bank_transfer',
                    'amount' => 100.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15'
                ],
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'invalid_method', // Invalid method
                    'amount' => 100.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15'
                ],
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'bank_transfer',
                    'amount' => -50.00, // Negative amount
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15'
                ],
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'bank_transfer',
                    'amount' => 0.00, // Zero amount
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15'
                ],
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'bank_transfer',
                    'amount' => 100.00,
                    'currency_id' => 'USD',
                    'payment_date' => 'invalid-date' // Invalid date
                ]
            ]
        ];

        $response = $this->postJson('/api/accounting/payment-batches', $payload, [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'entries.0.entity_id',
                'entries.1.payment_method',
                'entries.2.amount',
                'entries.3.amount',
                'entries.4.payment_date'
            ]);
    }

    /** @test */
    public function it_handles_idempotency_for_batch_creation()
    {
        $payload = [
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

        $idempotencyKey = 'test-idempotency-key-12345';

        // First request
        $response1 = $this->postJson('/api/accounting/payment-batches', $payload, [
            'X-Company-Id' => $this->company->id,
            'Idempotency-Key' => $idempotencyKey
        ]);

        $response1->assertStatus(202);
        $batchId = $response1->json('batch_id');

        // Second request with same idempotency key
        $response2 = $this->postJson('/api/accounting/payment-batches', $payload, [
            'X-Company-Id' => $this->company->id,
            'Idempotency-Key' => $idempotencyKey
        ]);

        $response2->assertStatus(409)
            ->assertJson([
                'error' => 'Duplicate batch creation',
                'message' => 'A batch with this idempotency key is already being processed'
            ]);

        // Should return the original batch ID
        $this->assertEquals($batchId, $response2->json('existing_batch_id'));
    }

    /** @test */
    public function it_enforces_company_isolation()
    {
        $otherCompany = Company::factory()->create();
        $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);

        // Try to create batch with other company's customer
        $payload = [
            'source_type' => 'manual',
            'entries' => [
                [
                    'entity_id' => $otherCustomer->id,
                    'payment_method' => 'bank_transfer',
                    'amount' => 100.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15'
                ]
            ]
        ];

        $response = $this->postJson('/api/accounting/payment-batches', $payload, [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['entries.0.entity_id']);

        // Try to view other company's batch
        $otherBatch = PaymentBatch::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'company_id' => $otherCompany->id,
            'batch_number' => 'OTHER-BATCH-001',
            'status' => 'completed',
            'receipt_count' => 1,
            'total_amount' => 100.00,
            'currency' => 'USD',
            'created_by_user_id' => $this->user->id
        ]);

        $response = $this->getJson("/api/accounting/payment-batches/{$otherBatch->id}", [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_bank_feed_source_type()
    {
        Event::fake();
        Queue::fake();

        $payload = [
            'source_type' => 'bank_feed',
            'entries' => [
                [
                    'entity_id' => $this->customer->id,
                    'payment_method' => 'bank_transfer',
                    'amount' => 1000.00,
                    'currency_id' => 'USD',
                    'payment_date' => '2025-01-15',
                    'reference_number' => 'BANK-FEED-001',
                    'notes' => 'Automated bank feed import'
                ]
            ],
            'metadata' => [
                'bank_account' => 'ACC-12345',
                'feed_source' => 'plaid',
                'import_date' => '2025-01-15T10:30:00Z'
            ]
        ];

        $response = $this->postJson('/api/accounting/payment-batches', $payload, [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(202);

        $batchId = $response->json('batch_id');

        // Verify metadata is stored
        $batch = PaymentBatch::find($batchId);
        $metadata = json_decode($batch->metadata, true);
        $this->assertEquals('bank_feed', $metadata['source_type']);
        $this->assertArrayHasKey('bank_account', $metadata);
        $this->assertArrayHasKey('feed_source', $metadata);
    }

    /** @test */
    public function it_returns_estimated_completion_time()
    {
        $payload = [
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

        $response = $this->postJson('/api/accounting/payment-batches', $payload, [
            'X-Company-Id' => $this->company->id
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure(['estimated_completion']);

        $estimatedCompletion = $response->json('estimated_completion');
        $this->assertNotNull($estimatedCompletion);
        $this->assertGreaterThan(now(), \Carbon\Carbon::parse($estimatedCompletion));
    }
}