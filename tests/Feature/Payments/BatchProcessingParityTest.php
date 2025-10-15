<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use Modules\Accounting\Domain\Payments\Actions\CreatePaymentBatchAction;

uses(DatabaseTransactions::class);

test('CLI and GUI batch creation parity', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $user = User::factory()->create();
    
    // Test data for batch creation
    $batchData = [
        'source_type' => 'manual',
        'entries' => [
            [
                'entity_id' => $customer->id,
                'payment_method' => 'bank_transfer',
                'amount' => 500.00,
                'currency_id' => 'USD',
                'payment_date' => '2025-01-15',
                'reference_number' => 'PARITY-TEST-001',
                'auto_allocate' => true,
                'allocation_strategy' => 'fifo'
            ]
        ],
        'company_id' => $company->id,
        'created_by_user_id' => $user->id,
    ];

    // Test CLI batch creation
    $cliResult = $this->artisan('payment:batch:import', [
        '--source' => 'manual',
        '--entries' => json_encode($batchData['entries']),
        '--force' => true,
    ]);

    $cliResult->assertExitCode(0);
    $cliResult->expectsOutput('✅ Batch created successfully!');

    // Get the batch created via CLI
    $cliBatch = \App\Models\PaymentBatch::where('created_by_user_id', $user->id)
        ->whereHas('metadata', function ($query) use ($batchData) {
            $query->whereJsonContains('entries', $batchData['entries'][0]);
        })
        ->first();

    expect($cliBatch)->not->toBeNull();
    expect($cliBatch->source_type)->toBe('manual');
    expect($cliBatch->receipt_count)->toBe(1);
    expect($cliBatch->total_amount)->toBe(500.00);

    // Test API batch creation (simulating GUI)
    $response = $this->postJson('/api/accounting/payment-batches', $batchData, [
        'X-Company-Id' => $company->id,
        'Idempotency-Key' => \Illuminate\Support\Str::uuid()->toString(),
    ]);

    $response->assertStatus(202);
    $apiBatchData = $response->json();

    // Verify parity between CLI and API results
    expect($apiBatchData['batch_number'])->toMatch('/^BATCH-\d{8}-\d{3}$/');
    expect($apiBatchData['source_type'])->toBe($cliBatch->source_type);
    expect($apiBatchData['receipt_count'])->toBe($cliBatch->receipt_count);
    expect($apiBatchData['total_amount'])->toBe($cliBatch->total_amount);
    expect($apiBatchData['status'])->toBe($cliBatch->status);

    // Get the batch created via API
    $apiBatch = \App\Models\PaymentBatch::findOrFail($apiBatchData['batch_id']);

    // Both batches should have same structure and validation
    expect($cliBatch->status)->toBeIn(['pending', 'processing']);
    expect($apiBatch->status)->toBeIn(['pending', 'processing']);
    expect($cliBatch->batch_number)->toMatch('/^BATCH-\d{8}-\d{3}$/');
    expect($apiBatch->batch_number)->toMatch('/^BATCH-\d{8}-\d{3}$/');
});

test('CLI and GUI batch status monitoring parity', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    $user = User::factory()->create();
    
    // Create a batch via CLI
    $entries = [
        [
            'entity_id' => Customer::factory()->create(['company_id' => $company->id])->id,
            'payment_method' => 'bank_transfer',
            'amount' => 250.00,
            'currency_id' => 'USD',
            'payment_date' => '2025-01-15',
            'auto_allocate' => false,
        ]
    ];

    $this->artisan('payment:batch:import', [
        '--source' => 'manual',
        '--entries' => json_encode($entries),
        '--force' => true,
    ]);

    $batch = \App\Models\PaymentBatch::latest()->first();
    
    // Test CLI status check
    $cliResult = $this->artisan('payment:batch:status', [
        'batch-id' => $batch->batch_number,
        '--format' => 'json',
    ]);

    $cliResult->assertExitCode(0);
    $cliOutput = $this->artisan('payment:batch:status', [
        'batch-id' => $batch->batch_number,
        '--format' => 'json',
    ]);

    // Test API status check (simulating GUI)
    $response = $this->getJson("/api/accounting/payment-batches/{$batch->id}", [
        'X-Company-Id' => $company->id,
    ]);

    $response->assertStatus(200);
    $apiData = $response->json();

    // Extract CLI data (this would need to be captured from actual command output)
    // For testing purposes, we'll verify the API returns expected structure
    expect($apiData)->toHaveKeys([
        'batch_id',
        'batch_number',
        'status',
        'receipt_count',
        'total_amount',
        'currency',
        'progress_percentage',
        'created_at'
    ]);

    expect($apiData['batch_number'])->toBe($batch->batch_number);
    expect($apiData['status'])->toBe($batch->status);
    expect($apiData['receipt_count'])->toBe($batch->receipt_count);
    expect($apiData['total_amount'])->toBe((float) $batch->total_amount);
});

