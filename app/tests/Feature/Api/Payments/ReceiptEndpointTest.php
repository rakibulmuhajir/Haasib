<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    // Set up test environment
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create currency
    $this->currency = Currency::where('code', 'USD')->first();
    if (! $this->currency) {
        $this->currency = Currency::create([
            'id' => (string) Str::uuid(),
            'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2,
        ]);
    }

    // Create company
    $this->company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'Receipt Test Co',
        'slug' => 'receipt-test-'.Str::random(4),
        'base_currency' => 'USD',
        'currency_id' => $this->currency->id,
        'language' => 'en',
        'locale' => 'en_US',
        'address' => '123 Test St',
        'city' => 'Test City',
        'country' => 'US',
        'postal_code' => '12345',
    ]);

    // Attach user to company and set RLS
    DB::table('auth.company_user')->insert([
        'company_id' => $this->company->id,
        'user_id' => $this->user->id,
        'role' => 'owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::statement("set local app.current_company = '".$this->company->id."'");

    // Create customer
    $this->customer = Customer::create([
        'customer_id' => (string) Str::uuid(),
        'company_id' => $this->company->id,
        'name' => 'Test Customer',
        'email' => 'billing@test.test',
        'currency_id' => $this->currency->id,
        'is_active' => true,
        'address' => '456 Customer Ave',
        'city' => 'Customer City',
        'country' => 'US',
        'postal_code' => '67890',
    ]);

    // Set up fake storage for PDFs
    Storage::fake('local');
});

it('returns receipt in JSON format for payment with allocations', function () {
    // Arrange
    $payment = createTestPayment();
    $allocation = createTestAllocation($payment->id);

    // Act
    $response = $this->getJson("/api/accounting/payments/{$payment->id}/receipt?format=json");

    // Assert
    $response->assertStatus(200)
        ->assertHeader('content-type', 'application/json')
        ->assertJsonStructure([
            'receipt_number',
            'company_details' => [
                'name',
                'address',
                'city',
                'country',
                'postal_code',
            ],
            'customer_details' => [
                'name',
                'email',
                'address',
            ],
            'payment_details' => [
                'payment_number',
                'payment_date',
                'payment_method_label',
                'reference_number',
            ],
            'amount_summary' => [
                'payment_amount',
                'currency_code',
                'total_allocated',
                'remaining_amount',
            ],
            'allocations' => [
                '*' => [
                    'allocation_id',
                    'invoice_number',
                    'allocation_date',
                    'allocated_amount',
                    'discount_amount',
                    'discount_percent',
                    'notes',
                ],
            ],
            'generated_at',
        ])
        ->assertJsonPath('receipt_number', 'R-' . $payment->payment_number)
        ->assertJsonPath('company_details.name', 'Receipt Test Co')
        ->assertJsonPath('customer_details.name', 'Test Customer')
        ->assertJsonPath('payment_details.payment_number', $payment->payment_number)
        ->assertJsonPath('amount_summary.payment_amount', $payment->amount)
        ->assertJsonPath('amount_summary.total_allocated', 800.00)
        ->assertJsonPath('amount_summary.remaining_amount', 200.00)
        ->assertJsonPath('allocations.0.invoice_number', 'INV-2025-001')
        ->assertJsonPath('allocations.0.allocated_amount', 800.00);
});

it('returns receipt in PDF format with proper headers', function () {
    // Arrange
    $payment = createTestPayment();
    createTestAllocation($payment->id);

    // Act
    $response = $this->getJson("/api/accounting/payments/{$payment->id}/receipt?format=pdf");

    // Assert
    $response->assertStatus(200)
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="receipt-R-' . $payment->payment_number . '.pdf"');

    // Verify PDF content contains key information
    $pdfContent = $response->getContent();
    expect($pdfContent)->toContain('PAYMENT RECEIPT');
    expect($pdfContent)->toContain($payment->payment_number);
    expect($pdfContent)->toContain('Receipt Test Co');
    expect($pdfContent)->toContain('Test Customer');
    expect($pdfContent)->toContain('$1,000.00');
});

it('includes early payment discount information in receipt', function () {
    // Arrange
    $payment = createTestPayment();
    $allocation = createTestAllocation($payment->id, [
        'discount_amount' => 20.00,
        'discount_percent' => 2.0,
        'allocated_amount' => 780.00, // 800 - 20 discount
    ]);

    // Act
    $response = $this->getJson("/api/accounting/payments/{$payment->id}/receipt?format=json");

    // Assert
    $response->assertStatus(200)
        ->assertJsonPath('allocations.0.discount_amount', 20.00)
        ->assertJsonPath('allocations.0.discount_percent', 2.0)
        ->assertJsonPath('allocations.0.allocated_amount', 780.00)
        ->assertJsonPath('amount_summary.total_allocated', 780.00)
        ->assertJsonPath('amount_summary.remaining_amount', 220.00); // 1000 - 780
});

