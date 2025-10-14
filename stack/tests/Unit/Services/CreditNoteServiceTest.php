<?php

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ContextService;
use App\Services\CreditNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;

uses(RefreshDatabase::class);

describe('CreditNoteService Unit Tests', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user->id, ['role' => 'owner']);

        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $this->invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => 'posted',
            'total_amount' => 1000,
            'balance_due' => 1000,
        ]);

        // Mock the auth and context services
        $this->mockAuthService = Mockery::mock(AuthService::class);
        $this->mockContextService = Mockery::mock(ContextService::class);

        $this->service = new CreditNoteService(
            $this->mockContextService,
            $this->mockAuthService,
            app(\App\Services\CreditNotePdfService::class),
            app(\App\Services\CreditNoteEmailService::class)
        );

        Storage::fake('local');
    });

    describe('createCreditNote', function () {
        it('creates a credit note successfully', function () {
            // Mock authorization checks
            $this->mockAuthService->shouldReceive('canAccessCompany')
                ->once()
                ->with($this->user, $this->company)
                ->andReturn(true);

            $this->mockAuthService->shouldReceive('hasPermission')
                ->once()
                ->with($this->user, 'credit_notes.create')
                ->andReturn(true);

            $data = [
                'reason' => 'Customer returned goods',
                'amount' => 500,
                'tax_amount' => 50,
                'total_amount' => 550,
                'currency' => 'USD',
                'notes' => 'Customer dissatisfaction with product quality',
            ];

            $creditNote = $this->service->createCreditNote($this->company, $data, $this->user);

            expect($creditNote)->toBeInstanceOf(CreditNote::class);
            expect($creditNote->company_id)->toBe($this->company->id);
            expect($creditNote->invoice_id)->toBe($this->invoice->id);
            expect($creditNote->reason)->toBe('Customer returned goods');
            expect($creditNote->amount)->toBe(500);
            expect($creditNote->total_amount)->toBe(550);
            expect($creditNote->status)->toBe('draft');
        });

        it('validates company access', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')
                ->once()
                ->with($this->user, $this->company)
                ->andReturn(false);

            expect(fn () => $this->service->createCreditNote($this->company, [], $this->user))
                ->toThrow('Unauthorized');
        });

        it('validates user permissions', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')
                ->once()
                ->with($this->user, $this->company)
                ->andReturn(true);

            $this->mockAuthService->shouldReceive('hasPermission')
                ->once()
                ->with($this->user, 'credit_notes.create')
                ->andReturn(false);

            expect(fn () => $this->service->createCreditNote($this->company, [], $this->user))
                ->toThrow('Unauthorized');
        });

        it('validates invoice exists and is posted', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $data = [
                'invoice_id' => 'non-existent-id',
                'amount' => 500,
                'total_amount' => 500,
            ];

            expect(fn () => $this->service->createCreditNote($this->company, $data, $this->user))
                ->toThrow('Invoice not found or not posted');
        });

        it('validates credit amount does not exceed invoice balance', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $data = [
                'invoice_id' => $this->invoice->id,
                'amount' => 1500, // Exceeds invoice balance
                'total_amount' => 1500,
            ];

            expect(fn () => $this->service->createCreditNote($this->company, $data, $this->user))
                ->toThrow('Credit amount cannot exceed invoice balance due');
        });

        it('creates credit note with items', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $data = [
                'reason' => 'Partial refund',
                'amount' => 300,
                'total_amount' => 330,
                'currency' => 'USD',
                'items' => [
                    [
                        'description' => 'Refund for item 1',
                        'quantity' => 1,
                        'unit_price' => 200,
                        'tax_rate' => 10,
                        'total' => 200,
                    ],
                    [
                        'description' => 'Refund for item 2',
                        'quantity' => 1,
                        'unit_price' => 100,
                        'tax_rate' => 10,
                        'total' => 100,
                    ],
                ],
            ];

            $creditNote = $this->service->createCreditNote($this->company, $data, $this->user);

            expect($creditNote->items)->toHaveCount(2);
            expect($creditNote->items->sum('total'))->toBe(300);
        });
    });

    describe('postCreditNote', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
            ]);
        });

        it('posts a draft credit note successfully', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $result = $this->service->postCreditNote($this->creditNote, $this->user);

            expect($result)->toBeTrue();
            $this->creditNote->refresh();
            expect($this->creditNote->status)->toBe('posted');
            expect($this->creditNote->posted_at)->not->toBeNull();
        });

        it('cannot post already posted credit note', function () {
            $this->creditNote->status = 'posted';
            $this->creditNote->posted_at = now();
            $this->creditNote->save();

            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $result = $this->service->postCreditNote($this->creditNote, $this->user);

            expect($result)->toBeFalse();
        });

        it('validates credit note before posting', function () {
            $this->creditNote->total_amount = 0; // Invalid amount
            $this->creditNote->save();

            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            expect(fn () => $this->service->postCreditNote($this->creditNote, $this->user))
                ->toThrow('Validation failed');
        });
    });

    describe('cancelCreditNote', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
            ]);
        });

        it('cancels a credit note successfully', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $result = $this->service->cancelCreditNote($this->creditNote, 'Customer request', $this->user);

            expect($result)->toBeTrue();
            $this->creditNote->refresh();
            expect($this->creditNote->status)->toBe('cancelled');
            expect($this->creditNote->cancelled_at)->not->toBeNull();
            expect($this->creditNote->cancellation_reason)->toBe('Customer request');
        });

        it('cannot cancel already cancelled credit note', function () {
            $this->creditNote->status = 'cancelled';
            $this->creditNote->cancelled_at = now();
            $this->creditNote->save();

            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $result = $this->service->cancelCreditNote($this->creditNote, 'Duplicate cancellation', $this->user);

            expect($result)->toBeFalse();
        });

        it('requires cancellation reason', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            expect(fn () => $this->service->cancelCreditNote($this->creditNote, '', $this->user))
                ->toThrow('Cancellation reason is required');
        });
    });

    describe('applyCreditNoteToInvoice', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'amount' => 500,
                'total_amount' => 500,
                'status' => 'posted',
            ]);

            CreditNoteItem::factory()->create([
                'credit_note_id' => $this->creditNote->id,
                'description' => 'Credit Item',
                'quantity' => 1,
                'unit_price' => 500,
                'total' => 500,
            ]);
        });

        it('applies credit note to invoice successfully', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $result = $this->service->applyCreditNoteToInvoice($this->creditNote, $this->user);

            expect($result)->toBeTrue();

            $this->invoice->refresh();
            expect($this->invoice->balance_due)->toBe(500);

            $this->creditNote->refresh();
            expect($this->creditNote->remaining_balance)->toBe(0);
        });

        it('cannot apply draft credit notes', function () {
            $this->creditNote->status = 'draft';
            $this->creditNote->save();

            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $result = $this->service->applyCreditNoteToInvoice($this->creditNote, $this->user);

            expect($result)->toBeFalse();
        });

        it('applies partial amount when credit exceeds balance', function () {
            // Reduce invoice balance
            $this->invoice->balance_due = 300;
            $this->invoice->save();

            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $result = $this->service->applyCreditNoteToInvoice($this->creditNote, $this->user);

            expect($result)->toBeTrue();

            $this->invoice->refresh();
            expect($this->invoice->balance_due)->toBe(0);

            $this->creditNote->refresh();
            expect($this->creditNote->remaining_balance)->toBe(200);
        });
    });

    describe('findCreditNoteByIdentifier', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'credit_note_number' => 'CN-2024-0001',
            ]);
        });

        it('finds credit note by ID', function () {
            $result = $this->service->findCreditNoteByIdentifier($this->creditNote->id, $this->company);

            expect($result)->toBeInstanceOf(CreditNote::class);
            expect($result->id)->toBe($this->creditNote->id);
        });

        it('finds credit note by number', function () {
            $result = $this->service->findCreditNoteByIdentifier('CN-2024-0001', $this->company);

            expect($result)->toBeInstanceOf(CreditNote::class);
            expect($result->credit_note_number)->toBe('CN-2024-0001');
        });

        it('finds credit note by partial number match', function () {
            $result = $this->service->findCreditNoteByIdentifier('0001', $this->company);

            expect($result)->toBeInstanceOf(CreditNote::class);
            expect($result->credit_note_number)->toBe('CN-2024-0001');
        });

        it('returns null for non-existent credit note', function () {
            $result = $this->service->findCreditNoteByIdentifier('CN-9999-9999', $this->company);

            expect($result)->toBeNull();
        });

        it('only finds credit notes from specified company', function () {
            $otherCompany = Company::factory()->create();
            $otherCreditNote = CreditNote::factory()->create([
                'company_id' => $otherCompany->id,
                'credit_note_number' => 'CN-2024-0002',
            ]);

            $result = $this->service->findCreditNoteByIdentifier('CN-2024-0002', $this->company);

            expect($result)->toBeNull();
        });
    });

    describe('getCreditNotesForCompany', function () {
        beforeEach(function () {
            $this->creditNote1 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'draft',
            ]);

            $this->creditNote2 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'posted',
            ]);

            $otherCompany = Company::factory()->create();
            $this->otherCreditNote = CreditNote::factory()->create([
                'company_id' => $otherCompany->id,
            ]);
        });

        it('returns paginated credit notes for company', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);

            $result = $this->service->getCreditNotesForCompany($this->company, $this->user);

            expect($result)->toHaveCount(2);
            expect($result->pluck('id'))->toContain($this->creditNote1->id, $this->creditNote2->id);
            expect($result->pluck('id'))->not->toContain($this->otherCreditNote->id);
        });

        it('filters by status', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);

            $result = $this->service->getCreditNotesForCompany($this->company, $this->user, ['status' => 'draft']);

            expect($result)->toHaveCount(1);
            expect($result->first()->id)->toBe($this->creditNote1->id);
        });

        it('validates company access', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')
                ->once()
                ->with($this->user, $this->company)
                ->andReturn(false);

            expect(fn () => $this->service->getCreditNotesForCompany($this->company, $this->user))
                ->toThrow('Unauthorized');
        });
    });

    describe('getCreditNoteStatistics', function () {
        beforeEach(function () {
            $this->creditNote1 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'posted',
                'total_amount' => 500,
            ]);

            $this->creditNote2 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'posted',
                'total_amount' => 300,
            ]);

            $this->creditNote3 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'cancelled',
                'total_amount' => 200,
            ]);
        });

        it('returns correct statistics', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);

            $stats = $this->service->getCreditNoteStatistics($this->company, $this->user);

            expect($stats['total_credit_notes'])->toBe(3);
            expect($stats['total_credit_amount'])->toBe(1000);
            expect($stats['posted_credit_notes'])->toBe(2);
            expect($stats['cancelled_credit_notes'])->toBe(1);
            expect($stats['total_posted_amount'])->toBe(800);
            expect($stats['total_cancelled_amount'])->toBe(200);
        });

        it('filters statistics by date range', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);

            $yesterday = now()->subDay()->format('Y-m-d');
            $stats = $this->service->getCreditNoteStatistics($this->company, $this->user, [
                'from_date' => $yesterday,
                'to_date' => now()->format('Y-m-d'),
            ]);

            expect($stats['total_credit_notes'])->toBe(3); // All created today
        });
    });

    describe('updateCreditNote', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'draft',
                'reason' => 'Original reason',
            ]);
        });

        it('updates draft credit note successfully', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $data = [
                'reason' => 'Updated reason',
                'notes' => 'Updated notes',
            ];

            $result = $this->service->updateCreditNote($this->creditNote, $data, $this->user);

            expect($result)->toBeTrue();
            $this->creditNote->refresh();
            expect($this->creditNote->reason)->toBe('Updated reason');
            expect($this->creditNote->notes)->toBe('Updated notes');
        });

        it('cannot update posted credit note amount', function () {
            $this->creditNote->status = 'posted';
            $this->creditNote->posted_at = now();
            $this->creditNote->save();

            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $data = [
                'amount' => 1000, // Try to change amount
            ];

            expect(fn () => $this->service->updateCreditNote($this->creditNote, $data, $this->user))
                ->toThrow('Cannot modify amount of posted credit note');
        });
    });

    describe('deleteCreditNote', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'draft',
            ]);
        });

        it('deletes draft credit note successfully', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $result = $this->service->deleteCreditNote($this->creditNote, $this->user);

            expect($result)->toBeTrue();
            expect(CreditNote::find($this->creditNote->id))->toBeNull();
        });

        it('cannot delete posted credit note', function () {
            $this->creditNote->status = 'posted';
            $this->creditNote->posted_at = now();
            $this->creditNote->save();

            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            expect(fn () => $this->service->deleteCreditNote($this->creditNote, $this->user))
                ->toThrow('Cannot delete posted credit note');
        });
    });

    describe('PDF Generation', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'posted',
            ]);
        });

        it('generates PDF successfully', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')->andReturn(true);

            $path = $this->service->generateCreditNotePdf($this->creditNote, $this->user);

            expect($path)->toBeString();
            expect($path)->toContain('credit-note-');
            expect($path)->toContain('.pdf');
            Storage::disk('local')->assertExists($path);
        });

        it('validates permissions for PDF generation', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')
                ->with($this->user, 'credit_notes.pdf')
                ->andReturn(false);

            expect(fn () => $this->service->generateCreditNotePdf($this->creditNote, $this->user))
                ->toThrow('Unauthorized');
        });
    });

    describe('Email Sending', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'posted',
            ]);
        });

        it('sends credit note email successfully', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')
                ->with($this->user, 'credit_notes.email')
                ->andReturn(true);

            $options = [
                'to' => 'customer@example.com',
                'subject' => 'Credit Note',
            ];

            $result = $this->service->sendCreditNoteEmail($this->creditNote, $this->user, $options);

            expect($result)->toBeArray();
            expect($result['success'])->toBeTrue();
        });

        it('validates email permissions', function () {
            $this->mockAuthService->shouldReceive('canAccessCompany')->andReturn(true);
            $this->mockAuthService->shouldReceive('hasPermission')
                ->with($this->user, 'credit_notes.email')
                ->andReturn(false);

            expect(fn () => $this->service->sendCreditNoteEmail($this->creditNote, $this->user))
                ->toThrow('Unauthorized');
        });
    });

    afterEach(function () {
        Mockery::close();
    });
});
