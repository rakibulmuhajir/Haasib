<?php

namespace App\Listeners\Invoicing;

use App\Events\Invoicing\InvoiceCancelled;
use App\Models\AccountsReceivable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdateAccountsReceivableForCancelledInvoice implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InvoiceCancelled $event): void
    {
        $invoice = $event->invoice;

        try {
            // Find the accounts receivable record for this invoice
            $ar = AccountsReceivable::where('invoice_id', $invoice->invoice_id)->first();

            if ($ar) {
                // Mark as not collectible; set amount due to 0 and record metadata
                $ar->amount_due = 0;
                $ar->metadata = array_merge($ar->metadata ?? [], [
                    'cancelled_at' => now()->toISOString(),
                    'cancellation_reason' => $event->context['reason'] ?? 'No reason provided',
                    'last_updated' => now()->toISOString(),
                ]);
                $ar->save();

                Log::info('Accounts receivable updated for cancelled invoice', [
                    'invoice_id' => $invoice->invoice_id,
                    'cancellation_reason' => $event->context['reason'] ?? 'No reason provided',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update accounts receivable for cancelled invoice', [
                'invoice_id' => $invoice->invoice_id,
                'error' => $e->getMessage(),
            ]);
            // Swallow to avoid breaking API responses
        }
    }
}
