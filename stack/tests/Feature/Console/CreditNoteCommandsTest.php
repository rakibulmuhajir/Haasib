<?php

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CreditNote CLI Commands Tests', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->company->users()->attach($this->user->id, ['role' => 'owner']);

        $this->customer = Customer::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
        ]);

        $this->invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => 'posted',
            'total_amount' => 1000,
            'balance_due' => 1000,
        ]);
    });

    describe('creditnote:create', function () {
        it('creates a credit note with basic parameters', function () {
            $this->artisan('creditnote:create', [
                'invoice' => $this->invoice->id,
                'amount' => 500,
                'reason' => 'Customer returned goods',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Credit note created successfully!');

            $creditNote = CreditNote::where('invoice_id', $this->invoice->id)->first();
            expect($creditNote)->not->toBeNull();
            expect($creditNote->amount)->toBe(500);
            expect($creditNote->reason)->toBe('Customer returned goods');
            expect($creditNote->status)->toBe('draft');
        });

        it('creates a credit note with items', function () {
            $this->artisan('creditnote:create', [
                'invoice' => $this->invoice->id,
                'amount' => 300,
                'reason' => 'Partial refund',
                '--items' => 'Service A:150,Service B:150',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);

            $creditNote = CreditNote::where('invoice_id', $this->invoice->id)->first();
            expect($creditNote->items)->toHaveCount(2);
        });

        it('validates required parameters', function () {
            $this->artisan('creditnote:create', [
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1);
        });

        it('validates invoice exists and is posted', function () {
            $this->artisan('creditnote:create', [
                'invoice' => 'invalid-invoice-id',
                'amount' => 500,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1)
                ->expectsOutput('Invoice not found or not posted');
        });

        it('validates credit amount does not exceed invoice balance', function () {
            $this->artisan('creditnote:create', [
                'invoice' => $this->invoice->id,
                'amount' => 1500, // Exceeds balance
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1)
                ->expectsOutput('Credit amount cannot exceed invoice balance due');
        });

        it('parses natural language input', function () {
            $this->artisan('creditnote:create', [
                '--input' => 'Create credit note for 500 because customer returned items',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);

            $creditNote = CreditNote::first();
            expect($creditNote)->not->BeNull();
            expect($creditNote->amount)->toBe(500);
        });

        it('works in interactive mode', function () {
            $this->artisan('creditnote:create', [
                '--interactive',
                '--company' => $this->company->id,
            ])
                ->expectsQuestion('Enter invoice ID or number:', $this->invoice->id)
                ->expectsQuestion('Enter credit amount:', 500)
                ->expectsQuestion('Enter credit reason:', 'Customer dissatisfaction')
                ->assertExitCode(0);
        });

        it('outputs JSON format when requested', function () {
            $this->artisan('creditnote:create', [
                'invoice' => $this->invoice->id,
                'amount' => 250,
                'reason' => 'Test credit',
                '--json',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsJson([
                    'success' => true,
                    'data' => [
                        'amount' => 250,
                        'reason' => 'Test credit',
                        'status' => 'draft',
                    ],
                ]);
        });
    });

    describe('creditnote:list', function () {
        beforeEach(function () {
            $this->creditNote1 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
                'amount' => 200,
            ]);

            $this->creditNote2 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
                'amount' => 300,
            ]);
        });

        it('lists credit notes in table format', function () {
            $this->artisan('creditnote:list', [
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsTable(['ID', 'Number', 'Customer', 'Amount', 'Status', 'Created'], [
                    [$this->creditNote1->id, $this->creditNote1->credit_note_number, $this->customer->name, '200.00', 'draft', $this->creditNote1->created_at->format('Y-m-d H:i')],
                    [$this->creditNote2->id, $this->creditNote2->credit_note_number, $this->customer->name, '300.00', 'posted', $this->creditNote2->created_at->format('Y-m-d H:i')],
                ]);
        });

        it('filters by status', function () {
            $this->artisan('creditnote:list', [
                '--status' => 'draft',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsTable(['ID', 'Number', 'Customer', 'Amount', 'Status', 'Created'], [
                    [$this->creditNote1->id, $this->creditNote1->credit_note_number, $this->customer->name, '200.00', 'draft', $this->creditNote1->created_at->format('Y-m-d H:i')],
                ]);
        });

        it('filters by customer', function () {
            $otherCustomer = Customer::factory()->create(['company_id' => $this->company->id]);
            $otherInvoice = Invoice::factory()->create([
                'company_id' => $this->company->id,
                'customer_id' => $otherCustomer->id,
                'status' => 'posted',
            ]);

            $otherCreditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $otherInvoice->id,
            ]);

            $this->artisan('creditnote:list', [
                '--customer' => $this->customer->id,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsTable(['ID', 'Number', 'Customer', 'Amount', 'Status', 'Created'], [
                    [$this->creditNote1->id, $this->creditNote1->credit_note_number, $this->customer->name, '200.00', 'draft', $this->creditNote1->created_at->format('Y-m-d H:i')],
                    [$this->creditNote2->id, $this->creditNote2->credit_note_number, $this->customer->name, '300.00', 'posted', $this->creditNote2->created_at->format('Y-m-d H:i')],
                ]);
        });

        it('limits results', function () {
            $this->artisan('creditnote:list', [
                '--limit' => 1,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsTable(['ID', 'Number', 'Customer', 'Amount', 'Status', 'Created'], [
                    [$this->creditNote2->id, $this->creditNote2->credit_note_number, $this->customer->name, '300.00', 'posted', $this->creditNote2->created_at->format('Y-m-d H:i')],
                ]);
        });

        it('exports to JSON', function () {
            $this->artisan('creditnote:list', [
                '--json',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsJson([
                    'success' => true,
                    'data' => [
                        ['amount' => 300, 'status' => 'posted'],
                        ['amount' => 200, 'status' => 'draft'],
                    ],
                    'total' => 2,
                ]);
        });

        it('handles no results gracefully', function () {
            // Create a different company with no credit notes
            $emptyCompany = Company::factory()->create();

            $this->artisan('creditnote:list', [
                '--company' => $emptyCompany->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('No credit notes found.');
        });
    });

    describe('creditnote:show', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'amount' => 500,
                'status' => 'posted',
            ]);
        });

        it('displays credit note details', function () {
            $this->artisan('creditnote:show', [
                'identifier' => $this->creditNote->id,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('Credit Note Details')
                ->expectsOutput('Credit Note Number: '.$this->creditNote->credit_note_number)
                ->expectsOutput('Status: Posted')
                ->expectsOutput('Amount: 500.00');
        });

        it('finds credit note by number', function () {
            $this->artisan('creditnote:show', [
                'identifier' => $this->creditNote->credit_note_number,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('Credit Note Number: '.$this->creditNote->credit_note_number);
        });

        it('shows items when requested', function () {
            // Create credit note items
            $this->artisan('creditnote:show', [
                'identifier' => $this->creditNote->id,
                '--items',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('Credit Note Items:');
        });

        it('shows application history when requested', function () {
            $this->artisan('creditnote:show', [
                'identifier' => $this->creditNote->id,
                '--applications',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('Application History:');
        });

        it('outputs JSON format when requested', function () {
            $this->artisan('creditnote:show', [
                'identifier' => $this->creditNote->id,
                '--format' => 'json',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsJson([
                    'id' => $this->creditNote->id,
                    'credit_note_number' => $this->creditNote->credit_note_number,
                    'amount' => 500,
                    'status' => 'posted',
                ]);
        });

        it('handles non-existent credit note', function () {
            $this->artisan('creditnote:show', [
                'identifier' => 'non-existent',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1)
                ->expectsOutput('Credit note not found');
        });
    });

    describe('creditnote:post', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
            ]);
        });

        it('posts a draft credit note', function () {
            $this->artisan('creditnote:post', [
                'identifier' => $this->creditNote->id,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Credit note posted successfully!');

            $this->creditNote->refresh();
            expect($this->creditNote->status)->toBe('posted');
            expect($this->creditNote->posted_at)->not->toBeNull();
        });

        it('posts without confirmation when force flag used', function () {
            $this->artisan('creditnote:post', [
                'identifier' => $this->creditNote->id,
                '--force',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Credit note posted successfully!');
        });

        it('confirms before posting in interactive mode', function () {
            $this->artisan('creditnote:post', [
                'identifier' => $this->creditNote->id,
                '--interactive',
                '--company' => $this->company->id,
            ])
                ->expectsConfirmation('Post '.$this->creditNote->credit_note_number.'?')
                ->expectsOutput('Operation cancelled.');
        });

        it('posts multiple credit notes in batch', function () {
            $creditNote2 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
            ]);

            $this->artisan('creditnote:post', [
                '--batch' => $this->creditNote->id.','.$creditNote2->id,
                '--force',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Credit note '.$this->creditNote->credit_note_number.' posted successfully!')
                ->expectsOutput('✅ Credit note '.$creditNote2->credit_note_number.' posted successfully!');
        });

        it('cannot post already posted credit note', function () {
            $this->creditNote->status = 'posted';
            $this->creditNote->posted_at = now();
            $this->creditNote->save();

            $this->artisan('creditnote:post', [
                'identifier' => $this->creditNote->id,
                '--force',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1)
                ->expectsOutput('Cannot post credit note');
        });

        it('sends email notification when requested', function () {
            $this->artisan('creditnote:post', [
                'identifier' => $this->creditNote->id,
                '--email',
                '--force',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Credit note posted successfully!')
                ->expectsOutput('  Sending email notification...')
                ->expectsOutput('  ✓ Email sent to: '.$this->customer->email);
        });
    });

    describe('creditnote:cancel', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
            ]);
        });

        it('cancels a credit note with reason', function () {
            $this->artisan('creditnote:cancel', [
                'identifier' => $this->creditNote->id,
                '--reason' => 'Customer request',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Credit note cancelled successfully!');

            $this->creditNote->refresh();
            expect($this->creditNote->status)->toBe('cancelled');
            expect($this->creditNote->cancellation_reason)->toBe('Customer request');
        });

        it('requires cancellation reason', function () {
            $this->artisan('creditnote:cancel', [
                'identifier' => $this->creditNote->id,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1)
                ->expectsOutput('Cancellation reason is required');
        });

        it('cancels multiple credit notes in batch', function () {
            $creditNote2 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
            ]);

            $this->artisan('creditnote:cancel', [
                '--batch' => $this->creditNote->id.','.$creditNote2->id,
                '--reason' => 'Bulk cancellation',
                '--force',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Credit note '.$this->creditNote->credit_note_number.' cancelled successfully!')
                ->expectsOutput('✅ Credit note '.$creditNote2->credit_note_number.' cancelled successfully!');
        });

        it('cannot cancel already cancelled credit note', function () {
            $this->creditNote->status = 'cancelled';
            $this->creditNote->cancelled_at = now();
            $this->creditNote->save();

            $this->artisan('creditnote:cancel', [
                'identifier' => $this->creditNote->id,
                '--reason' => 'Duplicate cancellation',
                '--force',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1)
                ->expectsOutput('Cannot cancel credit note');
        });
    });

    describe('creditnote:email', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
            ]);
        });

        it('sends credit note email', function () {
            $this->artisan('creditnote:email', [
                'creditnote' => $this->creditNote->id,
                '--to' => 'customer@example.com',
                '--subject' => 'Your Credit Note',
                '--generate-pdf',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Credit note email sent successfully!');
        });

        it('schedules email for later delivery', function () {
            $futureDate = now()->addDays(3)->format('Y-m-d H:i:s');

            $this->artisan('creditnote:email', [
                'creditnote' => $this->creditNote->id,
                '--schedule' => $futureDate,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Credit note email scheduled successfully!')
                ->expectsOutput('Scheduled for: '.$futureDate);
        });

        it('works in interactive mode', function () {
            $this->artisan('creditnote:email', [
                'creditnote' => $this->creditNote->id,
                '--interactive',
                '--company' => $this->company->id,
            ])
                ->expectsQuestion('Recipient email address:', $this->customer->email)
                ->expectsQuestion('Email subject:', 'Credit Note')
                ->expectsOutput('Enter email message (press Ctrl+D or type "END" on a new line to finish):')
                ->assertExitCode(0);
        });

        it('parses natural language input', function () {
            $this->artisan('creditnote:email', [
                '--input' => 'email credit note '.$this->creditNote->credit_note_number.' to '.$this->customer->email,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Credit note email sent successfully!');
        });

        it('validates schedule time is in future', function () {
            $pastDate = now()->subDay()->format('Y-m-d H:i:s');

            $this->artisan('creditnote:email', [
                'creditnote' => $this->creditNote->id,
                '--schedule' => $pastDate,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1)
                ->expectsOutput('Schedule time must be in the future');
        });
    });

    describe('creditnote:email:batch', function () {
        beforeEach(function () {
            $this->creditNote1 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
            ]);

            $this->creditNote2 = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
            ]);
        });

        it('sends batch emails', function () {
            $this->artisan('creditnote:email:batch', [
                '--status' => 'posted',
                '--force',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('✅ Batch email sending completed!')
                ->expectsOutput('Successfully sent: 2');
        });

        it('previews batch operation', function () {
            $this->artisan('creditnote:email:batch', [
                '--status' => 'posted',
                '--preview',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsTable(['Credit Note', 'Customer', 'Amount', 'Status', 'Email'], [
                    [$this->creditNote1->credit_note_number, $this->customer->name, $this->creditNote1->amount, 'posted', $this->customer->email],
                    [$this->creditNote2->credit_note_number, $this->customer->name, $this->creditNote2->amount, 'posted', $this->customer->email],
                ]);
        });

        it('filters by date range', function () {
            $this->artisan('creditnote:email:batch', [
                '--from-date' => now()->subDay()->format('Y-m-d'),
                '--to-date' => now()->format('Y-m-d'),
                '--preview',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);
        });

        it('handles no results gracefully', function () {
            $this->artisan('creditnote:email:batch', [
                '--status' => 'cancelled',
                '--preview',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('No credit notes found matching the specified criteria.');
        });
    });

    describe('creditnote:email:process', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
            ]);
        });

        it('processes scheduled emails', function () {
            // This would normally process scheduled emails from the database
            $this->artisan('creditnote:email:process', [
                '--type' => 'scheduled',
                '--dry-run',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('📧 Processing Credit Note Emails')
                ->expectsOutput('Type: scheduled')
                ->expectsOutput('DRY RUN MODE - No emails will actually be sent');
        });

        it('sends reminder emails', function () {
            $this->artisan('creditnote:email:process', [
                '--type' => 'reminders',
                '--dry-run',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('📨 Sending Reminder Emails');
        });

        it('processes all email types', function () {
            $this->artisan('creditnote:email:process', [
                '--dry-run',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('🔄 Processing Scheduled Emails')
                ->expectsOutput('📨 Sending Reminder Emails');
        });

        it('limits processing when specified', function () {
            $this->artisan('creditnote:email:process', [
                '--limit' => 10,
                '--dry-run',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsOutput('Limit: 10');
        });
    });

    describe('Error Handling', function () {
        it('handles company access errors', function () {
            $otherCompany = Company::factory()->create();
            $creditNote = CreditNote::factory()->create(['company_id' => $otherCompany->id]);

            $this->artisan('creditnote:show', [
                'identifier' => $creditNote->id,
                '--company' => $this->company->id, // Different company
            ])
                ->assertExitCode(1)
                ->expectsOutput('Unauthorized access to this company');
        });

        it('handles validation errors gracefully', function () {
            $this->artisan('creditnote:create', [
                'invoice' => $this->invoice->id,
                'amount' => 'invalid-amount',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(1)
                ->expectsOutput('Failed to create credit note');
        });

        it('handles database errors gracefully', function () {
            // Force a database error by using invalid company ID
            $this->artisan('creditnote:create', [
                'invoice' => $this->invoice->id,
                'amount' => 500,
                '--company' => 'invalid-company-id',
            ])
                ->assertExitCode(1);
        });
    });

    describe('Natural Language Processing', function () {
        it('parses complex natural language input', function () {
            $this->artisan('creditnote:create', [
                '--input' => 'Create a credit note for invoice '.$this->invoice->invoice_number.' for $300 because customer returned defective product',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);

            $creditNote = CreditNote::first();
            expect($creditNote)->not->BeNull();
            expect($creditNote->amount)->toBe(300);
        });

        it('parses email natural language input', function () {
            $creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
            ]);

            $this->artisan('creditnote:email', [
                '--input' => 'send credit note '.$creditNote->credit_note_number.' to '.$this->customer->email.' with subject Your Credit Note',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);
        });
    });

    describe('JSON Output Format', function () {
        it('provides consistent JSON structure across commands', function () {
            $creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);

            $this->artisan('creditnote:show', [
                'identifier' => $creditNote->id,
                '--format' => 'json',
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0)
                ->expectsJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'credit_note_number',
                        'status',
                        'amount',
                    ],
                ]);
        });
    });

    describe('Performance and Limits', function () {
        it('handles large result sets efficiently', function () {
            // Create multiple credit notes
            CreditNote::factory()->count(10)->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);

            $this->artisan('creditnote:list', [
                '--limit' => 5,
                '--company' => $this->company->id,
            ])
                ->assertExitCode(0);
        });
    });
});
