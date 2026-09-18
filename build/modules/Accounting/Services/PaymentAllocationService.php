<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Invoice;

/**
 * The one place an invoice's paid_amount/balance/status get updated in response to money
 * applied against it. Payment\CreateAction (a new payment settling one or more invoices)
 * and Payment\ApplyCreditAction (an existing on-account credit applied to an invoice later)
 * both funnel through here so the paid/partial/paid_at bookkeeping can never drift apart
 * between the two entry points.
 */
class PaymentAllocationService
{
    /**
     * @return array{status: string, balance: float}
     */
    public function settleInvoice(Invoice $invoice, float $invoiceCurrencyAmount): array
    {
        $newBalance = round((float) $invoice->balance - $invoiceCurrencyAmount, 6);
        $newPaidAmount = round((float) $invoice->paid_amount + $invoiceCurrencyAmount, 6);
        $newStatus = $newBalance <= 0.000001
            ? 'paid'
            : ($newPaidAmount > 0 ? 'partial' : $invoice->status);

        $invoice->update([
            'status' => $newStatus,
            'paid_amount' => $newPaidAmount,
            'balance' => max(0, $newBalance),
            'paid_at' => $newStatus === 'paid' ? now() : null,
        ]);

        return ['status' => $newStatus, 'balance' => max(0, $newBalance)];
    }

    /**
     * A customer's invoices in the order an auto-allocated payment (or credit application)
     * should settle them: oldest invoice_date first, invoice_number breaking ties.
     */
    public function openInvoicesOldestFirst(string $companyId, string $customerId, ?string $excludeInvoiceId = null)
    {
        return Invoice::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['approved', 'sent', 'viewed', 'partial', 'overdue'])
            ->where('balance', '>', 0)
            ->when($excludeInvoiceId, fn ($q) => $q->where('id', '!=', $excludeInvoiceId))
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get();
    }
}
