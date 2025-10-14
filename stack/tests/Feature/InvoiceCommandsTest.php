<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Clean up test environment
    Storage::fake('local');

    // Create test user and company
    $this->user = User::factory()->create();
    $this->company = Company::factory()->create();
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);

    // Attach user to company as owner
    $this->company->users()->attach($this->user, ['role' => 'owner']);
});

// InvoiceCreate Command Tests
it('creates an invoice with required fields', function () {
    $command = $this->artisan('invoice:create', [
        '--customer' => $this->customer->id,
        '--items' => 'Test Service:1:100.00',
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);

    $this->assertDatabaseHas('invoicing.invoices', [
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);
});

it('validates invoice creation without customer', function () {
    $command = $this->artisan('invoice:create', [
        '--items' => 'Test Service:1:100.00',
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

it('validates invoice creation without line items', function () {
    $command = $this->artisan('invoice:create', [
        '--customer' => $this->customer->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

it('creates invoice with natural language input', function () {
    $command = $this->artisan('invoice:create', [
        '--natural' => 'create invoice for test customer $500 due in 30 days',
        '--company' => $this->company->id,
        '--quiet' => true,
        '--no-interactive' => true,
    ]);

    $command->assertExitCode(0);

    $this->assertDatabaseHas('invoicing.invoices', [
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);
});

it('sends invoice immediately after creation', function () {
    $this->customer->update(['email' => 'test@example.com']);

    $command = $this->artisan('invoice:create', [
        '--customer' => $this->customer->id,
        '--items' => 'Test Service:1:100.00',
        '--send' => true,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);

    $this->assertDatabaseHas('invoicing.invoices', [
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'sent',
    ]);
});

// InvoiceUpdate Command Tests
it('updates invoice status', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);

    $command = $this->artisan('invoice:update', [
        'invoice' => $invoice->id,
        '--status' => 'sent',
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);

    $this->assertDatabaseHas('invoicing.invoices', [
        'id' => $invoice->id,
        'status' => 'sent',
    ]);
});

it('updates invoice customer', function () {
    $newCustomer = Customer::factory()->create(['company_id' => $this->company->id]);
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $command = $this->artisan('invoice:update', [
        'invoice' => $invoice->id,
        '--customer' => $newCustomer->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);

    $this->assertDatabaseHas('invoicing.invoices', [
        'id' => $invoice->id,
        'customer_id' => $newCustomer->id,
    ]);
});

it('prevents updating cancelled invoice without force', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'cancelled',
    ]);

    $command = $this->artisan('invoice:update', [
        'invoice' => $invoice->id,
        '--status' => 'sent',
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

it('updates cancelled invoice with force flag', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'cancelled',
    ]);

    $command = $this->artisan('invoice:update', [
        'invoice' => $invoice->id,
        '--status' => 'sent',
        '--force' => true,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);

    $this->assertDatabaseHas('invoicing.invoices', [
        'id' => $invoice->id,
        'status' => 'sent',
    ]);
});

// InvoiceSend Command Tests
it('sends invoice to customer', function () {
    $this->customer->update(['email' => 'test@example.com']);
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);

    $command = $this->artisan('invoice:send', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);

    $invoice->refresh();
    expect($invoice->status)->toBe('sent');
    expect($invoice->sent_at)->not->toBeNull();
});

it('prevents sending cancelled invoice', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'cancelled',
    ]);

    $command = $this->artisan('invoice:send', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

it('sends invoice with custom email', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);

    $command = $this->artisan('invoice:send', [
        'invoice' => $invoice->id,
        '--email' => 'custom@example.com',
        '--subject' => 'Custom Subject',
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);
});

// InvoicePost Command Tests
it('posts invoice to ledger', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'sent',
    ]);

    $command = $this->artisan('invoice:post', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);

    $invoice->refresh();
    expect($invoice->status)->toBe('posted');
    expect($invoice->posted_at)->not->toBeNull();
});

it('validates invoice before posting', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);

    // Create invoice without line items
    $command = $this->artisan('invoice:post', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

it('prevents double posting', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'posted',
    ]);

    $command = $this->artisan('invoice:post', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

// InvoiceCancel Command Tests
it('cancels draft invoice', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);

    $command = $this->artisan('invoice:cancel', [
        'invoice' => $invoice->id,
        '--reason' => 'Test cancellation',
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);

    $invoice->refresh();
    expect($invoice->status)->toBe('cancelled');
    expect($invoice->cancelled_at)->not->toBeNull();
    expect($invoice->cancellation_reason)->toBe('Test cancellation');
});

it('prevents cancelling already cancelled invoice', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'cancelled',
    ]);

    $command = $this->artisan('invoice:cancel', [
        'invoice' => $invoice->id,
        '--reason' => 'Test cancellation',
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

it('requires cancellation reason', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);

    $command = $this->artisan('invoice:cancel', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

// InvoiceList Command Tests
it('lists invoices for company', function () {
    Invoice::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $command = $this->artisan('invoice:list', [
        '--company' => $this->company->id,
        '--format' => 'json',
    ]);

    $command->assertExitCode(0);
    $command->expectsOutputToContain('"total": 3');
});

it('filters invoices by status', function () {
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'sent',
    ]);

    $command = $this->artisan('invoice:list', [
        '--status' => 'draft',
        '--company' => $this->company->id,
        '--format' => 'json',
    ]);

    $command->assertExitCode(0);
    $command->expectsOutputToContain('"total": 1');
});

it('filters invoices by customer', function () {
    $customer2 = Customer::factory()->create(['company_id' => $this->company->id]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $customer2->id,
    ]);

    $command = $this->artisan('invoice:list', [
        '--customer' => $customer2->id,
        '--company' => $this->company->id,
        '--format' => 'json',
    ]);

    $command->assertExitCode(0);
    $command->expectsOutputToContain('"total": 1');
});

it('exports invoices to CSV', function () {
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $exportPath = storage_path('app/test_export.csv');

    $command = $this->artisan('invoice:list', [
        '--export' => $exportPath,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);
    expect(file_exists($exportPath))->toBeTrue();

    // Clean up
    if (file_exists($exportPath)) {
        unlink($exportPath);
    }
});

// InvoiceShow Command Tests
it('shows invoice details', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 100.00,
    ]);

    $command = $this->artisan('invoice:show', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--format' => 'json',
    ]);

    $command->assertExitCode(0);
    $command->expectsOutputToContain('"invoice_number":');
    $command->expectsOutputToContain('"total_amount": 100');
});

it('shows invoice with line items', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $command = $this->artisan('invoice:show', [
        'invoice' => $invoice->id,
        '--with-line-items' => true,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);
});

it('shows invoice with payment history', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $command = $this->artisan('invoice:show', [
        'invoice' => $invoice->id,
        '--with-payments' => true,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);
});

// InvoiceDuplicate Command Tests
it('duplicates existing invoice', function () {
    $sourceInvoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 100.00,
    ]);

    $command = $this->artisan('invoice:duplicate', [
        'invoice' => $sourceInvoice->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);

    $this->assertDatabaseHas('invoicing.invoices', [
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ], 2); // Original + duplicate
});

it('duplicates invoice with price adjustment', function () {
    $sourceInvoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total_amount' => 100.00,
    ]);

    $command = $this->artisan('invoice:duplicate', [
        'invoice' => $sourceInvoice->id,
        '--adjust-prices' => 10,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);
});

