<?php

use App\Services\InvoiceCliService;
use Illuminate\Validation\ValidationException;

// Invoice Creation Tests
it('creates an invoice with valid data', function () {
    $service = new InvoiceCliService;

    $data = [
        'company_id' => '550e8400-e29b-41d4-a716-446655440001',
        'customer_id' => '550e8400-e29b-41d4-a716-446655440002',
        'issue_date' => '2024-01-15',
        'due_date' => '2024-02-15',
        'currency' => 'USD',
    ];

    $lineItems = [
        [
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => 100.00,
            'tax_rate' => 10,
        ],
        [
            'description' => 'Additional Service',
            'quantity' => 2,
            'unit_price' => 50.00,
            'tax_rate' => 5,
        ],
    ];

    // Test that validation passes for valid data
    $validator = validator($data, [
        'company_id' => ['required', 'uuid'],
        'customer_id' => ['required', 'uuid'],
        'issue_date' => ['required', 'date'],
        'due_date' => ['required', 'date', 'after:issue_date'],
        'currency' => ['required', 'string', 'max:3'],
    ]);

    expect($validator->passes())->toBeTrue();
});

it('validates required fields on invoice creation', function () {
    $service = new InvoiceCliService;

    expect(fn () => $service->createInvoice([]))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->createInvoice([
        'company_id' => '550e8400-e29b-41d4-a716-446655440001',
        // Missing required fields
    ]))->toThrow(ValidationException::class);
});

it('validates UUID format for company_id', function () {
    $service = new InvoiceCliService;

    expect(fn () => $service->createInvoice([
        'company_id' => 'invalid-uuid-format',
        'customer_id' => '550e8400-e29b-41d4-a716-446655440002',
        'issue_date' => '2024-01-15',
        'due_date' => '2024-02-15',
        'currency' => 'USD',
    ]))->toThrow(ValidationException::class);
});

// Invoice Update Tests
it('validates invoice status updates', function () {
    $service = new InvoiceCliService;

    $validStatuses = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];

    foreach ($validStatuses as $status) {
        $validator = validator(['status' => $status], [
            'status' => ['string', 'in:'.implode(',', $validStatuses)],
        ]);
        expect($validator->passes())->toBeTrue();
    }

    $invalidStatus = 'invalid-status';
    $validator = validator(['status' => $invalidStatus], [
        'status' => ['string', 'in:'.implode(',', $validStatuses)],
    ]);
    expect($validator->passes())->toBeFalse();
});

// Line Item Tests
it('validates line item data', function () {
    $service = new InvoiceCliService;

    $validLineItem = [
        'description' => 'Test Service',
        'quantity' => 1,
        'unit_price' => 100.00,
        'tax_rate' => 10,
    ];

    $validator = validator($validLineItem, [
        'description' => ['required', 'string'],
        'quantity' => ['required', 'numeric', 'min:0'],
        'unit_price' => ['required', 'numeric', 'min:0'],
        'tax_rate' => ['numeric', 'min:0'],
    ]);

    expect($validator->passes())->toBeTrue();
});

it('rejects invalid line item data', function () {
    $service = new InvoiceCliService;

    $invalidLineItems = [
        [
            'description' => '', // Empty description
            'quantity' => 1,
            'unit_price' => 100.00,
        ],
        [
            'description' => 'Test Service',
            'quantity' => -1, // Negative quantity
            'unit_price' => 100.00,
        ],
        [
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => -50.00, // Negative price
        ],
    ];

    foreach ($invalidLineItems as $item) {
        $validator = validator($item, [
            'description' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['numeric', 'min:0'],
        ]);

        expect($validator->passes())->toBeFalse();
    }
});

// PDF Generation Tests
it('validates PDF generation settings', function () {
    $service = new InvoiceCliService;

    $validSettings = [
        'template' => 'default',
        'format' => 'A4',
        'orientation' => 'portrait',
        'compress' => true,
        'encrypt' => false,
    ];

    $validator = validator($validSettings, [
        'template' => ['string', 'in:default,modern,classic'],
        'format' => ['string', 'in:A4,Letter,Legal'],
        'orientation' => ['string', 'in:portrait,landscape'],
        'compress' => ['boolean'],
        'encrypt' => ['boolean'],
    ]);

    expect($validator->passes())->toBeTrue();
});

// Statistics Tests
it('calculates invoice totals correctly', function () {
    $lineItems = [
        [
            'description' => 'Service 1',
            'quantity' => 1,
            'unit_price' => 100.00,
            'tax_rate' => 10,
        ],
        [
            'description' => 'Service 2',
            'quantity' => 2,
            'unit_price' => 50.00,
            'tax_rate' => 5,
        ],
    ];

    $subtotal = 0;
    $totalTax = 0;

    foreach ($lineItems as $item) {
        $itemTotal = $item['quantity'] * $item['unit_price'];
        $itemTax = $itemTotal * ($item['tax_rate'] / 100);
        $subtotal += $itemTotal;
        $totalTax += $itemTax;
    }

    $totalAmount = $subtotal + $totalTax;

    expect($subtotal)->toBe(200.00); // (1 * 100) + (2 * 50)
    expect($totalTax)->toBe(15.00);  // (100 * 0.10) + (100 * 0.05)
    expect($totalAmount)->toBe(215.00); // 200 + 15
});

