<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use App\Models\Customer;
use Pest\Laravel\browser;

uses(DatabaseTransactions::class);

test('browser test for batch processing GUI functionality', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $user = User::factory()->create();
    
    // Login user and set company context
    $this->actingAs($user);
    
    browser()
        ->visit('/login')
        ->type('email', $user->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs('/dashboard')
        ->assertSee('Accounting')
        
        // Navigate to payment batches page
        ->clickLink('Accounting')
        ->clickLink('Payments')
        ->clickLink('Batches')
        ->assertPathIs('/accounting/payments/batches')
        ->assertSee('Payment Batches')
        
        // Test batch creation button
        ->assertSee('Create New Batch')
        ->press('Create New Batch')
        ->assertSee('Create Payment Batch')
        
        // Fill out batch creation form
        ->select('source_type', 'manual')
        ->type('entity_id', $customer->id)
        ->select('payment_method', 'bank_transfer')
        ->type('amount', '500.00')
        ->type('payment_date', '2025-01-15')
        ->type('reference_number', 'BROWSER-TEST-001')
        ->check('auto_allocate')
        ->select('allocation_strategy', 'fifo')
        
        // Submit form
        ->press('Create Batch')
        ->assertSee('Batch created successfully')
        
        // Verify batch appears in list
        ->assertSee('BROWSER-TEST-001')
        ->assertSee('bank_transfer')
        ->assertSee('$500.00')
        
        // Test batch status viewing
        ->clickLink('View Details')
        ->assertSee('Batch Details')
        ->assertSee('BROWSER-TEST-001')
        ->assertSee('Manual Entry')
        ->assertSee('Processing')
        
        // Verify batch metadata
        ->assertSee('Created At')
        ->assertSee('Receipt Count')
        ->assertSee('Total Amount')
        
        // Test batch actions
        ->assertSee('Refresh Status')
        ->assertSee('Download Report');
});

test('browser test for CSV upload functionality', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $user = User::factory()->create();
    
    // Create a test CSV file
    $csvContent = implode(',', [
        'entity_id,payment_method,amount,currency,payment_date,reference_number,auto_allocate,allocation_strategy',
        "{$customer->id},bank_transfer,250.00,USD,2025-01-15,CSV-TEST-001,true,fifo"
    ]);
    
    $csvPath = sys_get_temp_dir() . '/test_batch.csv';
    file_put_contents($csvPath, $csvContent);
    
    // Login user
    $this->actingAs($user);
    
    browser()
        ->visit('/login')
        ->type('email', $user->email)
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs('/dashboard')
        
        // Navigate to batch creation
        ->visit('/accounting/payments/batches')
        ->press('Create New Batch')
        ->select('source_type', 'csv_import')
        
        // Upload CSV file
        ->attach('file', $csvPath)
        ->press('Upload and Process')
        ->assertSee('File uploaded successfully')
        ->assertSee('CSV-TEST-001')
        
        // Verify CSV validation
        ->assertSee('1 valid row')
        ->assertSee('Processing started');
        
    // Cleanup
    unlink($csvPath);
});

test('browser test for batch error handling', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    $user = User::factory()->create();
    
    // Login user
    $this->actingAs($user);
    
    browser()
        ->visit('/login')
        ->type('email', $user->email)
        ->type('password', 'password')
        ->press('Log in')
        ->visit('/accounting/payments/batches')
        ->press('Create New Batch')
        
        // Submit empty form
        ->press('Create Batch')
        ->assertSee('The entity id field is required')
        ->assertSee('The amount field is required')
        ->assertSee('The payment date field is required')
        
        // Test invalid data
        ->type('entity_id', 'invalid-uuid')
        ->type('amount', '-100.00')
        ->type('payment_date', 'invalid-date')
        ->press('Create Batch')
        ->assertSee('The selected entity id is invalid')
        ->assertSee('The amount must be at least 0.01')
        ->assertSee('The payment date is not a valid date');
});

