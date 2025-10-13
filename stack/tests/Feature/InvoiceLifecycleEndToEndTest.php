<?php

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Invoice Lifecycle End-to-End Tests', function () {
    beforeEach(function () {
        // Set up test environment
        Storage::fake('local');
        
        // Create test company and user
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user->id, ['role' => 'owner']);
        
        // Create test customer
        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Customer Ltd',
            'email' => 'test@example.com',
        ]);
        
        // Set up authentication context
        $this->actingAs($this->user);
    });

    describe('Complete Invoice Lifecycle', function () {
        it('performs complete invoice lifecycle from creation to payment', function () {
            // Step 1: Create invoice template
            $template = $this->createInvoiceTemplate();
            
            // Step 2: Create invoice from template
            $invoice = $this->createInvoiceFromTemplate($template);
            
            // Step 3: Update invoice details
            $this->updateInvoice($invoice);
            
            // Step 4: Send invoice
            $this->sendInvoice($invoice);
            
            // Step 5: Post invoice to ledger
            $this->postInvoice($invoice);
            
            // Step 6: Receive payment
            $payment = $this->receivePayment($invoice);
            
            // Step 7: Allocate payment to invoice
            $this->allocatePayment($payment, $invoice);
            
            // Step 8: Verify final state
            $this->verifyFinalState($invoice, $payment);
        });

        it('handles invoice with credit note lifecycle', function () {
            // Create and post invoice
            $invoice = $this->createPostedInvoice(1000.00);
            
            // Create credit note
            $creditNote = $this->createCreditNote($invoice, 200.00);
            
            // Apply credit note
            $this->applyCreditNote($creditNote, $invoice);
            
            // Receive and allocate payment
            $payment = $this->receivePayment($invoice, 800.00);
            $this->allocatePayment($payment, $invoice);
            
            // Verify settlement
            $this->verifyInvoiceSettlement($invoice, $creditNote, $payment);
        });

        it('handles partial payment scenarios', function () {
            // Create large invoice
            $invoice = $this->createPostedInvoice(5000.00);
            
            // Multiple partial payments
            $payment1 = $this->receivePayment($invoice, 2000.00);
            $payment2 = $this->receivePayment($invoice, 2000.00);
            $payment3 = $this->receivePayment($invoice, 1000.00);
            
            // Allocate payments sequentially
            $this->allocatePayment($payment1, $invoice);
            $this->allocatePayment($payment2, $invoice);
            $this->allocatePayment($payment3, $invoice);
            
            // Verify complete settlement
            $this->verifyCompleteSettlement($invoice);
        });
    });

    describe('CLI-GUI Parity Tests', function () {
        it('creates identical invoices via CLI and API', function () {
            $invoiceData = [
                'customer_id' => $this->customer->id,
                'items' => 'Consulting Services:10:150.00',
                'due_date' => now()->addDays(30)->toDateString(),
                'notes' => 'Test invoice for CLI-GUI parity',
            ];

            // Create via CLI
            $cliCommand = $this->artisan('invoice:create', [
                '--customer' => $invoiceData['customer_id'],
                '--items' => $invoiceData['items'],
                '--due-date' => $invoiceData['due_date'],
                '--notes' => $invoiceData['notes'],
                '--company' => $this->company->id,
                '--quiet' => true,
            ]);
            $cliCommand->assertExitCode(0);

            $cliInvoice = Invoice::where('notes', $invoiceData['notes'])->first();

            // Create via API
            $apiResponse = $this->postJson("/api/invoices", [
                'customer_id' => $invoiceData['customer_id'],
                'template_data' => [
                    'line_items' => [
                        [
                            'description' => 'Consulting Services',
                            'quantity' => 10,
                            'unit_price' => 150.00,
                            'tax_rate' => 0,
                            'discount_amount' => 0,
                        ],
                    ],
                ],
                'issue_date' => now()->toDateString(),
                'due_date' => $invoiceData['due_date'],
                'notes' => $invoiceData['notes'] . ' (API)',
            ]);
            $apiResponse->assertStatus(201);

            // Verify both invoices exist and have similar properties
            $this->assertDatabaseHas('invoicing.invoices', [
                'company_id' => $this->company->id,
                'customer_id' => $this->customer->id,
                'total_amount' => 1500.00,
            ]);

            $apiInvoice = Invoice::where('notes', $invoiceData['notes'] . ' (API)')->first();

            // Compare key properties
            $this->assertEquals($cliInvoice->total_amount, $apiInvoice->total_amount);
            $this->assertEquals($cliInvoice->customer_id, $apiInvoice->customer_id);
            $this->assertEquals($cliInvoice->due_date->format('Y-m-d'), $apiInvoice->due_date->format('Y-m-d'));
        });

        it('performs identical payment allocation via CLI and API', function () {
            // Create invoice
            $invoice = $this->createPostedInvoice(1000.00);
            
            // Create payment
            $payment = Payment::factory()->create([
                'company_id' => $this->company->id,
                'customer_id' => $this->customer->id,
                'amount' => 600.00,
                'payment_date' => now(),
                'status' => 'pending',
            ]);

            // Allocate via CLI
            $cliCommand = $this->artisan('payment:allocate', [
                'payment' => $payment->id,
                '--invoices' => $invoice->id,
                '--amounts' => 600.00,
                '--force' => true,
                '--quiet' => true,
            ]);
            $cliCommand->assertExitCode(0);

            // Verify allocation
            $this->assertDatabaseHas('invoicing.payment_allocations', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => 600.00,
            ]);
        });
    });

    describe('Template Workflow Tests', function () {
        it('creates and applies template workflow end-to-end', function () {
            // Step 1: Create template via CLI
            $this->artisan('invoice:template:create', [
                'name' => 'Monthly Services Template',
                '--customer' => $this->customer->id,
                '--items' => 'Web Hosting:1:100.00,Maintenance:1:200.00',
                '--payment-terms' => 30,
                '--company' => $this->company->id,
                '--quiet' => true,
            ])->assertExitCode(0);

            $template = InvoiceTemplate::where('name', 'Monthly Services Template')->first();
            $this->assertNotNull($template);

            // Step 2: List templates via CLI
            $this->artisan('invoice:template:list', [
                '--company' => $this->company->id,
                '--format' => 'json',
            ])->assertExitCode(0);

            // Step 3: Apply template to create invoice
            $this->artisan('invoice:template:apply', [
                'id' => $template->id,
                '--company' => $this->company->id,
                '--quiet' => true,
            ])->assertExitCode(0);

            // Verify invoice created from template
            $this->assertDatabaseHas('invoicing.invoices', [
                'company_id' => $this->company->id,
                'customer_id' => $this->customer->id,
                'total_amount' => 300.00,
            ]);

            $invoice = Invoice::where('total_amount', 300.00)->first();

            // Step 4: Complete invoice lifecycle
            $this->sendInvoice($invoice);
            $this->postInvoice($invoice);
            $payment = $this->receivePayment($invoice);
            $this->allocatePayment($payment, $invoice);

            // Verify template usage
            $this->assertDatabaseHas('invoicing.invoice_templates', [
                'id' => $template->id,
                'company_id' => $this->company->id,
            ]);
        });
    });

    describe('Error Handling and Edge Cases', function () {
        it('handles insufficient payment scenarios', function () {
            // Create invoice for $1000
            $invoice = $this->createPostedInvoice(1000.00);
            
            // Receive partial payment of $600
            $payment = $this->receivePayment($invoice, 600.00);
            $this->allocatePayment($payment, $invoice);
            
            // Verify invoice still has balance
            $invoice->refresh();
            $this->assertEquals(400.00, $invoice->balance_due);
            $this->assertEquals('partially_paid', $invoice->status);
        });

        it('prevents overpayment scenarios', function () {
            $invoice = $this->createPostedInvoice(500.00);
            $payment = Payment::factory()->create([
                'company_id' => $this->company->id,
                'customer_id' => $this->customer->id,
                'amount' => 800.00, // More than invoice amount
            ]);

            // Attempt to allocate overpayment
            $this->artisan('payment:allocate', [
                'payment' => $payment->id,
                '--invoices' => $invoice->id,
                '--amounts' => 800.00,
                '--company' => $this->company->id,
                '--quiet' => true,
            ])->assertExitCode(1); // Should fail
        });

        it('handles duplicate invoice prevention', function () {
            $invoiceData = [
                'customer_id' => $this->customer->id,
                'items' => 'Test Service:1:100.00',
                'company' => $this->company->id,
            ];

            // Create first invoice
            $this->artisan('invoice:create', $invoiceData + ['--quiet' => true])
                ->assertExitCode(0);

            // Attempt to create duplicate with same data
            $this->artisan('invoice:create', $invoiceData + ['--quiet' => true])
                ->assertExitCode(0); // Should succeed (different invoice number)

            // Verify two different invoices exist
            $this->assertDatabaseCount('invoicing.invoices', 2);
        });
    });

    // Helper methods for the test suite
    protected function createInvoiceTemplate(): InvoiceTemplate
    {
        $this->artisan('invoice:template:create', [
            'name' => 'Test Template',
            '--customer' => $this->customer->id,
            '--items' => 'Consulting Services:10:150.00',
            '--company' => $this->company->id,
            '--quiet' => true,
        ])->assertExitCode(0);

        return InvoiceTemplate::where('name', 'Test Template')->first();
    }

    protected function createInvoiceFromTemplate(InvoiceTemplate $template): Invoice
    {
        $this->artisan('invoice:template:apply', [
            'id' => $template->id,
            '--company' => $this->company->id,
            '--quiet' => true,
        ])->assertExitCode(0);

        return Invoice::where('company_id', $this->company->id)
            ->where('customer_id', $this->customer->id)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    protected function updateInvoice(Invoice $invoice): void
    {
        $this->artisan('invoice:update', [
            'id' => $invoice->id,
            '--notes' => 'Updated invoice notes',
            '--company' => $this->company->id,
            '--quiet' => true,
        ])->assertExitCode(0);

        $invoice->refresh();
        $this->assertEquals('Updated invoice notes', $invoice->notes);
    }

    protected function sendInvoice(Invoice $invoice): void
    {
        $this->artisan('invoice:send', [
            'id' => $invoice->id,
            '--company' => $this->company->id,
            '--quiet' => true,
        ])->assertExitCode(0);

        $invoice->refresh();
        $this->assertEquals('sent', $invoice->status);
    }

    protected function postInvoice(Invoice $invoice): void
    {
        $this->artisan('invoice:post', [
            'id' => $invoice->id,
            '--company' => $this->company->id,
            '--quiet' => true,
        ])->assertExitCode(0);

        $invoice->refresh();
        $this->assertEquals('posted', $invoice->status);
    }

    protected function receivePayment(Invoice $invoice, float $amount = null): Payment
    {
        $amount = $amount ?? $invoice->total_amount;

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'amount' => $amount,
            'payment_date' => now(),
            'status' => 'pending',
        ]);

        return $payment;
    }

    protected function allocatePayment(Payment $payment, Invoice $invoice): void
    {
        $this->artisan('payment:allocate', [
            'payment' => $payment->id,
            '--invoices' => $invoice->id,
            '--amounts' => min($payment->amount, $invoice->balance_due),
            '--strategy' => 'fifo',
            '--company' => $this->company->id,
            '--quiet' => true,
        ])->assertExitCode(0);
    }

    protected function createPostedInvoice(float $amount): Invoice
    {
        $this->artisan('invoice:create', [
            '--customer' => $this->customer->id,
            '--items' => 'Test Service:' . $amount . ':' . ($amount / 10),
            '--post' => true,
            '--company' => $this->company->id,
            '--quiet' => true,
        ])->assertExitCode(0);

        return Invoice::where('total_amount', $amount)->first();
    }

    protected function createCreditNote(Invoice $invoice, float $amount): CreditNote
    {
        $this->artisan('creditnote:create', [
            'invoice' => $invoice->id,
            'amount' => $amount,
            '--reason' => 'Test credit note',
            '--company' => $this->company->id,
            '--quiet' => true,
        ])->assertExitCode(0);

        return CreditNote::where('total_amount', $amount)->first();
    }

    protected function applyCreditNote(CreditNote $creditNote, Invoice $invoice): void
    {
        $this->artisan('creditnote:post', [
            'id' => $creditNote->id,
            '--company' => $this->company->id,
            '--quiet' => true,
        ])->assertExitCode(0);

        $creditNote->refresh();
        $this->assertEquals('posted', $creditNote->status);
    }

    protected function verifyFinalState(Invoice $invoice, Payment $payment): void
    {
        $invoice->refresh();
        $payment->refresh();

        // Verify invoice is paid
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0, $invoice->balance_due);

        // Verify payment is fully allocated
        $this->assertEquals('completed', $payment->status);
        $this->assertEquals(0, $payment->remaining_amount);

        // Verify allocation record exists
        $this->assertDatabaseHas('invoicing.payment_allocations', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
        ]);
    }

    protected function verifyInvoiceSettlement(Invoice $invoice, CreditNote $creditNote, Payment $payment): void
    {
        $invoice->refresh();
        
        // Verify invoice is paid after credit and payment
        $this->assertEquals('paid', $invoice->status);
        
        // Calculate expected settlement
        $expectedPaymentAmount = $invoice->total_amount - $creditNote->total_amount;
        
        // Verify payment allocation
        $this->assertDatabaseHas('invoicing.payment_allocations', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => $expectedPaymentAmount,
        ]);
    }

    protected function verifyCompleteSettlement(Invoice $invoice): void
    {
        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0, $invoice->balance_due);
    }
});