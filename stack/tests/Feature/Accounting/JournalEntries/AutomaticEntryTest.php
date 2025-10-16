<?php

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntrySource;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\Event;
use Modules\Accounting\Domain\Ledgers\Actions\AutoJournalEntryAction;
use Modules\Accounting\Domain\Ledgers\Events\JournalEntryCreated;
use Modules\Accounting\Domain\Payments\Events\PaymentAllocated;
use Modules\Accounting\Domain\Payments\Events\PaymentRecorded;

beforeEach(function () {
    $this->company = createCompany();
    $this->user = createUser(['company_id' => $this->company->id]);

    // Create test accounts
    $this->receivablesAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '12000',
        'name' => 'Accounts Receivable',
        'normal_balance' => 'debit',
        'allow_manual_entries' => false,
    ]);

    $this->revenueAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '40000',
        'name' => 'Sales Revenue',
        'normal_balance' => 'credit',
        'allow_manual_entries' => false,
    ]);

    $this->cashAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '10000',
        'name' => 'Cash',
        'normal_balance' => 'debit',
        'allow_manual_entries' => false,
    ]);

    $this->taxAccount = Account::factory()->create([
        'company_id' => $this->company->id,
        'code' => '22000',
        'name' => 'Sales Tax Payable',
        'normal_balance' => 'credit',
        'allow_manual_entries' => false,
    ]);
});

