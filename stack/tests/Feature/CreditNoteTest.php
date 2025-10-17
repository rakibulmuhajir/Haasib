<?php

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('CreditNote Feature Tests', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user->id, ['role' => 'owner']);

        // Create a test customer
        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Customer',
        ]);

        // Create a test invoice
        $this->invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => 'posted',
            'total_amount' => 1000,
            'balance_due' => 1000,
        ]);

        // Create invoice items
        InvoiceItem::factory()->create([
            'invoice_id' => $this->invoice->id,
            'description' => 'Test Service',
            'quantity' => 1,
            'unit_price' => 1000,
            'total' => 1000,
        ]);

        Storage::fake('local');
    });

    describe('CreditNote Creation', function () {
        it('creates a credit note against a posted invoice', function () {
            $creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'amount' => 500,
                'total_amount' => 500,
                'status' => 'draft',
            ]);

            expect($creditNote)->toBeInstanceOf(CreditNote::class);
            expect($creditNote->company_id)->toBe($this->company->id);
            expect($creditNote->invoice_id)->toBe($this->invoice->id);
            expect($creditNote->amount)->toBe(500);
            expect($creditNote->status)->toBe('draft');
        });

        it('generates unique credit note numbers', function () {
            $creditNote1 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);

            $creditNote2 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);

            expect($creditNote1->credit_note_number)->not->toBe($creditNote2->credit_note_number);
        });

        it('validates credit note amount does not exceed invoice balance', function () {
            expect(fn () => CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'amount' => 1500, // Exceeds invoice balance
                'total_amount' => 1500,
            ]))->toThrow('InvalidArgumentException');
        });

        it('only allows credit notes against posted invoices', function () {
            $draftInvoice = Invoice::factory()->create([
                'company_id' => $this->company->id,
                'customer_id' => $this->customer->id,
                'status' => 'draft',
            ]);

            expect(fn () => CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $draftInvoice->id,
            ]))->toThrow('InvalidArgumentException');
        });
    });

    describe('CreditNote Status Management', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
            ]);
        });

        it('can post a draft credit note', function () {
            $result = $this->creditNote->post();

            expect($result)->toBeTrue();
            expect($this->creditNote->status)->toBe('posted');
            expect($this->creditNote->posted_at)->not->toBeNull();
        });

        it('cannot post a cancelled credit note', function () {
            $this->creditNote->status = 'cancelled';
            $this->creditNote->cancelled_at = now();
            $this->creditNote->save();

            $result = $this->creditNote->post();

            expect($result)->toBeFalse();
            expect($this->creditNote->status)->toBe('cancelled');
        });

        it('can cancel a draft credit note', function () {
            $result = $this->creditNote->cancel('Customer requested cancellation');

            expect($result)->toBeTrue();
            expect($this->creditNote->status)->toBe('cancelled');
            expect($this->creditNote->cancelled_at)->not->toBeNull();
            expect($this->creditNote->cancellation_reason)->toBe('Customer requested cancellation');
        });

        it('can cancel a posted credit note', function () {
            $this->creditNote->status = 'posted';
            $this->creditNote->posted_at = now();
            $this->creditNote->save();

            $result = $this->creditNote->cancel('Error in calculation');

            expect($result)->toBeTrue();
            expect($this->creditNote->status)->toBe('cancelled');
        });

        it('calculates remaining balance correctly', function () {
            // Create credit note items
            CreditNoteItem::factory()->create([
                'credit_note_id' => $this->creditNote->id,
                'description' => 'Credit Item 1',
                'quantity' => 1,
                'unit_price' => 200,
                'total' => 200,
            ]);

            CreditNoteItem::factory()->create([
                'credit_note_id' => $this->creditNote->id,
                'description' => 'Credit Item 2',
                'quantity' => 1,
                'unit_price' => 300,
                'total' => 300,
            ]);

            $this->creditNote->refresh();
            expect($this->creditNote->remainingBalance())->toBe(500.0);
        });
    });

    describe('CreditNote Application to Invoice', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'amount' => 500,
                'total_amount' => 500,
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            // Create credit note items
            CreditNoteItem::factory()->create([
                'credit_note_id' => $this->creditNote->id,
                'description' => 'Credit Item',
                'quantity' => 1,
                'unit_price' => 500,
                'total' => 500,
            ]);

            $this->creditNote->refresh();
        });

        it('applies credit note to invoice balance', function () {
            $initialBalance = $this->invoice->balance_due;

            $result = $this->creditNote->applyToInvoice($this->user);

            expect($result)->toBeTrue();

            $this->invoice->refresh();
            expect($this->invoice->balance_due)->toBe($initialBalance - 500);
            expect($this->creditNote->remainingBalance())->toBe(0.0);
        });

        it('creates application record when applying to invoice', function () {
            $this->creditNote->applyToInvoice($this->user);

            $application = CreditNoteApplication::where('credit_note_id', $this->creditNote->id)->first();
            expect($application)->not->BeNull();
            expect($application->amount_applied)->toBe(500);
            expect($application->user_id)->toBe($this->user->id);
        });

        it('updates invoice payment status when fully paid', function () {
            // Create additional payment to reduce balance to exact amount
            $this->invoice->balance_due = 500;
            $this->invoice->save();

            $this->creditNote->applyToInvoice($this->user);

            $this->invoice->refresh();
            expect($this->invoice->payment_status)->toBe('paid');
            expect($this->invoice->paid_at)->not->toBeNull();
        });

        it('cannot apply more than available balance', function () {
            // Try to apply when remaining balance is 0
            $this->creditNote->applyToInvoice($this->user);

            $result = $this->creditNote->applyToInvoice($this->user);
            expect($result)->toBeFalse();
        });

        it('cannot apply draft credit notes', function () {
            $this->creditNote->status = 'draft';
            $this->creditNote->save();

            $result = $this->creditNote->applyToInvoice($this->user);
            expect($result)->toBeFalse();
        });
    });

    describe('CreditNote Items Management', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('can add items to credit note', function () {
            $item = CreditNoteItem::factory()->create([
                'credit_note_id' => $this->creditNote->id,
                'description' => 'Credit Item',
                'quantity' => 2,
                'unit_price' => 100,
                'total' => 200,
            ]);

            expect($item)->toBeInstanceOf(CreditNoteItem::class);
            expect($item->credit_note_id)->toBe($this->creditNote->id);
            expect($item->total)->toBe(200);
        });

        it('calculates tax amount correctly', function () {
            $item = CreditNoteItem::factory()->create([
                'credit_note_id' => $this->creditNote->id,
                'description' => 'Taxable Item',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 10,
                'total' => 100,
            ]);

            expect($item->tax_amount)->toBe(10);
        });

        it('calculates final total including tax', function () {
            $item = CreditNoteItem::factory()->create([
                'credit_note_id' => $this->creditNote->id,
                'description' => 'Taxable Item',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 10,
                'total' => 100,
            ]);

            expect($item->final_total)->toBe(110);
        });
    });

    describe('CreditNote Relationships', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('belongs to company', function () {
            expect($this->creditNote->company)->toBeInstanceOf(Company::class);
            expect($this->creditNote->company->id)->toBe($this->company->id);
        });

        it('belongs to invoice', function () {
            expect($this->creditNote->invoice)->toBeInstanceOf(Invoice::class);
            expect($this->creditNote->invoice->id)->toBe($this->invoice->id);
        });

        it('has many items', function () {
            CreditNoteItem::factory()->create([
                'credit_note_id' => $this->creditNote->id,
            ]);

            expect($this->creditNote->items)->toHaveCount(1);
            expect($this->creditNote->items->first())->toBeInstanceOf(CreditNoteItem::class);
        });

        it('has many applications', function () {
            // Create application record
            DB::table('invoicing.credit_note_applications')->insert([
                'id' => \Str::uuid(),
                'credit_note_id' => $this->creditNote->id,
                'invoice_id' => $this->invoice->id,
                'amount_applied' => 100,
                'applied_at' => now(),
                'user_id' => $this->user->id,
                'invoice_balance_before' => 1000,
                'invoice_balance_after' => 900,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            expect($this->creditNote->applications)->toHaveCount(1);
        });
    });

    describe('CreditNote Scopes', function () {
        beforeEach(function () {
            $this->creditNote1 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
            ]);

            $this->creditNote2 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            $this->creditNote3 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        });

        it('can filter by company', function () {
            $otherCompany = Company::factory()->create();
            $otherCreditNote = CreditNote::factory()->create([
                'company_id' => $otherCompany->id,
            ]);

            $companyCreditNotes = CreditNote::forCompany($this->company->id)->get();
            expect($companyCreditNotes)->toHaveCount(3);
            expect($companyCreditNotes->pluck('id'))->not->toContain($otherCreditNote->id);
        });

        it('can filter by status', function () {
            $draftNotes = CreditNote::withStatus('draft')->get();
            expect($draftNotes)->toHaveCount(1);
            expect($draftNotes->first()->id)->toBe($this->creditNote1->id);

            $postedNotes = CreditNote::withStatus('posted')->get();
            expect($postedNotes)->toHaveCount(1);
            expect($postedNotes->first()->id)->toBe($this->creditNote2->id);

            $cancelledNotes = CreditNote::withStatus('cancelled')->get();
            expect($cancelledNotes)->toHaveCount(1);
            expect($cancelledNotes->first()->id)->toBe($this->creditNote3->id);
        });

        it('can filter posted credit notes', function () {
            $postedNotes = CreditNote::posted()->get();
            expect($postedNotes)->toHaveCount(1);
            expect($postedNotes->first()->id)->toBe($this->creditNote2->id);
        });

        it('can filter draft credit notes', function () {
            $draftNotes = CreditNote::draft()->get();
            expect($draftNotes)->toHaveCount(1);
            expect($draftNotes->first()->id)->toBe($this->creditNote1->id);
        });

        it('can filter cancelled credit notes', function () {
            $cancelledNotes = CreditNote::cancelled()->get();
            expect($cancelledNotes)->toHaveCount(1);
            expect($cancelledNotes->first()->id)->toBe($this->creditNote3->id);
        });
    });

    describe('CreditNote Validation', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
            ]);
        });

        it('validates for posting', function () {
            $errors = $this->creditNote->validateForPosting();
            expect($errors)->toBeEmpty();
        });

        it('detects validation errors for cancelled credit notes', function () {
            $this->creditNote->status = 'cancelled';
            $this->creditNote->cancelled_at = now();
            $this->creditNote->save();

            $errors = $this->creditNote->validateForPosting();
            expect($errors)->toHaveKey('status');
        });

        it('detects validation errors for zero amount', function () {
            $this->creditNote->total_amount = 0;
            $this->creditNote->save();

            $errors = $this->creditNote->validateForPosting();
            expect($errors)->toHaveKey('amount');
        });
    });

    describe('CreditNote Summary and Serialization', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'amount' => 500,
                'tax_amount' => 50,
                'total_amount' => 550,
                'status' => 'posted',
                'posted_at' => now(),
            ]);
        });

        it('generates correct summary', function () {
            $summary = $this->creditNote->getSummary();

            expect($summary)->toHaveKey('id');
            expect($summary)->toHaveKey('credit_note_number');
            expect($summary)->toHaveKey('amount');
            expect($summary)->toHaveKey('tax_amount');
            expect($summary)->toHaveKey('total_amount');
            expect($summary)->toHaveKey('status');
            expect($summary)->toHaveKey('remaining_balance');
            expect($summary['amount'])->toBe(500);
            expect($summary['tax_amount'])->toBe(50);
            expect($summary['total_amount'])->toBe(550);
            expect($summary['status'])->toBe('posted');
        });

        it('includes all necessary attributes in toArray', function () {
            $array = $this->creditNote->toArray();

            expect($array)->toHaveKey('id');
            expect($array)->toHaveKey('credit_note_number');
            expect($array)->toHaveKey('company_id');
            expect($array)->toHaveKey('invoice_id');
            expect($array)->toHaveKey('amount');
            expect($array)->toHaveKey('tax_amount');
            expect($array)->toHaveKey('total_amount');
            expect($array)->toHaveKey('status');
        });
    });

    describe('CreditNote Number Generation', function () {
        it('generates sequential credit note numbers', function () {
            $creditNote1 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);

            $creditNote2 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);

            $currentYear = now()->format('Y');
            expect($creditNote1->credit_note_number)->toMatch("/CN-{$currentYear}-\\d{4}/");
            expect($creditNote2->credit_note_number)->toMatch("/CN-{$currentYear}-\\d{4}/");

            // Extract sequence numbers
            $seq1 = (int) substr($creditNote1->credit_note_number, -4);
            $seq2 = (int) substr($creditNote2->credit_note_number, -4);
            expect($seq2)->toBe($seq1 + 1);
        });

        it('handles different companies separately', function () {
            $otherCompany = Company::factory()->create();
            $otherInvoice = Invoice::factory()->create([
                'company_id' => $otherCompany->id,
                'customer_id' => Customer::factory()->create(['company_id' => $otherCompany->id])->id,
                'status' => 'posted',
            ]);

            $creditNote1 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);

            $creditNote2 = CreditNote::factory()->create([
                'company_id' => $otherCompany->id,
                'invoice_id' => $otherInvoice->id,
            ]);

            // Both should start with sequence 0001
            expect($creditNote1->credit_note_number)->toMatch('/CN-\\d{4}-0001$/');
            expect($creditNote2->credit_note_number)->toMatch('/CN-\\d{4}-0001$/');
        });
    });
});
