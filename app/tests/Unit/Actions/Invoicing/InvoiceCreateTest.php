<?php

use App\Actions\Invoicing\InvoiceCreate;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function () {
    // Create a simple test user object
    $this->user = new User;
    $this->user->id = 'test-user-id';
    $this->user->current_company_id = 'test-company-id';

    // Mock the InvoiceService
    $this->invoiceService = mock(InvoiceService::class);
    $this->action = new InvoiceCreate($this->invoiceService);
});

test('it creates invoice successfully', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => 'test-customer-id',
        'currency_id' => 'test-currency-id',
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'notes' => 'Test invoice',
        'items' => [
            [
                'description' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // Mock Invoice query to return null (no existing invoice)
    Invoice::shouldReceive('where->first')->andReturnNull();

    $expectedInvoice = mock(Invoice::class);
    $expectedInvoice->invoice_id = (string) Str::uuid();
    $expectedInvoice->invoice_number = 'INV-2025-001';
    $expectedInvoice->status = 'draft';
    $expectedInvoice->total_amount = 100.00;

    $this->invoiceService->shouldReceive('createInvoice')
        ->once()
        ->with(
            \Mockery::on(function ($arg) {
                return is_array($arg) || $arg->id === $this->company->id;
            }),
            \Mockery::on(function ($arg) use ($invoiceData) {
                return is_array($arg) &&
                       $arg['customer_id'] === $invoiceData['customer_id'] &&
                       $arg['currency_id'] === $invoiceData['currency_id'] &&
                       $arg['items'] === $invoiceData['items'] &&
                       $arg['idempotency_key'] === null;
            })
        )
        ->andReturn($expectedInvoice);

    // Act
    $result = $this->action->handle($invoiceData, $this->user);

    // Assert
    expect($result)->toEqual([
        'message' => 'Invoice created successfully',
        'data' => [
            'id' => $expectedInvoice->invoice_id,
            'invoice_number' => $expectedInvoice->invoice_number,
            'status' => $expectedInvoice->status,
            'total_amount' => $expectedInvoice->total_amount,
        ],
        'idempotent' => false,
    ]);
});

test('it handles idempotency key', function () {
    // Arrange
    $idempotencyKey = 'test-key-123';
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'idempotency_key' => $idempotencyKey,
        'items' => [
            [
                'description' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    $expectedInvoice = new \App\Models\Invoice;
    $expectedInvoice->invoice_id = (string) \Illuminate\Support\Str::uuid();
    $expectedInvoice->invoice_number = 'INV-2025-001';
    $expectedInvoice->status = 'draft';
    $expectedInvoice->total_amount = 100.00;

    $this->invoiceService->shouldReceive('createInvoice')
        ->once()
        ->andReturn($expectedInvoice);

    // Act
    $result = $this->action->handle($invoiceData, $this->user);

    // Assert
    expect($result['idempotent'])->toBeFalse();
    expect($result['message'])->toBe('Invoice created successfully');
});

test('it returns existing invoice for duplicate idempotency key', function () {
    // Arrange
    $idempotencyKey = 'test-key-123';

    // Create an existing invoice with the idempotency key
    $existingInvoice = \App\Models\Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->customer_id,
        'idempotency_key' => $idempotencyKey,
        'status' => 'draft',
        'total_amount' => 100.00,
    ]);

    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'invoice_date' => '2025-01-01',
        'due_date' => '2025-01-15',
        'idempotency_key' => $idempotencyKey,
        'items' => [
            [
                'description' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    // The service should NOT be called when idempotency key matches
    $this->invoiceService->shouldNotReceive('createInvoice');

    // Act
    $result = $this->action->handle($invoiceData, $this->user);

    // Assert
    expect($result)->toEqual([
        'message' => 'Invoice already exists (idempotent request)',
        'data' => [
            'id' => $existingInvoice->invoice_id,
            'invoice_number' => $existingInvoice->invoice_number,
            'status' => $existingInvoice->status,
            'total_amount' => $existingInvoice->total_amount,
        ],
        'idempotent' => true,
    ]);
});

test('it logs duplicate request detection', function () {
    // Arrange
    $idempotencyKey = 'test-key-123';

    // Create an existing invoice with the idempotency key
    $existingInvoice = \App\Models\Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->customer_id,
        'idempotency_key' => $idempotencyKey,
    ]);

    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'idempotency_key' => $idempotencyKey,
        'items' => [
            [
                'description' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    Log::shouldReceive('info')
        ->once()
        ->with('Duplicate invoice request detected', \Mockery::on(function ($arg) use ($idempotencyKey) {
            return $arg['idempotency_key'] === $idempotencyKey &&
                   $arg['company_id'] === $this->company->id &&
                   $arg['existing_invoice_id'] === $existingInvoice->invoice_id &&
                   $arg['actor_id'] === $this->user->id;
        }));

    // Act
    $result = $this->action->handle($invoiceData, $this->user);

    // Assert
    expect($result['idempotent'])->toBeTrue();
});

test('it uses user company_id when not provided', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'currency_id' => $this->currency->id,
        'items' => [
            [
                'description' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    $expectedInvoice = new \App\Models\Invoice;
    $expectedInvoice->invoice_id = (string) \Illuminate\Support\Str::uuid();
    $expectedInvoice->invoice_number = 'INV-2025-001';

    $this->invoiceService->shouldReceive('createInvoice')
        ->once()
        ->with(
            \Mockery::on(function ($arg) {
                return $arg->id === $this->company->id;
            }),
            \Mockery::on(function ($arg) {
                return is_array($arg) && $arg['customer_id'] === $this->customer->customer_id;
            })
        )
        ->andReturn($expectedInvoice);

    // Act
    $result = $this->action->handle($invoiceData, $this->user);

    // Assert
    expect($result['data']['id'])->toBe($expectedInvoice->invoice_id);
});

test('it handles nullable fields gracefully', function () {
    // Arrange
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'items' => [
            [
                'description' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
        // Optional fields omitted
    ];

    $expectedInvoice = new \App\Models\Invoice;
    $expectedInvoice->invoice_id = (string) \Illuminate\Support\Str::uuid();
    $expectedInvoice->invoice_number = 'INV-2025-001';

    $this->invoiceService->shouldReceive('createInvoice')
        ->once()
        ->andReturn($expectedInvoice);

    // Act
    $result = $this->action->handle($invoiceData, $this->user);

    // Assert
    expect($result['message'])->toBe('Invoice created successfully');
});

test('it passes idempotency_key to service', function () {
    // Arrange
    $idempotencyKey = 'test-key-456';
    $invoiceData = [
        'customer_id' => $this->customer->customer_id,
        'idempotency_key' => $idempotencyKey,
        'items' => [
            [
                'description' => 'Test Item',
                'quantity' => 1,
                'unit_price' => 100.00,
            ],
        ],
    ];

    $expectedInvoice = new \App\Models\Invoice;
    $expectedInvoice->invoice_id = (string) \Illuminate\Support\Str::uuid();

    $this->invoiceService->shouldReceive('createInvoice')
        ->once()
        ->with(
            \Mockery::any(),
            \Mockery::on(function ($arg) use ($idempotencyKey) {
                return is_array($arg) && $arg['idempotency_key'] === $idempotencyKey;
            })
        )
        ->andReturn($expectedInvoice);

    // Act
    $result = $this->action->handle($invoiceData, $this->user);

    // Assert
    expect($result['idempotent'])->toBeFalse();
});