describe('Automatic Journal Entry Generation', function () {

    it('generates journal entry automatically when invoice is posted', function () {
        // Arrange: Create and post an invoice
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'draft',
            'subtotal' => 1000.00,
            'tax_amount' => 80.00,
            'total' => 1080.00,
        ]);

        Event::fake();

        // Act: Post the invoice
        $invoice->update(['status' => 'posted', 'posted_at' => now()]);
        event(new \Modules\Accounting\Domain\Invoices\Events\InvoicePosted($invoice));

        // Assert: Journal entry was created
        expect(JournalEntry::count())->toBe(1);

        $journalEntry = JournalEntry::first();
        expect($journalEntry)->toBeTruthy();
        expect($journalEntry->company_id)->toBe($this->company->id);
        expect($journalEntry->type)->toBe('automation');
        expect($journalEntry->status)->toBe('posted');
        expect($journalEntry->description)->toContain('Invoice');
        expect($journalEntry->reference)->toContain($invoice->id);

        // Assert: Transactions are balanced
        expect($journalEntry->transactions)->toHaveCount(3); // Receivables, Revenue, Tax

        $totalDebits = $journalEntry->transactions->where('debit_credit', 'debit')->sum('amount');
        $totalCredits = $journalEntry->transactions->where('debit_credit', 'credit')->sum('amount');
        expect($totalDebits)->toBe(1080.00);
        expect($totalCredits)->toBe(1080.00);

        // Assert: Source document is linked
        $source = JournalEntrySource::where('journal_entry_id', $journalEntry->id)->first();
        expect($source)->toBeTruthy();
        expect($source->source_type)->toBe('invoice');
        expect($source->source_id)->toBe($invoice->id);
        expect($source->source_data['invoice_number'])->toBe($invoice->invoice_number);

        // Assert: Events were dispatched
        Event::assertDispatched(JournalEntryCreated::class);
    });

    it('generates journal entry automatically when payment is recorded', function () {
        // Arrange: Create a payment
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'amount' => 500.00,
            'method' => 'cash',
            'status' => 'pending',
        ]);

        Event::fake();

        // Act: Record the payment
        $payment->update(['status' => 'recorded', 'recorded_at' => now()]);
        event(new PaymentRecorded($payment));

        // Assert: Journal entry was created
        expect(JournalEntry::count())->toBe(1);

        $journalEntry = JournalEntry::first();
        expect($journalEntry)->toBeTruthy();
        expect($journalEntry->company_id)->toBe($this->company->id);
        expect($journalEntry->type)->toBe('automation');
        expect($journalEntry->status)->toBe('posted');
        expect($journalEntry->description)->toContain('Payment');
        expect($journalEntry->reference)->toContain($payment->id);

        // Assert: Transactions are balanced
        expect($journalEntry->transactions)->toHaveCount(2); // Cash, Receivables

        $totalDebits = $journalEntry->transactions->where('debit_credit', 'debit')->sum('amount');
        $totalCredits = $journalEntry->transactions->where('debit_credit', 'credit')->sum('amount');
        expect($totalDebits)->toBe(500.00);
        expect($totalCredits)->toBe(500.00);

        // Assert: Source document is linked
        $source = JournalEntrySource::where('journal_entry_id', $journalEntry->id)->first();
        expect($source)->toBeTruthy();
        expect($source->source_type)->toBe('payment');
        expect($source->source_id)->toBe($payment->id);
        expect($source->source_data['payment_method'])->toBe($payment->method);

        // Assert: Events were dispatched
        Event::assertDispatched(JournalEntryCreated::class);
    });

    it('generates journal entry when payment allocation occurs', function () {
        // Arrange: Create invoice and payment
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'total' => 1000.00,
            'amount_due' => 1000.00,
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'amount' => 300.00,
            'method' => 'cash',
        ]);

        $allocation = PaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 300.00,
        ]);

        Event::fake();

        // Act: Allocate the payment
        event(new PaymentAllocated($allocation));

        // Assert: Journal entry was created for allocation
        expect(JournalEntry::count())->toBe(1);

        $journalEntry = JournalEntry::first();
        expect($journalEntry)->toBeTruthy();
        expect($journalEntry->type)->toBe('automation');
        expect($journalEntry->description)->toContain('Allocation');
        expect($journalEntry->reference)->toContain($allocation->id);

        // Assert: Source document is linked with allocation details
        $source = JournalEntrySource::where('journal_entry_id', $journalEntry->id)->first();
        expect($source)->toBeTruthy();
        expect($source->source_type)->toBe('payment_allocation');
        expect($source->source_id)->toBe($allocation->id);
        expect($source->source_data['payment_id'])->toBe($payment->id);
        expect($source->source_data['invoice_id'])->toBe($invoice->id);
        expect($source->source_data['allocation_amount'])->toBe(300.00);
    });

    it('prevents duplicate journal entries with idempotency keys', function () {
        // Arrange: Create an invoice
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'id' => 'test-invoice-123',
            'total' => 1000.00,
        ]);

        $event = new \Modules\Accounting\Domain\Invoices\Events\InvoicePosted($invoice);

        // Act: Process the same event twice
        $action = app(AutoJournalEntryAction::class);

        $result1 = $action->execute([
            'source_type' => 'invoice',
            'source_id' => $invoice->id,
            'event_type' => 'invoice_posted',
            'idempotency_key' => 'invoice_posted_'.$invoice->id,
        ]);

        $result2 = $action->execute([
            'source_type' => 'invoice',
            'source_id' => $invoice->id,
            'event_type' => 'invoice_posted',
            'idempotency_key' => 'invoice_posted_'.$invoice->id,
        ]);

        // Assert: Only one journal entry was created
        expect(JournalEntry::count())->toBe(1);
        expect($result1['status'])->toBe('created');
        expect($result2['status'])->toBe('duplicate');
    });

    it('handles missing accounts gracefully', function () {
        // Arrange: Create invoice without required accounts setup
        $companyWithoutAccounts = createCompany();
        $invoice = Invoice::factory()->create([
            'company_id' => $companyWithoutAccounts->id,
            'total' => 1000.00,
        ]);

        Event::fake();

        // Act & Assert: Should handle gracefully without throwing exceptions
        expect(fn () => event(new \Modules\Accounting\Domain\Invoices\Events\InvoicePosted($invoice)))
            ->not->toThrow(Exception::class);

        // No journal entry should be created if accounts are missing
        expect(JournalEntry::count())->toBe(0);
    });

    it('tracks complete audit trail for automatic entries', function () {
        // Arrange: Create and post invoice
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'total' => 1000.00,
        ]);

        // Act: Post the invoice
        $invoice->update(['status' => 'posted', 'posted_at' => now()]);
        event(new \Modules\Accounting\Domain\Invoices\Events\InvoicePosted($invoice));

        // Assert: Complete audit trail exists
        $journalEntry = JournalEntry::first();

        expect($journalEntry->audit)->toHaveCount(1);
        $auditRecord = $journalEntry->audit->first();
        expect($auditRecord->event_type)->toBe('created');
        expect($auditRecord->payload['source_type'])->toBe('invoice');
        expect($auditRecord->payload['source_id'])->toBe($invoice->id);
        expect($auditRecord->payload['automatic'])->toBe(true);
    });

    it('generates correct debit/credit amounts for complex invoice', function () {
        // Arrange: Create invoice with multiple line items
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'subtotal' => 2000.00,
            'discount_amount' => 200.00,
            'tax_amount' => 144.00,
            'total' => 1944.00,
        ]);

        // Act: Post the invoice
        $invoice->update(['status' => 'posted', 'posted_at' => now()]);
        event(new \Modules\Accounting\Domain\Invoices\Events\InvoicePosted($invoice));

        // Assert: Correct amounts
        $journalEntry = JournalEntry::first();

        $receivablesTransaction = $journalEntry->transactions
            ->where('account_id', $this->receivablesAccount->id)
            ->first();
        expect($receivablesTransaction->debit_credit)->toBe('debit');
        expect($receivablesTransaction->amount)->toBe(1944.00);

        $revenueTransaction = $journalEntry->transactions
            ->where('account_id', $this->revenueAccount->id)
            ->first();
        expect($revenueTransaction->debit_credit)->toBe('credit');
        expect($revenueTransaction->amount)->toBe(1800.00); // 2000 - 200

        $taxTransaction = $journalEntry->transactions
            ->where('account_id', $this->taxAccount->id)
            ->first();
        expect($taxTransaction->debit_credit)->toBe('credit');
        expect($taxTransaction->amount)->toBe(144.00);
    });

    it('maintains data integrity during concurrent operations', function () {
        // Arrange: Create multiple invoices
        $invoices = Invoice::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'total' => 1000.00,
        ]);

        // Act: Post all invoices concurrently (simulate)
        foreach ($invoices as $invoice) {
            $invoice->update(['status' => 'posted', 'posted_at' => now()]);
            event(new \Modules\Accounting\Domain\Invoices\Events\InvoicePosted($invoice));
        }

        // Assert: All journal entries created correctly
        expect(JournalEntry::count())->toBe(3);

        foreach (JournalEntry::all() as $journalEntry) {
            expect($journalEntry->transactions)->toHaveCount(3);

            $totalDebits = $journalEntry->transactions->where('debit_credit', 'debit')->sum('amount');
            $totalCredits = $journalEntry->transactions->where('debit_credit', 'credit')->sum('amount');
            expect($totalDebits)->toBe($totalCredits);
        }

        // All source documents linked correctly
        expect(JournalEntrySource::count())->toBe(3);
    });
});
