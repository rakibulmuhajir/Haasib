<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;

/**
 * A buyer's running balance, built from the canonical AR-facing models
 * (invoices, payments, credit notes) rather than from any single entry
 * point's own bookkeeping. An invoice created standalone via /invoices, one
 * created inline inside a fuel station Daily Close, a payment recorded at
 * /payments, and one entered inside a close (see DailyClosePaymentsReceived)
 * are all the same kind of row here: whichever door they came through, the
 * buyer owes or has paid the same real money, so the statement must show all
 * of them or the balance lies.
 *
 * The close's own consolidated journal posts a single lump AR debit for all
 * of a day's credit sales combined (no per-customer dimension), so this
 * statement is intentionally built from the canonical subsidiary records
 * (Invoice/Payment/CreditNote), not from journal_entries directly - those
 * records are what actually carries the customer attribution.
 */
class CustomerStatementService
{
    public function statement(Customer $customer): array
    {
        $rows = [];

        Invoice::where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->get()
            ->each(function (Invoice $invoice) use (&$rows) {
                $rows[] = [
                    'date' => optional($invoice->invoice_date)->toDateString(),
                    'type' => 'invoice',
                    'reference' => $invoice->invoice_number,
                    'description' => 'Invoice ' . $invoice->invoice_number,
                    'debit' => (float) $invoice->total_amount,
                    'credit' => 0.0,
                    'source_id' => $invoice->id,
                    'link' => "invoices/{$invoice->id}",
                    'created_at' => optional($invoice->created_at)->toISOString(),
                ];
            });

        Payment::where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->get()
            ->each(function (Payment $payment) use (&$rows) {
                $rows[] = [
                    'date' => optional($payment->payment_date)->toDateString(),
                    'type' => 'payment',
                    'reference' => $payment->payment_number,
                    'description' => 'Payment received' . ($payment->reference_number ? " ({$payment->reference_number})" : ''),
                    'debit' => 0.0,
                    'credit' => (float) $payment->amount,
                    'source_id' => $payment->id,
                    'link' => "payments/{$payment->id}",
                    'created_at' => optional($payment->created_at)->toISOString(),
                ];
            });

        if (class_exists(CreditNote::class)) {
            CreditNote::where('company_id', $customer->company_id)
                ->where('customer_id', $customer->id)
                ->whereNotIn('status', ['draft', 'void', 'cancelled'])
                ->get()
                ->each(function (CreditNote $creditNote) use (&$rows) {
                    $rows[] = [
                        'date' => optional($creditNote->credit_date)->toDateString(),
                        'type' => 'credit_note',
                        'reference' => $creditNote->credit_note_number,
                        'description' => 'Credit note ' . $creditNote->credit_note_number,
                        'debit' => 0.0,
                        'credit' => (float) $creditNote->amount,
                        'source_id' => $creditNote->id,
                        'link' => "credit-notes/{$creditNote->id}",
                        'created_at' => optional($creditNote->created_at)->toISOString(),
                    ];
                });
        }

        usort($rows, fn ($a, $b) => [$a['date'], $a['created_at']] <=> [$b['date'], $b['created_at']]);

        $running = 0.0;
        $statement = [[
            'date' => null, 'type' => 'opening_balance', 'reference' => null,
            'description' => 'Opening balance', 'debit' => 0.0, 'credit' => 0.0,
            'source_id' => null, 'link' => null, 'balance' => 0.0,
        ]];
        foreach ($rows as $row) {
            $running = round($running + $row['debit'] - $row['credit'], 2);
            $row['balance'] = $running;
            $statement[] = $row;
        }

        return [
            'rows' => $statement,
            'closing_balance' => $running,
        ];
    }
}
