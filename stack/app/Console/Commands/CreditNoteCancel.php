<?php

namespace App\Console\Commands;

class CreditNoteCancel extends CreditNoteBaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'creditnote:cancel
                           {identifier : Credit note ID, number, or partial match}
                           {--reason= : Cancellation reason}
                           {--force : Force cancellation without confirmation}
                           {--reverse-ledger : Reverse ledger entries (for posted credit notes)}
                           {--company= : Company ID (overrides current company)}
                           {--batch= : Batch process multiple credit notes (comma-separated)}';

    /**
     * The console command description.
     */
    protected $description = 'Cancel a credit note';

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

        // Validate that credit note can be cancelled
        $this->validateForCancellation($creditNote);

        // Get cancellation reason
        $reason = $this->getCancellationReason($input, $creditNote);

        // Show confirmation unless force flag is used
        if (! isset($input['force'])) {
            $this->displayCancellationPreview($creditNote, $reason, $input);

            if (! $this->confirm('Do you want to cancel this credit note?')) {
                $this->info('Cancellation cancelled.');

                return self::SUCCESS;
            }
        }

        // Cancel the credit note
        $this->cancelCreditNote($creditNote, $reason, $input);

        return self::SUCCESS;
    }

    /**
     * Handle batch processing of multiple credit notes.
     */
    protected function handleBatchProcessing(array $input): int
    {
        $identifiers = explode(',', $input['batch']);
        $identifiers = array_map('trim', $identifiers);

        $this->info('Batch Cancelling Credit Notes');
        $this->line(str_repeat('=', 40));

        // Get common cancellation reason for batch
        $batchReason = $input['reason'] ?? null;
        if (! $batchReason) {
            $batchReason = $this->ask('Enter cancellation reason for all credit notes:');
            while (empty($batchReason)) {
                $this->error('Cancellation reason is required.');
                $batchReason = $this->ask('Enter cancellation reason for all credit notes:');
            }
        }

        $successCount = 0;
        $failureCount = 0;
        $results = [];

        foreach ($identifiers as $identifier) {
            try {
                $creditNote = $this->findCreditNote($identifier);
                $this->validateForCancellation($creditNote);

                if (! isset($input['force'])) {
                    $this->line("\nProcessing: {$creditNote->credit_note_number}");
                    if (! $this->confirm("Cancel {$creditNote->credit_note_number}?")) {
                        $this->line("Skipped: {$creditNote->credit_note_number}");

                        continue;
                    }
                }

                $this->cancelCreditNote($creditNote, $batchReason, $input);

                $results[] = [
                    'identifier' => $identifier,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'status' => 'success',
                    'message' => 'Cancelled successfully',
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
     * Validate that credit note can be cancelled.
     */
    protected function validateForCancellation($creditNote): void
    {
        if ($creditNote->status === 'cancelled') {
            throw new \Exception("Credit note {$creditNote->credit_note_number} is already cancelled.");
        }

        // Check if there are any applications
        if ($creditNote->applications()->exists()) {
            $totalApplied = $creditNote->applications()->sum('amount_applied');
            if ($totalApplied > 0) {
                throw new \Exception(
                    "Cannot cancel credit note {$creditNote->credit_note_number}: ".
                    '$'.number_format($totalApplied, 2).' has already been applied to the invoice. '.
                    'You may need to create a reversal credit note instead.'
                );
            }
        }
    }

    /**
     * Get cancellation reason from input or user.
     */
    protected function getCancellationReason(array $input, $creditNote): string
    {
        $reason = $input['reason'] ?? null;

        if (! $reason) {
            // Provide some common reasons as choices
            $commonReasons = [
                'duplicate' => 'Duplicate credit note',
                'error' => 'Data entry error',
                'customer_request' => 'Customer request',
                'policy_violation' => 'Policy violation',
                'other' => 'Other (specify)',
            ];

            $choice = $this->choice('Select cancellation reason:', array_values($commonReasons));

            if ($choice === 'Other (specify)') {
                $reason = $this->ask('Enter specific cancellation reason:');
                while (empty($reason)) {
                    $this->error('Cancellation reason is required.');
                    $reason = $this->ask('Enter specific cancellation reason:');
                }
            } else {
                $reason = $choice;
            }
        }

        return $reason;
    }

    /**
     * Display cancellation preview.
     */
    protected function displayCancellationPreview($creditNote, string $reason, array $input): void
    {
        $this->info('Cancellation Preview');
        $this->line(str_repeat('=', 50));
        $this->line("Credit Note: {$creditNote->credit_note_number}");
        $this->line("Invoice: {$creditNote->invoice->invoice_number}");
        $this->line("Customer: {$creditNote->invoice->customer->name}");
        $this->line('Current Status: '.ucfirst($creditNote->status));
        $this->line('Total Amount: $'.number_format($creditNote->total_amount, 2));
        $this->line('Remaining Balance: $'.number_format($creditNote->remainingBalance(), 2));
        $this->line("Cancellation Reason: {$reason}");

        if ($creditNote->status === 'posted' && isset($input['reverse-ledger'])) {
            $this->line('Reverse Ledger: Yes');
        }

        $this->line('');
        $this->warn('Warning:');
        if ($creditNote->status === 'posted') {
            $this->line('• This will cancel a posted credit note');
            if (isset($input['reverse-ledger'])) {
                $this->line('• Ledger entries will be reversed');
            } else {
                $this->line('• Ledger entries will remain but the credit note will be cancelled');
            }
        } else {
            $this->line('• This will cancel a draft credit note');
        }

        $this->line('• This action cannot be undone');
        $this->line('• The credit note number will not be reused');
        $this->line(str_repeat('=', 50));
    }

    /**
     * Cancel the credit note.
     */
    protected function cancelCreditNote($creditNote, string $reason, array $input): void
    {
        try {
            $this->creditNoteService->cancelCreditNote($creditNote, $reason, auth()->user());

            $this->info("✓ Credit note {$creditNote->credit_note_number} cancelled successfully!");
            $this->line('  Status: '.ucfirst($creditNote->status));
            $this->line('  Cancelled at: '.$creditNote->cancelled_at->format('Y-m-d H:i:s'));
            $this->line("  Cancellation Reason: {$reason}");

            // Additional information based on original status
            if ($creditNote->getOriginal('status') === 'posted') {
                $this->line('  Previous Status: Posted');
                if (isset($input['reverse-ledger'])) {
                    $this->line('  Ledger Entries: Reversed');
                } else {
                    $this->line('  Ledger Entries: Preserved');
                }
            }

            // Log the action
            $this->logExecution('credit_note_cancelled', [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'reason' => $reason,
                'previous_status' => $creditNote->getOriginal('status'),
                'reverse_ledger' => isset($input['reverse-ledger']),
            ]);

        } catch (\Exception $e) {
            $this->error("✗ Failed to cancel credit note {$creditNote->credit_note_number}:");
            $this->line('  '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Display batch processing results.
     */
    protected function displayBatchResults(array $results, int $successCount, int $failureCount): void
    {
        $this->line('');
        $this->info('Batch Cancellation Results');
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
            $this->info('Successfully cancelled:');

            $tableData = [];
            foreach ($results as $result) {
                if ($result['status'] === 'success') {
                    $tableData[] = [
                        'Credit Note' => $result['credit_note_number'],
                        'Status' => 'Cancelled',
                    ];
                }
            }

            $this->table(['Credit Note', 'Status'], $tableData);
        }

        $this->line(str_repeat('=', 50));
    }

    /**
     * Display help information about cancelling credit notes.
     */
    protected function displayHelp(): void
    {
        $this->info('Credit Note Cancellation Help');
        $this->line(str_repeat('=', 50));
        $this->line('');
        $this->line('This command cancels credit notes, making them inactive and preventing');
        $this->line('any further applications to invoices. Cancellation cannot be undone.');
        $this->line('');
        $this->line('Cancellation rules:');
        $this->line('• Draft credit notes can be cancelled freely');
        $this->line('• Posted credit notes can be cancelled but ledger entries may remain');
        $this->line('• Credit notes with applications cannot be cancelled');
        $this->line('• A valid cancellation reason is required');
        $this->line('');
        $this->line('What happens when cancelling:');
        $this->line('• Status changes to "cancelled"');
        $this->line('• Cancellation date and reason are recorded');
        $this->line('• Credit note can no longer be applied to invoices');
        $this->line('• For posted notes, ledger entries can optionally be reversed');
        $this->line('');
        $this->line('Examples:');
        $this->line('  creditnote:cancel CN-001 --reason="Duplicate entry"');
        $this->line('  creditnote:cancel CN-001 --force');
        $this->line('  creditnote:cancel CN-001 --reverse-ledger');
        $this->line('  creditnote:cancel --batch="CN-001,CN-002" --reason="Batch error"');
        $this->line('');
    }
}