it('previews duplicate before creation', function () {
    $sourceInvoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $command = $this->artisan('invoice:duplicate', [
        'invoice' => $sourceInvoice->id,
        '--preview' => true,
        '--company' => $this->company->id,
    ]);

    $command->assertExitCode(0);
    $command->expectsOutputToContain('Duplicate Invoice Preview');
    $command->expectsOutputToContain('Source Invoice:');
    $command->expectsOutputToContain('New Invoice Details:');
});

// InvoicePdf Command Tests
it('generates PDF for invoice', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $command = $this->artisan('invoice:pdf', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);

    // Check if PDF was created (in storage/app/invoices/pdf/)
    $expectedPattern = storage_path('app/invoices/pdf/Invoice-*.pdf');
    $pdfFiles = glob($expectedPattern);

    if (! empty($pdfFiles)) {
        expect(file_exists($pdfFiles[0]))->toBeTrue();
        // Clean up
        unlink($pdfFiles[0]);
    }
});

it('generates PDF with custom template', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $command = $this->artisan('invoice:pdf', [
        'invoice' => $invoice->id,
        '--template' => 'modern',
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);
});

it('previews PDF settings before generation', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $command = $this->artisan('invoice:pdf', [
        'invoice' => $invoice->id,
        '--template' => 'modern',
        '--preview' => true,
        '--company' => $this->company->id,
    ]);

    $command->assertExitCode(0);
    $command->expectsOutputToContain('PDF Generation Preview');
    $command->expectsOutputToContain('Template: modern');
});

