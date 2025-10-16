<?php

namespace Modules\Accounting\Domain\Ledgers\Listeners;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntrySource;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Invoices\Events\InvoicePosted;
use Modules\Accounting\Domain\Ledgers\Actions\AutoJournalEntryAction;

class InvoicePostedSubscriber
{
    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            InvoicePosted::class => 'handle',
        ];
    }

    /**
     * Handle the event.
     */
    public function handle(InvoicePosted $event): void
    {
        $invoice = $event->invoice;

        DB::transaction(function () use ($invoice) {
            $autoJournalAction = app(AutoJournalEntryAction::class);

            // Get required accounts
            $receivablesAccountId = $this->getAccountsReceivableAccountId($invoice->company_id);
            $revenueAccountId = $this->getRevenueAccountId($invoice->company_id);
            $taxAccountId = $this->getTaxAccountId($invoice->company_id);

            if (! $receivablesAccountId || ! $revenueAccountId) {
                // Log error but don't fail the invoice posting
                logger()->error('Required accounts not found for invoice journal entry', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $invoice->company_id,
                    'receivables_account_id' => $receivablesAccountId,
                    'revenue_account_id' => $revenueAccountId,
                ]);

                return;
            }

            // Build journal entry lines
            $lines = [
                [
                    'account_id' => $receivablesAccountId,
                    'debit_credit' => 'debit',
                    'amount' => $invoice->total,
                    'description' => "Invoice #{$invoice->invoice_number}",
                ],
                [
                    'account_id' => $revenueAccountId,
                    'debit_credit' => 'credit',
                    'amount' => $invoice->subtotal,
                    'description' => "Revenue from invoice #{$invoice->invoice_number}",
                ],
            ];

            // Add tax line if applicable
            if ($invoice->tax_amount > 0 && $taxAccountId) {
                $lines[] = [
                    'account_id' => $taxAccountId,
                    'debit_credit' => 'credit',
                    'amount' => $invoice->tax_amount,
                    'description' => "Sales tax from invoice #{$invoice->invoice_number}",
                ];
            }

            // Add discount line if applicable
            if ($invoice->discount_amount > 0) {
                $discountAccountId = $this->getDiscountAccountId($invoice->company_id);
                if ($discountAccountId) {
                    $lines[] = [
                        'account_id' => $discountAccountId,
                        'debit_credit' => 'debit', // Discounts reduce revenue
                        'amount' => $invoice->discount_amount,
                        'description' => "Discount on invoice #{$invoice->invoice_number}",
                    ];
                }
            }

            $journalData = [
                'company_id' => $invoice->company_id,
                'description' => "Invoice #{$invoice->invoice_number}",
                'date' => $invoice->invoice_date,
                'type' => 'sales',
                'currency' => $invoice->currency,
                'reference' => $invoice->invoice_number,
                'lines' => $lines,
                'source_data' => [
                    'source_type' => 'invoice',
                    'source_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id' => $invoice->customer_id,
                    'subtotal' => $invoice->subtotal,
                    'tax_amount' => $invoice->tax_amount,
                    'discount_amount' => $invoice->discount_amount,
                    'total' => $invoice->total,
                    'due_date' => $invoice->due_date,
                    'line_items' => $this->getInvoiceLineItems($invoice),
                ],
                'idempotency_key' => "invoice_posted_{$invoice->id}",
            ];

            $result = $autoJournalAction->execute($journalData);

            // Create source document link
            if (isset($result['journal_entry_id'])) {
                JournalEntrySource::create([
                    'journal_entry_id' => $result['journal_entry_id'],
                    'source_type' => 'invoice',
                    'source_id' => $invoice->id,
                    'source_data' => $journalData['source_data'],
                ]);
            }
        });
    }

    /**
     * Get accounts receivable account ID for company.
     */
    private function getAccountsReceivableAccountId(string $companyId): ?string
    {
        return Account::where('company_id', $companyId)
            ->where('code', '12000') // Accounts Receivable
            ->first()?->id;
    }

    /**
     * Get revenue account ID for company.
     */
    private function getRevenueAccountId(string $companyId): ?string
    {
        return Account::where('company_id', $companyId)
            ->where('code', '40000') // Sales Revenue
            ->first()?->id;
    }

    /**
     * Get tax account ID for company.
     */
    private function getTaxAccountId(string $companyId): ?string
    {
        return Account::where('company_id', $companyId)
            ->where('code', '22000') // Sales Tax Payable
            ->first()?->id;
    }

    /**
     * Get discount/allowance account ID for company.
     */
    private function getDiscountAccountId(string $companyId): ?string
    {
        return Account::where('company_id', $companyId)
            ->where('code', '42000') // Sales Discounts
            ->first()?->id;
    }

    /**
     * Get invoice line items for source data.
     */
    private function getInvoiceLineItems(Invoice $invoice): array
    {
        // This would depend on your invoice line items structure
        // For now, return a basic structure
        return [
            [
                'description' => $invoice->description ?? 'Invoice items',
                'quantity' => 1,
                'unit_price' => $invoice->subtotal,
                'total' => $invoice->subtotal,
            ],
        ];
    }
}
