<?php

namespace App\Listeners\Invoicing;

use App\Events\Invoicing\InvoicePaid;
use App\Models\AccountsReceivable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdateAccountsReceivableForPaidInvoice implements ShouldQueue
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
    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice;

        try {
            // Find the accounts receivable record for this invoice
            $ar = AccountsReceivable::where('invoice_id', $invoice->invoice_id)->first();

            if ($ar) {
                if ($invoice->isFullyPaid()) {
                    // Mark as paid
                    $ar->status = 'paid';
                    $ar->amount_due = 0;
                    $ar->metadata['paid_at'] = now()->toISOString();
                    $ar->metadata['payment_notes'] = 'Invoice fully paid';
                } else {
                    // Update remaining balance
                    $ar->status = 'partial';
                    $ar->amount_due = $invoice->balance_due;
                    $ar->metadata['last_payment_date'] = now()->toISOString();
                    $ar->metadata['payment_notes'] = 'Partial payment received';
                }

                $ar->metadata['last_updated'] = now()->toISOString();
                $ar->save();

                Log::info('Accounts receivable updated for paid invoice', [
                    'invoice_id' => $invoice->invoice_id,
                    'status' => $ar->status,
                    'amount_due' => $ar->amount_due,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update accounts receivable for paid invoice', [
                'invoice_id' => $invoice->invoice_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