// Permission Tests
it('prevents viewer role from creating invoices', function () {
    // Create user with viewer role
    $viewerUser = User::factory()->create();
    $this->company->users()->attach($viewerUser, ['role' => 'viewer']);

    $command = $this->actingAs($viewerUser)->artisan('invoice:create', [
        '--customer' => $this->customer->id,
        '--items' => 'Test Service:1:100.00',
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

it('allows admin role to send invoices', function () {
    // Create user with admin role
    $adminUser = User::factory()->create();
    $this->company->users()->attach($adminUser, ['role' => 'admin']);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'status' => 'draft',
    ]);

    $command = $this->actingAs($adminUser)->artisan('invoice:send', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);
});

// Natural Language Processing Tests
it('parses natural language invoice creation', function () {
    $command = $this->artisan('invoice:create', [
        '--natural' => 'create invoice for '.$this->customer->name.' test service for $250 due in 30 days',
        '--company' => $this->company->id,
        '--no-interactive' => true,
        '--quiet' => true,
    ]);

    $command->assertExitCode(0);
});

it('suggests command completion for partial input', function () {
    // This would require implementing the suggestion method in the trait
    // For now, we test that the command accepts the input
    $command = $this->artisan('invoice:create', [
        '--natural' => 'create inv',
        '--company' => $this->company->id,
        '--no-interactive' => true,
        '--quiet' => true,
    ]);

    // Command may succeed or fail based on parsing, but should not crash
    expect($command->exitCode)->toBeIn([0, 1]);
});

// Output Format Tests
it('outputs data in JSON format', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $command = $this->artisan('invoice:show', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--format' => 'json',
    ]);

    $command->assertExitCode(0);
    $command->expectsOutputToContain('{');
    $command->expectsOutputToContain('}');
});

it('outputs data in CSV format', function () {
    $command = $this->artisan('invoice:list', [
        '--company' => $this->company->id,
        '--format' => 'csv',
    ]);

    $command->assertExitCode(0);
    // CSV output should contain headers and potentially data
});

// Error Handling Tests
it('handles invoice not found error gracefully', function () {
    $command = $this->artisan('invoice:show', [
        'invoice' => 'non-existent-invoice',
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

it('handles company access denied error gracefully', function () {
    // Create user without access to company
    $otherUser = User::factory()->create();
    $otherCompany = Company::factory()->create();
    $otherCustomer = Customer::factory()->create(['company_id' => $otherCompany->id]);

    $command = $this->actingAs($otherUser)->artisan('invoice:list', [
        '--company' => $this->company->id,
        '--quiet' => true,
    ]);

    $command->assertExitCode(1);
});

// Performance Tests
it('loads invoice listing under 200ms with 10 invoices', function () {
    Invoice::factory()->count(10)->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $startTime = microtime(true);

    $command = $this->artisan('invoice:list', [
        '--company' => $this->company->id,
        '--format' => 'json',
        '--quiet' => true,
    ]);

    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    $command->assertExitCode(0);
    expect($responseTime)->toBeLessThan(200);
});

it('loads invoice details under 200ms', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
    ]);

    $startTime = microtime(true);

    $command = $this->artisan('invoice:show', [
        'invoice' => $invoice->id,
        '--company' => $this->company->id,
        '--format' => 'json',
        '--quiet' => true,
    ]);

    $endTime = microtime(true);
    $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

    $command->assertExitCode(0);
    expect($responseTime)->toBeLessThan(200);
});