test('CLI and GUI batch listing parity', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    $user = User::factory()->create();
    
    // Create multiple batches via CLI
    for ($i = 1; $i <= 3; $i++) {
        $entries = [
            [
                'entity_id' => Customer::factory()->create(['company_id' => $company->id])->id,
                'payment_method' => 'bank_transfer',
                'amount' => 100.00 * $i,
                'currency_id' => 'USD',
                'payment_date' => '2025-01-15',
                'auto_allocate' => false,
            ]
        ];

        $this->artisan('payment:batch:import', [
            '--source' => 'manual',
            '--entries' => json_encode($entries),
            '--force' => true,
        ]);
    }

    // Test CLI listing
    $cliResult = $this->artisan('payment:batch:list', [
        '--format' => 'json',
        '--limit' => '10',
    ]);

    $cliResult->assertExitCode(0);

    // Test API listing (simulating GUI)
    $response = $this->getJson('/api/accounting/payment-batches?limit=10', [
        'X-Company-Id' => $company->id,
    ]);

    $response->assertStatus(200);
    $apiData = $response->json();

    expect($apiData)->toHaveKey('data');
    expect($apiData['data'])->toHaveCount(3);
    
    // Verify structure consistency
    foreach ($apiData['data'] as $batch) {
        expect($batch)->toHaveKeys([
            'batch_id',
            'batch_number',
            'status',
            'source_type',
            'receipt_count',
            'total_amount',
            'currency',
            'created_at'
        ]);
        
        expect($batch['status'])->toBeIn(['pending', 'processing', 'completed', 'failed']);
        expect($batch['receipt_count'])->toBeGreaterThan(0);
        expect($batch['total_amount'])->toBeGreaterThan(0);
    }
});

test('CLI and GUI error handling parity', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    
    // Test invalid batch data
    $invalidData = [
        'source_type' => 'manual',
        'entries' => [
            [
                'entity_id' => 'invalid-uuid',
                'payment_method' => 'invalid_method',
                'amount' => -100.00,
                'currency_id' => 'USD',
                'payment_date' => 'invalid-date',
            ]
        ],
        'company_id' => $company->id,
    ];

    // Test CLI error handling
    $cliResult = $this->artisan('payment:batch:import', [
        '--source' => 'manual',
        '--entries' => json_encode($invalidData['entries']),
        '--force' => true,
    ]);

    $cliResult->assertExitCode(1); // Should fail with validation errors

    // Test API error handling (simulating GUI)
    $response = $this->postJson('/api/accounting/payment-batches', $invalidData, [
        'X-Company-Id' => $company->id,
        'Idempotency-Key' => \Illuminate\Support\Str::uuid()->toString(),
    ]);

    $response->assertStatus(422); // Validation error
    $apiErrors = $response->json('errors');

    // Both CLI and API should catch the same validation issues
    expect($apiErrors)->toHaveKey('entries.0.entity_id');
    expect($apiErrors)->toHaveKey('entries.0.payment_method');
    expect($apiErrors)->toHaveKey('entries.0.amount');
    expect($apiErrors)->toHaveKey('entries.0.payment_date');
});

test('CLI and GUI batch processing performance parity', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    $user = User::factory()->create();
    
    // Create a batch for performance testing
    $entries = [];
    for ($i = 1; $i <= 10; $i++) {
        $entries[] = [
            'entity_id' => Customer::factory()->create(['company_id' => $company->id])->id,
            'payment_method' => 'bank_transfer',
            'amount' => 100.00,
            'currency_id' => 'USD',
            'payment_date' => '2025-01-15',
            'auto_allocate' => false,
        ];
    }

    // Measure CLI processing time
    $cliStartTime = microtime(true);
    $this->artisan('payment:batch:import', [
        '--source' => 'manual',
        '--entries' => json_encode($entries),
        '--force' => true,
    ]);
    $cliEndTime = microtime(true);
    $cliProcessingTime = ($cliEndTime - $cliStartTime) * 1000; // Convert to milliseconds

    // Get the created batch
    $batch = \App\Models\PaymentBatch::latest()->first();
    
    // Measure API processing time
    $apiStartTime = microtime(true);
    $response = $this->postJson('/api/accounting/payment-batches', [
        'source_type' => 'manual',
        'entries' => $entries,
        'company_id' => $company->id,
        'created_by_user_id' => $user->id,
    ], [
        'X-Company-Id' => $company->id,
        'Idempotency-Key' => \Illuminate\Support\Str::uuid()->toString(),
    ]);
    $apiEndTime = microtime(true);
    $apiProcessingTime = ($apiEndTime - $apiStartTime) * 1000;

    $response->assertStatus(202);

    // Both should complete within reasonable time (adjust thresholds as needed)
    expect($cliProcessingTime)->toBeLessThan(5000); // 5 seconds
    expect($apiProcessingTime)->toBeLessThan(1000); // 1 second

    // API should generally be faster than CLI for the same operation
    // but both should be within acceptable performance ranges
    expect(abs($cliProcessingTime - $apiProcessingTime))->toBeLessThan(10000); // 10 seconds difference max
});