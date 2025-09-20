<?php

namespace App\Listeners\Ledger;

use App\Events\Invoicing\InvoiceCancelled;
use App\Services\LedgerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class VoidJournalEntryForCancelledInvoice implements ShouldQueue
{
    public function __construct(private readonly LedgerService $ledgerService)
    {
    }

    public function handle(InvoiceCancelled $event): void
    {
        $invoice = $event->invoice;

        try {
            $entry = $invoice->journalEntry()->first();
            if (! $entry) {
                return; // Nothing to void
            }

            $reason = (string) ($event->context['reason'] ?? 'Invoice cancelled');
            $this->ledgerService->voidJournalEntry($entry, $reason);
        } catch (\Throwable $e) {
            Log::error('Failed to void journal entry for cancelled invoice', [
                'invoice_id' => $invoice->invoice_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

