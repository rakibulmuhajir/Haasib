<?php

namespace App\Console\Commands;

class CreditNotePost extends CreditNoteBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'creditnote:post
                           {identifier : Credit note ID, number, or partial match}
                           {--force : Force posting without confirmation}
                           {--date= : Posting date (Y-m-d, defaults to today)}
                           {--auto-apply : Automatically apply to invoice after posting}
                           {--email : Send email notification after posting}
                           {--email-to= : Email recipient (if different from customer)}
                           {--email-template= : Email template to use}
                           {--company= : Company ID (overrides current company)}
                           {--batch= : Batch process multiple credit notes (comma-separated)}';

    /**
     * The console command description.
     */
    protected $description = 'Post a credit note to the ledger';

    /**
     * Handle the command logic.
     */
    protected function handleCommand(): int
    {
        $input = $this->parseInput();

        // Handle batch processing
        if (isset($input['batch'])) {
            return $this->handleBatchProcessing($input);
        }

        // Find the credit note
        $creditNote = $this->findCreditNote($input['identifier']);

        // Validate that credit note can be posted
        $this->validateForPosting($creditNote);

        // Show confirmation unless force flag is used
        if (! isset($input['force'])) {
            $this->displayPostingPreview($creditNote, $input);

            if (! $this->confirm('Do you want to post this credit note?')) {
                $this->info('Posting cancelled.');

                return self::SUCCESS;
            }
        }

        // Post the credit note
        $this->postCreditNote($creditNote, $input);

        // Auto-apply if requested
        if (isset($input['auto-apply'])) {
            $this->autoApplyToInvoice($creditNote);
        }

        return self::SUCCESS;
    }

    /**
     * Handle batch processing of multiple credit notes.
     */
    protected function handleBatchProcessing(array $input): int
    {
        $identifiers = explode(',', $input['batch']);
        $identifiers = array_map('trim', $identifiers);

        $this->info('Batch Processing Credit Notes');
        $this->line(str_repeat('=', 40));

        $successCount = 0;
        $failureCount = 0;
        $results = [];

        foreach ($identifiers as $identifier) {
            try {
                $creditNote = $this->findCreditNote($identifier);
                $this->validateForPosting($creditNote);

                if (! isset($input['force'])) {
                    $this->line("\nProcessing: {$creditNote->credit_note_number}");
                    if (! $this->confirm("Post {$creditNote->credit_note_number}?")) {
                        $this->line("Skipped: {$creditNote->credit_note_number}");

                        continue;
                    }
                }

                $this->postCreditNote($creditNote, $input);

                if (isset($input['auto-apply'])) {
                    $this->autoApplyToInvoice($creditNote);
                }

                $results[] = [
                    'identifier' => $identifier,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'status' => 'success',
                    'message' => 'Posted successfully',
                ];
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'identifier' => $identifier,
                    'credit_note_number' => null,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
                $failureCount++;
            }
        }

        // Display batch results
        $this->displayBatchResults($results, $successCount, $failureCount);

        return $failureCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Validate that credit note can be posted.
     */
    protected function validateForPosting($creditNote): void
    {
        if ($creditNote->status === 'posted') {
            throw new \Exception("Credit note {$creditNote->credit_note_number} is already posted.");
        }

        if ($creditNote->status === 'cancelled') {
            throw new \Exception("Cannot post cancelled credit note {$creditNote->credit_note_number}.");
        }

        // Validate business rules
        $errors = $creditNote->validateForPosting();
        if (! empty($errors)) {
            $this->error('Cannot post credit note:');
            foreach ($errors as $field => $error) {
                $this->line("  {$field}: {$error}");
            }
            exit(1);
        }
    }

    /**
     * Display posting preview.
     */
    protected function displayPostingPreview($creditNote, array $input): void
    {
        $this->info('Posting Preview');
        $this->line(str_repeat('=', 50));
        $this->line("Credit Note: {$creditNote->credit_note_number}");
        $this->line("Invoice: {$creditNote->invoice->invoice_number}");
        $this->line("Customer: {$creditNote->invoice->customer->name}");
        $this->line('Amount: $'.number_format($creditNote->total_amount, 2));
        $this->line("Currency: {$creditNote->currency}");

        $postingDate = $input['date'] ?? now()->format('Y-m-d');
        $this->line("Posting Date: {$postingDate}");

        if (isset($input['auto-apply'])) {
            $this->line('Auto-Apply: Yes');
        }

        $this->line('');
        $this->line('This will:');
        $this->line('• Change status from "draft" to "posted"');
        $this->line('• Create ledger entries');
        $this->line('• Make the credit note available for application to invoice');

        if (isset($input['auto-apply'])) {
            $this->line('• Automatically apply to invoice balance');
        }

        $this->line(str_repeat('=', 50));
    }

    /**
     * Post the credit note.
     */
    protected function postCreditNote($creditNote, array $input): void
    {
        $postingDate = $input['date'] ?? null;

        try {
            // Set posting date if provided
            if ($postingDate) {
                $creditNote->posting_date = $postingDate;
            }

            $this->creditNoteService->postCreditNote($creditNote, auth()->user());

            $this->info("✓ Credit note {$creditNote->credit_note_number} posted successfully!");
            $this->line('  Status: '.ucfirst($creditNote->status));
            $this->line('  Posted at: '.$creditNote->posted_at->format('Y-m-d H:i:s'));

            // Send email if requested
            if (isset($input['email'])) {
                $this->sendPostingEmail($creditNote, $input);
            }

            // Log the action
            $this->logExecution('credit_note_posted', [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'amount' => $creditNote->total_amount,
                'posting_date' => $postingDate,
                'email_sent' => isset($input['email']),
            ]);

        } catch (\Exception $e) {
            $this->error("✗ Failed to post credit note {$creditNote->credit_note_number}:");
            $this->line('  '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Auto-apply credit note to invoice.
     */
    protected function autoApplyToInvoice($creditNote): void
    {
        try {
            $applied = $this->creditNoteService->applyCreditNoteToInvoice($creditNote, auth()->user());

            if ($applied) {
                $this->info('✓ Credit note automatically applied to invoice!');
                $this->line('  Amount Applied: $'.number_format($creditNote->remainingBalance(), 2));
                $this->line('  Invoice Balance After: $'.number_format($creditNote->invoice->balance_due, 2));
            } else {
                $this->warn('⚠ Could not auto-apply credit note to invoice');
                $this->line('  This might be due to insufficient invoice balance or other constraints');
            }

        } catch (\Exception $e) {
            $this->warn('⚠ Auto-application failed:');
            $this->line('  '.$e->getMessage());
            $this->line('  Credit note was posted but not applied');
        }
    }

    /**
     * Display batch processing results.
     */
    protected function displayBatchResults(array $results, int $successCount, int $failureCount): void
    {
        $this->line('');
        $this->info('Batch Processing Results');
        $this->line(str_repeat('=', 50));

        $this->line('Total processed: '.count($results));
        $this->line("Successful: {$successCount}");
        $this->line("Failed: {$failureCount}");

        if ($failureCount > 0) {
            $this->line('');
            $this->warn('Failed items:');

            $tableData = [];
            foreach ($results as $result) {
                if ($result['status'] === 'failed') {
                    $tableData[] = [
                        'Identifier' => $result['identifier'],
                        'Reason' => $result['message'],
                    ];
                }
            }

            $this->table(['Identifier', 'Reason'], $tableData);
        }

        if ($successCount > 0) {
            $this->line('');
            $this->info('Successfully posted:');

            $tableData = [];
            foreach ($results as $result) {
                if ($result['status'] === 'success') {
                    $tableData[] = [
                        'Credit Note' => $result['credit_note_number'],
                        'Status' => 'Posted',
                    ];
                }
            }

            $this->table(['Credit Note', 'Status'], $tableData);
        }

        $this->line(str_repeat('=', 50));
    }

    /**
     * Display help information about posting credit notes.
     */
    protected function displayHelp(): void
    {
        $this->info('Credit Note Posting Help');
        $this->line(str_repeat('=', 50));
        $this->line('');
        $this->line('This command posts draft credit notes to the ledger, making them');
        $this->line('official and available for application to invoice balances.');
        $this->line('');
        $this->line('Requirements for posting:');
        $this->line('• Credit note must be in "draft" status');
        $this->line('• Credit note must have valid amounts');
        $this->line('• Related invoice must be in "posted" status');
        $this->line('');
        $this->line('What happens when posting:');
        $this->line('• Status changes from "draft" to "posted"');
        $this->line('• Ledger entries are created');
        $this->line('• Credit note becomes available for application');
        $this->line('• Posting date and timestamp are recorded');
        $this->line('');
        $this->line('Examples:');
        $this->line('  creditnote:post CN-001                    # Post single credit note');
        $this->line('  creditnote:post CN-001 --force             # Post without confirmation');
        $this->line('  creditnote:post CN-001 --auto-apply        # Post and apply to invoice');
        $this->line('  creditnote:post CN-001 --email             # Post and send email');
        $this->line('  creditnote:post --batch="CN-001,CN-002"    # Batch process multiple');
        $this->line('  creditnote:post CN-001 --date=2025-01-15   # Post with specific date');
        $this->line('');
    }

    /**
     * Send posting notification email.
     */
    protected function sendPostingEmail($creditNote, array $input): void
    {
        try {
            $this->line('  Sending email notification...');

            $emailOptions = [];

            if ($this->option('email-to')) {
                $emailOptions['to'] = $this->option('email-to');
            }

            if ($this->option('email-template')) {
                $emailOptions['view'] = "emails.{$this->option('email-template')}";
            }

            $emailOptions['message'] = "Dear {$creditNote->invoice->customer->name},\n\n".
                                     "We have posted the following credit note to your account:\n\n".
                                     "Credit Note: {$creditNote->credit_note_number}\n".
                                     'Amount: $'.number_format($creditNote->total_amount, 2)."\n".
                                     "Original Invoice: {$creditNote->invoice->invoice_number}\n\n".
                                     "This credit note is now available to be applied to your outstanding balance.\n\n".
                                     "Best regards,\n".
                                     "{$creditNote->company->name}";

            $result = $this->creditNoteService->generateAndEmailCreditNote($creditNote, auth()->user(), $emailOptions);

            if ($result['success']) {
                $this->line('  ✓ Email sent to: '.($result['recipient'] ?? $creditNote->invoice->customer->email));
            } else {
                $this->warn('  ⚠ Email sending failed: '.($result['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            $this->warn("  ⚠ Email notification failed: {$e->getMessage()}");
        }
    }
}