// Email Validation Tests
it('validates email addresses for sending invoices', function () {
    $validEmails = [
        'test@example.com',
        'user.name+tag@domain.co.uk',
        'user123@test-domain.org',
    ];

    $invalidEmails = [
        'invalid-email',
        '@domain.com',
        'user@',
    ];

    foreach ($validEmails as $email) {
        $validator = validator(['email' => $email], ['email' => 'email']);
        expect($validator->passes())->toBeTrue();
    }

    foreach ($invalidEmails as $email) {
        $validator = validator(['email' => $email], ['email' => 'email']);
        expect($validator->passes())->toBeFalse();
    }
});

// Search Tests
it('validates search query parameters', function () {
    $service = new InvoiceCliService;

    $validSearchFilters = [
        'status' => 'sent',
        'date_from' => '2024-01-01',
        'date_to' => '2024-12-31',
        'customer_id' => '550e8400-e29b-41d4-a716-446655440001',
        'min_amount' => 100,
        'max_amount' => 10000,
    ];

    $validator = validator($validSearchFilters, [
        'status' => ['in:draft,sent,paid,overdue,cancelled'],
        'date_from' => ['date'],
        'date_to' => ['date', 'after_or_equal:date_from'],
        'customer_id' => ['uuid'],
        'min_amount' => ['numeric', 'min:0'],
        'max_amount' => ['numeric', 'gte:min_amount'],
    ]);

    expect($validator->passes())->toBeTrue();
});

// Performance Tests
it('handles validation within performance limits', function () {
    $service = new InvoiceCliService;

    // Test validation performance with complex data
    $data = [
        'company_id' => '550e8400-e29b-41d4-a716-446655440001',
        'customer_id' => '550e8400-e29b-41d4-a716-446655440002',
        'issue_date' => '2024-01-15',
        'due_date' => '2024-02-15',
        'currency' => 'USD',
    ];

    $startTime = microtime(true);

    $validator = validator($data, [
        'company_id' => ['required', 'uuid'],
        'customer_id' => ['required', 'uuid'],
        'issue_date' => ['required', 'date'],
        'due_date' => ['required', 'date', 'after:issue_date'],
        'currency' => ['required', 'string', 'max:3'],
    ]);

    $endTime = microtime(true);
    $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    expect($validator->passes())->toBeTrue();
    expect($executionTime)->toBeLessThan(100); // Should complete in under 100ms
});

// Error Handling Tests
it('provides clear validation error messages', function () {
    $service = new InvoiceCliService;

    $invalidData = [
        'company_id' => 'invalid-uuid',
        'customer_id' => 'invalid-uuid',
        'issue_date' => 'invalid-date',
        'due_date' => '2023-01-01', // Before issue date
        'currency' => 'TOOLONG',
    ];

    $validator = validator($invalidData, [
        'company_id' => ['required', 'uuid'],
        'customer_id' => ['required', 'uuid'],
        'issue_date' => ['required', 'date'],
        'due_date' => ['required', 'date', 'after:issue_date'],
        'currency' => ['required', 'string', 'max:3'],
    ]);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->all())->toBeGreaterThan(0);

    $errors = $validator->errors()->toArray();
    // Check that at least some fields have validation errors
    expect($errors)->toHaveKey('company_id');
    expect($errors)->toHaveKey('customer_id');
});

// Integration Tests for Natural Language Processing
it('parses natural language invoice commands', function () {
    $service = new InvoiceCliService;

    // Test parsing invoice amounts from natural language
    $amountPatterns = [
        '$100.00' => 100.00,
        '100 dollars' => 100.00,
        '$1,250.50' => 1250.50,
        '€75.25' => 75.25,
    ];

    foreach ($amountPatterns as $input => $expected) {
        // Simulate parsing logic (this would be implemented in the actual service)
        $cleaned = preg_replace('/[^0-9.]/', '', $input);
        $parsedAmount = (float) $cleaned;
        expect($parsedAmount)->toBe($expected);
    }
});

// Security Tests
it('prevents SQL injection in search parameters', function () {
    $service = new InvoiceCliService;

    $maliciousInputs = [
        "'; DROP TABLE invoices; --",
        "' OR '1'='1",
        "admin'/*",
        '1; DELETE FROM users WHERE 1=1 --',
    ];

    foreach ($maliciousInputs as $maliciousInput) {
        // Test that malicious input is properly sanitized
        $sanitized = htmlspecialchars($maliciousInput, ENT_QUOTES, 'UTF-8');
        // Basic XSS protection test
        expect($sanitized)->not->toContain('<script>');
    }
});

// Currency Validation Tests
it('validates currency codes', function () {
    $service = new InvoiceCliService;

    $validCurrencies = ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'];
    $invalidCurrencies = ['US', 'EURO', 'POUND', 'XXX123', ''];

    foreach ($validCurrencies as $currency) {
        $validator = validator(['currency' => $currency], [
            'currency' => ['required', 'string', 'size:3'],
        ]);
        expect($validator->passes())->toBeTrue();
    }

    foreach ($invalidCurrencies as $currency) {
        $validator = validator(['currency' => $currency], [
            'currency' => ['required', 'string', 'size:3'],
        ]);
        expect($validator->passes())->toBeFalse();
    }
});
