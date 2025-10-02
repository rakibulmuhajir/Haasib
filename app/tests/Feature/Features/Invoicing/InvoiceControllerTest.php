<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Create test user and company
    $this->user = User::factory()->create();
    $this->company = Company::factory()->create();

    // Attach user to company
    $this->user->companies()->attach($this->company->id, ['role' => 'admin']);

    // Set current company in session
    session(['current_company_id' => $this->company->id]);

    // Create currency manually
    $this->currency = Currency::create([
        'id' => (string) Str::uuid(),
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'minor_unit' => 2,
        'is_active' => true,
        'exchange_rate' => 1.0,
    ]);

    // Create customer
    $this->customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $this->company->id,
        'customer_number' => 'CUST-001',
        'name' => 'Test Customer',
        'email' => 'test@example.com',
        'currency_id' => $this->currency->id,
    ]);

    // Acting as user
    $this->actingAs($this->user);

    // Verify user has current_company_id set
    $this->assertNotNull($this->user->current_company_id, 'User must have current_company_id set');
});

test('store method creates invoice successfully', function () {
    // Arrange
    $invoiceData = [
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'invoice_number' => 'INV-TEST-001',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'notes' => 'Test invoice notes',
        'terms' => 'Test terms and conditions',
        'items' => [
            [
                'description' => 'Test Product',
                'quantity' => 2,
                'unit_price' => 50.00,
                'taxes' => [
                    ['name' => 'VAT', 'rate' => 10.0],
                ],
            ],
            [
                'description' => 'Service Fee',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Act
    $response = $this->post(route('invoices.store'), $invoiceData);

    // Assert
    $response->assertSessionHas('success', 'Invoice created successfully');

    // Get the invoice from the database
    $this->assertDatabaseHas('invoices', [
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->customer_id,
        'invoice_number' => 'INV-TEST-001',
        'notes' => 'Test invoice notes',
        'terms' => 'Test terms and conditions',
    ]);

    $invoice = Invoice::where('invoice_number', 'INV-TEST-001')->first();

    // Now check the redirect
    $response->assertRedirect(route('invoices.show', $invoice));
    $this->assertCount(2, $invoice->items);
    $this->assertEquals('Test Product', $invoice->items->first()->description);
    $this->assertCount(1, $invoice->items->first()->taxes);
});

test('store method handles idempotency key', function () {
    // Arrange
    $idempotencyKey = 'test-idempotency-key-12345';
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'invoice_number' => 'INV-TEST-002',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'idempotency_key' => $idempotencyKey,
        'items' => [
            [
                'description' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Act - First request
    $response1 = $this->post(route('invoices.store'), $invoiceData);
    $firstInvoice = Invoice::first();

    // Act - Second request with same idempotency key
    $response2 = $this->post(route('invoices.store'), $invoiceData);

    // Assert
    $response1->assertRedirect(route('invoices.show', $firstInvoice));
    $response2->assertRedirect(route('invoices.show', $firstInvoice));

    // Only one invoice should be created
    $this->assertEquals(1, Invoice::count());

    // The invoice should have the idempotency key stored
    $this->assertDatabaseHas('invoices', [
        'invoice_id' => $firstInvoice->invoice_id,
        'idempotency_key' => $idempotencyKey,
    ]);
});

test('store method handles idempotency key from header', function () {
    // Arrange
    $idempotencyKey = 'header-key-67890';
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'invoice_number' => 'INV-TEST-003',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'items' => [
            [
                'description' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Act - Request with idempotency header
    $response = $this->withHeaders([
        'Idempotency-Key' => $idempotencyKey,
    ])->post(route('invoices.store'), $invoiceData);

    $invoice = Invoice::first();

    // Assert
    $response->assertRedirect(route('invoices.show', $invoice));
    $this->assertEquals(1, Invoice::count());

    // The invoice should have the idempotency key from header
    $this->assertDatabaseHas('invoices', [
        'invoice_id' => $invoice->invoice_id,
        'idempotency_key' => $idempotencyKey,
    ]);
});

test('store method validates required fields', function () {
    // Arrange - Empty request
    $response = $this->post(route('invoices.store'), []);

    // Assert
    $response->assertSessionHasErrors([
        'customer_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'items',
    ]);
});

test('store method validates items array', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'invoice_number' => 'INV-TEST-004',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'items' => [], // Empty items array
    ];

    // Act
    $response = $this->post(route('invoices.store'), $invoiceData);

    // Assert
    $response->assertSessionHasErrors(['items']);
});

test('store method validates item fields', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'invoice_number' => 'INV-TEST-005',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'items' => [
            [
                'description' => '', // Empty description
                'quantity' => -1, // Invalid quantity
                'unit_price' => 'invalid', // Invalid price
            ],
        ],
    ];

    // Act
    $response = $this->post(route('invoices.store'), $invoiceData);

    // Assert
    $response->assertSessionHasErrors([
        'items.0.description',
        'items.0.quantity',
        'items.0.unit_price',
    ]);
});

test('store method validates date fields', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'invoice_number' => 'INV-TEST-006',
        'invoice_date' => 'invalid-date',
        'due_date' => '2025-01-15',
        'items' => [
            [
                'description' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Act
    $response = $this->post(route('invoices.store'), $invoiceData);

    // Assert
    $response->assertSessionHasErrors([
        'invoice_date',
    ]);
});

test('store method validates due_date is after invoice_date', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'invoice_number' => 'INV-TEST-007',
        'invoice_date' => '2025-01-15',
        'due_date' => '2025-01-01', // Due date before invoice date
        'items' => [
            [
                'description' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Act
    $response = $this->post(route('invoices.store'), $invoiceData);

    // Assert
    $response->assertSessionHasErrors([
        'due_date',
    ]);
});

test('store method validates customer exists', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => '00000000-0000-0000-0000-000000000000',
        'currency_id' => $this->currency->id,
        'invoice_number' => 'INV-TEST-008',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'items' => [
            [
                'description' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Act
    $response = $this->post(route('invoices.store'), $invoiceData);

    // Assert
    $response->assertSessionHasErrors([
        'customer_id',
    ]);
});

test('store method validates currency exists when provided', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => 'non-existent-currency-id',
        'invoice_number' => 'INV-TEST-009',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'items' => [
            [
                'description' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Act
    $response = $this->post(route('invoices.store'), $invoiceData);

    // Assert
    $response->assertSessionHasErrors([
        'currency_id',
    ]);
});

test('store method handles idempotency key validation', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'invoice_number' => 'INV-TEST-010',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'idempotency_key' => str_repeat('a', 256), // Too long
        'items' => [
            [
                'description' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Act
    $response = $this->post(route('invoices.store'), $invoiceData);

    // Assert
    $response->assertSessionHasErrors([
        'idempotency_key',
    ]);
});

test('store method maintains company isolation with idempotency', function () {
    // Arrange - Create another company
    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->create();

    // Attach user to company
    $otherUser->companies()->attach($otherCompany->id, ['role' => 'admin']);

    $otherCustomer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $otherCompany->id,
        'customer_number' => 'CUST-002',
        'name' => 'Other Test Customer',
        'currency_id' => $this->currency->id,
    ]);

    $idempotencyKey = 'cross-company-key';
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'invoice_number' => 'INV-TEST-011',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'idempotency_key' => $idempotencyKey,
        'items' => [
            [
                'description' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Act - Create invoice in first company
    $this->withSession(['current_company_id' => $this->company->id])
        ->actingAs($this->user)
        ->post(route('invoices.store'), $invoiceData);

    // Act - Try same idempotency key in different company
    $otherInvoiceData = [
        'customer_id' => $otherCustomer->customer_id,
        'invoice_number' => 'INV-TEST-012',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'idempotency_key' => $idempotencyKey,
        'items' => [
            [
                'description' => 'Other Product',
                'quantity' => 1,
                'unit_price' => 200.00,
            ],
        ],
    ];

    $response = $this->withSession(['current_company_id' => $otherCompany->id])
        ->actingAs($otherUser)
        ->post(route('invoices.store'), $otherInvoiceData);

    // Assert - Should create a new invoice (different company)
    $response->assertRedirect();
    $this->assertEquals(2, Invoice::count()); // Two invoices created
});

test('store method logs errors on failure', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => '00000000-0000-0000-0000-000000000001',
        'items' => [
            [
                'description' => 'Test Product',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Act
    $response = $this->post(route('invoices.store'), $invoiceData);

    // Assert
    $response->assertSessionHasErrors(['customer_id']);
    $response->assertSessionHas('error');
});
