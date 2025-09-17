<?php

namespace App\Listeners\Invoicing;

use App\Events\Invoicing\InvoicePosted;
use App\Models\AccountsReceivable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class UpdateAccountsReceivableForPostedInvoice implements ShouldQueue
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
    public function handle(InvoicePosted $event): void
    {
        $invoice = $event->invoice;

        try {
            // Create or update accounts receivable record for this invoice
            AccountsReceivable::updateOrCreate(
                [
                    'company_id' => $invoice->company_id,
                    'customer_id' => $invoice->customer_id,
                    'invoice_id' => $invoice->invoice_id,
                ],
                [
                    'amount_due' => $invoice->balance_due,
                    'original_amount' => $invoice->total_amount,
                    'invoice_date' => $invoice->invoice_date,
                    'due_date' => $invoice->due_date,
                    'status' => 'open',
                    'aging_bucket' => $this->calculateAgingBucket($invoice->due_date),
                    'metadata' => [
                        'invoice_number' => $invoice->invoice_number,
                        'posted_at' => $invoice->posted_at->toISOString(),
                        'last_updated' => now()->toISOString(),
                    ],
                ]
            );

            Log::info('Accounts receivable updated for posted invoice', [
                'invoice_id' => $invoice->invoice_id,
                'customer_id' => $invoice->customer_id,
                'amount_due' => $invoice->balance_due,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update accounts receivable for posted invoice', [
                'invoice_id' => $invoice->invoice_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate the aging bucket based on the due date.
     */
    protected function calculateAgingBucket($dueDate): string
    {
        $daysOverdue = now()->diffInDays($dueDate, false);

        if ($daysOverdue > 90) {
            return '91+';
        } elseif ($daysOverdue > 60) {
            return '61-90';
        } elseif ($daysOverdue > 30) {
            return '31-60';
        } elseif ($daysOverdue > 0) {
            return '1-30';
        } else {
            return 'current';
        }
    }
}
