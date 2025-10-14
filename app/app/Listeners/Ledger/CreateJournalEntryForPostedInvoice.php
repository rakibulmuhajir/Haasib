<?php

namespace App\Listeners\Ledger;

use App\Events\Invoicing\InvoicePosted;
use App\Models\Company;
use App\Services\LedgerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class CreateJournalEntryForPostedInvoice implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct(protected LedgerService $ledgerService)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InvoicePosted $event): void
    {
        try {
            $invoice = $event->invoice;
            $company = $invoice->company;

            // Ensure this process is idempotent. If a journal entry for this invoice already exists, do nothing.
            if ($invoice->journalEntry()->exists()) {
                Log::warning('Attempted to create a duplicate journal entry for a posted invoice.', ['invoice_id' => $invoice->invoice_id]);

                return;
            }

            // Fetch the required ledger accounts from company settings.
            // You should have a robust way to manage these account mappings.
            $accountsReceivableAccountId = $company->settings['default_accounts_receivable_account_id'] ?? null;
            $salesRevenueAccountId = $company->settings['default_sales_revenue_account_id'] ?? null;
            $salesTaxAccountId = $company->settings['default_sales_tax_account_id'] ?? null;

            if (! $accountsReceivableAccountId || ! $salesRevenueAccountId) {
                Log::error('Missing default ledger account mappings for sales posting.', ['company_id' => $company->id]);

                return;
            }

            $lines = [];

            // Debit Accounts Receivable for the total amount
            $lines[] = [
                'account_id' => $accountsReceivableAccountId,
                'debit_amount' => $invoice->total_amount,
                'credit_amount' => 0,
                'description' => "Accounts receivable for Invoice #{$invoice->invoice_number}",
            ];

            // Credit Sales Revenue for the subtotal
            $lines[] = [
                'account_id' => $salesRevenueAccountId,
                'debit_amount' => 0,
                'credit_amount' => $invoice->subtotal,
                'description' => "Sales revenue for Invoice #{$invoice->invoice_number}",
            ];

            // Credit Sales Tax Payable if there is tax
            if ($invoice->tax_amount > 0 && $salesTaxAccountId) {
                $lines[] = [
                    'account_id' => $salesTaxAccountId,
                    'debit_amount' => 0,
                    'credit_amount' => $invoice->tax_amount,
                    'description' => "Sales tax for Invoice #{$invoice->invoice_number}",
                ];
            }

            $journalEntry = $this->ledgerService->createJournalEntry(
                company: $company,
                description: "To record sale for Invoice #{$invoice->invoice_number}",
                lines: $lines,
                reference: $invoice->invoice_number,
                date: optional($invoice->posted_at)->toDateString() ?? now()->toDateString(),
                sourceType: 'invoice',
                sourceId: $invoice->invoice_id
            );

            $this->ledgerService->postJournalEntry($journalEntry);
        } catch (\Throwable $e) {
            Log::error('Failed to create/post journal entry for invoice', [
                'invoice_id' => $event->invoice->invoice_id,
                'error' => $e->getMessage(),
            ]);
            // Do not throw to avoid breaking API responses; rely on logs for diagnostics
        }
    }
}