test('browser test for batch status updates', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $user = User::factory()->create();
    
    // Create a batch via API first
    $this->actingAs($user);
    
    $response = $this->postJson('/api/accounting/payment-batches', [
        'source_type' => 'manual',
        'entries' => [[
            'entity_id' => $customer->id,
            'payment_method' => 'bank_transfer',
            'amount' => 100.00,
            'currency_id' => 'USD',
            'payment_date' => '2025-01-15',
            'auto_allocate' => false,
        ]],
        'company_id' => $company->id,
        'created_by_user_id' => $user->id,
    ], [
        'X-Company-Id' => $company->id,
        'Idempotency-Key' => \Illuminate\Support\Str::uuid()->toString(),
    ]);
    
    $batchId = $response->json('batch_id');
    
    browser()
        ->actingAs($user)
        ->visit("/accounting/payments/batches/{$batchId}")
        ->assertSee('Batch Details')
        ->assertSee('Processing')
        
        // Test status refresh
        ->press('Refresh Status')
        ->waitForText('Updated', 5)
        
        // Test progress indicator
        ->assertPresent('[data-testid="progress-bar"]')
        ->assertPresent('[data-testid="status-indicator"]')
        
        // Verify batch information display
        ->assertSee('Batch Number')
        ->assertSee('Source Type')
        ->assertSee('Receipt Count')
        ->assertSee('Total Amount');
});

test('browser test for batch filtering and search', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    $user = User::factory()->create();
    
    // Create multiple batches with different statuses
    for ($i = 1; $i <= 3; $i++) {
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        
        $this->actingAs($user)->postJson('/api/accounting/payment-batches', [
            'source_type' => 'manual',
            'entries' => [[
                'entity_id' => $customer->id,
                'payment_method' => 'bank_transfer',
                'amount' => 100.00 * $i,
                'currency_id' => 'USD',
                'payment_date' => "2025-01-{$i:02d}",
                'auto_allocate' => false,
            ]],
            'company_id' => $company->id,
            'created_by_user_id' => $user->id,
        ], [
            'X-Company-Id' => $company->id,
            'Idempotency-Key' => \Illuminate\Support\Str::uuid()->toString(),
        ]);
    }
    
    browser()
        ->actingAs($user)
        ->visit('/accounting/payments/batches')
        ->assertSee('Payment Batches')
        
        // Test status filter
        ->select('filter_status', 'pending')
        ->press('Apply Filters')
        ->assertSee('Showing batches with status: pending')
        
        // Test source type filter
        ->select('filter_source', 'manual')
        ->press('Apply Filters')
        ->assertSee('Showing manual entry batches')
        
        // Test date range filter
        ->type('filter_date_from', '2025-01-01')
        ->type('filter_date_to', '2025-01-31')
        ->press('Apply Filters')
        ->assertSee('Showing batches from 2025-01-01 to 2025-01-31')
        
        // Test search functionality
        ->type('search', 'BATCH')
        ->press('Search')
        ->assertSee('Search results for "BATCH"')
        
        // Test clear filters
        ->press('Clear Filters')
        ->assertSee('All filters cleared');
});

test('browser test for responsive design', function () {
    $this->artisan('migrate:fresh --seed');
    
    // Setup test data
    $company = Company::factory()->create();
    $user = User::factory()->create();
    
    browser()
        ->actingAs($user)
        ->visit('/login')
        ->type('email', $user->email)
        ->type('password', 'password')
        ->press('Log in')
        ->visit('/accounting/payments/batches')
        
        // Test desktop view
        ->resize(1200, 800)
        ->assertSee('Payment Batches')
        ->assertVisible('[data-testid="batch-table"]')
        ->assertVisible('[data-testid="batch-actions"]')
        
        // Test tablet view
        ->resize(768, 1024)
        ->assertSee('Payment Batches')
        ->assertVisible('[data-testid="batch-table"]')
        ->assertVisible('[data-testid="batch-actions"]')
        
        // Test mobile view
        ->resize(375, 667)
        ->assertSee('Payment Batches')
        ->assertVisible('[data-testid="batch-list"]') // Should switch to list view
        ->assertVisible('[data-testid="mobile-menu"]')
        
        // Test navigation on mobile
        ->click('[data-testid="mobile-menu-toggle"]')
        ->assertVisible('[data-testid="mobile-nav"]')
        ->clickLink('Accounting')
        ->assertPathIs('/accounting');
});