it('shows unallocated cash amount in receipt', function () {
    // Arrange
    $payment = createTestPayment(['amount' => 1500.00]);
    createTestAllocation($payment->id, ['allocated_amount' => 1000.00]);

    // Act
    $response = $this->getJson("/api/accounting/payments/{$payment->id}/receipt?format=json");

    // Assert
    $response->assertStatus(200)
        ->assertJsonPath('amount_summary.payment_amount', 1500.00)
        ->assertJsonPath('amount_summary.total_allocated', 1000.00)
        ->assertJsonPath('amount_summary.remaining_amount', 500.00)
        ->assertJsonPath('unallocated_cash_available', true);
});

it('validates format parameter for receipt endpoint', function () {
    // Arrange
    $payment = createTestPayment();

    // Act
    $response = $this->getJson("/api/accounting/payments/{$payment->id}/receipt?format=invalid");

    // Assert
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['format']);
});

it('returns 404 for non-existent payment receipt', function () {
    // Act
    $response = $this->getJson("/api/accounting/payments/non-existent-id/receipt?format=json");

    // Assert
    $response->assertStatus(404);
});

it('enforces company isolation for receipt access', function () {
    // Arrange
    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->create();
    
    $payment = createTestPayment();

    // Act - Try to access receipt from different company
    $response = $this->actingAs($otherUser)
        ->withHeaders(['X-Company-Id' => $otherCompany->id])
        ->getJson("/api/accounting/payments/{$payment->id}/receipt?format=json");

    // Assert
    $response->assertStatus(404);
});

it('requires proper authentication for receipt access', function () {
    // Arrange
    $payment = createTestPayment();

    // Act - Try to access without authentication
    $response = $this->getJson("/api/accounting/payments/{$payment->id}/receipt?format=json");

    // Assert
    $response->assertStatus(401);
});

it('generates receipt with multiple allocations', function () {
    // Arrange
    $payment = createTestPayment(['amount' => 2000.00]);
    
    // Create multiple allocations
    createTestAllocation($payment->id, [
        'invoice_id' => 'invoice-1-uuid',
        'invoice_number' => 'INV-2025-001',
        'allocated_amount' => 800.00,
    ]);
    
    createTestAllocation($payment->id, [
        'invoice_id' => 'invoice-2-uuid',
        'invoice_number' => 'INV-2025-002',
        'allocated_amount' => 700.00,
    ]);

    // Act
    $response = $this->getJson("/api/accounting/payments/{$payment->id}/receipt?format=json");

    // Assert
    $response->assertStatus(200)
        ->assertJsonCount(2, 'allocations')
        ->assertJsonPath('amount_summary.total_allocated', 1500.00)
        ->assertJsonPath('amount_summary.remaining_amount', 500.00);
});

it('includes proper timestamps in receipt response', function () {
    // Arrange
    $payment = createTestPayment();
    $allocation = createTestAllocation($payment->id);

    // Act
    $response = $this->getJson("/api/accounting/payments/{$payment->id}/receipt?format=json");

    // Assert
    $response->assertStatus(200)
        ->assertJsonStructure([
            'generated_at',
            'payment_details' => [
                'payment_date',
            ],
            'allocations' => [
                '*' => [
                    'allocation_date',
                ],
            ],
        ]);

    // Verify timestamp format
    $responseData = $response->json();
    expect($responseData['generated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

it('handles payment with no allocations in receipt', function () {
    // Arrange
    $payment = createTestPayment();

    // Act
    $response = $this->getJson("/api/accounting/payments/{$payment->id}/receipt?format=json");

    // Assert
    $response->assertStatus(200)
        ->assertJsonPath('amount_summary.total_allocated', 0.00)
        ->assertJsonPath('amount_summary.remaining_amount', $payment->amount)
        ->assertJsonCount(0, 'allocations')
        ->assertJsonPath('unallocated_cash_available', true);
});

// Helper functions
function createTestPayment(array $overrides = []): \App\Models\Payment
{
    return \App\Models\Payment::create(array_merge([
        'id' => (string) Str::uuid(),
        'company_id' => test()->company->id,
        'customer_id' => test()->customer->customer_id,
        'payment_number' => 'PAY-2025-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT),
        'payment_date' => now()->format('Y-m-d'),
        'payment_method' => 'bank_transfer',
        'amount' => 1000.00,
        'currency' => 'USD',
        'status' => 'completed',
        'created_by_user_id' => test()->user->id,
    ], $overrides));
}

function createTestAllocation(string $paymentId, array $overrides = []): \App\Models\PaymentAllocation
{
    return \App\Models\PaymentAllocation::create(array_merge([
        'id' => (string) Str::uuid(),
        'company_id' => test()->company->id,
        'payment_id' => $paymentId,
        'invoice_id' => 'test-invoice-uuid',
        'invoice_number' => 'INV-2025-001',
        'allocated_amount' => 800.00,
        'allocation_date' => now(),
        'allocation_method' => 'manual',
        'notes' => 'Test allocation',
        'created_by_user_id' => test()->user->id,
    ], $overrides));
}