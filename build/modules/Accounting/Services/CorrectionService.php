<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\CreditNoteApplication;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\InvoiceLineItem;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Services\Concerns\RecordsCorrections;
use App\Services\CommandBus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrections to posted records, done the way an accountant would: the original is never
 * rewritten or deleted. What changes is posted as its own entry and recorded in acct.corrections
 * with the before and after, the reason and who made it.
 *
 * Customers are a sub-ledger of the one receivables account, so moving an invoice or a payment
 * from one customer to another leaves the receivables total unchanged. It is still posted -- a
 * correcting journal out of the old customer and into the new, on that account -- so the move
 * shows in the journal with its correction number, as a sub-ledger transfer would in any set of
 * books. Payments already applied to a moved invoice by the old customer come off it and go back
 * to that customer's on-account credit; they are theirs, not the new customer's.
 *
 * Splitting an invoice between customers keeps the invoice (it may be a posted Daily Close sale,
 * which never changes): the first customer keeps it, a credit note takes each other share off it,
 * and a new invoice with the same date and reference puts that share on the customer it belongs
 * to. Revenue is untouched; only who owes it moves.
 */
class CorrectionService
{
    use RecordsCorrections;

    public function __construct(private readonly GlPostingService $posting) {}

    /** Move an invoice to the customer it really belongs to. */
    public function invoiceChangeCustomer(Invoice $invoice, string $customerId, string $reason): array
    {
        $to = $this->customer($invoice->company_id, $customerId);
        if ($to->id === $invoice->customer_id) {
            throw ValidationException::withMessages(['customer_id' => 'That is already this invoice\'s customer.']);
        }

        return $this->run($invoice->company_id, function () use ($invoice, $to, $reason) {
            $from = Customer::find($invoice->customer_id);
            $unapplied = $this->unapplyForeignPayments($invoice, $to->id);
            DB::table('acct.invoices')->where('id', $invoice->id)->update(['customer_id' => $to->id, 'updated_at' => now()]);

            $amount = round((float) $invoice->total_amount, 2);
            $ar = $this->receivablesAccount($invoice);
            $journal = $this->journal($invoice->company_id, $invoice->currency, "Correction: {$invoice->invoice_number} moved from {$from?->name} to {$to->name}", [
                ['account_id' => $ar, 'type' => 'debit', 'amount' => $amount, 'description' => "{$invoice->invoice_number} to {$to->name}"],
                ['account_id' => $ar, 'type' => 'credit', 'amount' => $amount, 'description' => "{$invoice->invoice_number} from {$from?->name}"],
            ]);

            return $this->record($invoice->company_id, 'invoice', $invoice->id, 'change_customer', $reason, [
                'number' => $invoice->invoice_number,
                'before' => ['customer_id' => $from?->id, 'customer' => $from?->name],
                'after' => ['customer_id' => $to->id, 'customer' => $to->name],
                'payments_unapplied' => $unapplied,
            ], $journal);
        });
    }

    /**
     * Split an invoice between the customers it really belongs to. The invoice stays with the
     * customer it was billed to and is cancelled there by a credit note for each share; each share
     * becomes a new invoice of its own for its customer, same date and reference. So each buyer
     * sees only their own sale, and the correction sits on the record of whoever was billed wrongly.
     * Revenue is untouched: only who owes it moves. Payments on the invoice come off first
     * ($unapplyPayments), each staying with its payer as credit on account.
     */
    public function invoiceSplit(Invoice $invoice, array $shares, string $reason, bool $unapplyPayments = false): array
    {
        // A share may also name the customer's vehicle, its litres and its slip number: one sale
        // that was several vehicles' fuel, split into an invoice per vehicle for the same customer.
        $shares = array_values(array_filter(array_map(fn ($s) => [
            'customer_id' => (string) $s['customer_id'],
            'amount' => round((float) $s['amount'], 2),
            'unit_id' => ($s['unit_id'] ?? null) ?: null,
            'quantity' => isset($s['quantity']) && (float) $s['quantity'] > 0 ? round((float) $s['quantity'], 4) : null,
            'reference' => isset($s['reference']) && trim((string) $s['reference']) !== '' ? trim((string) $s['reference']) : null,
        ], $shares), fn ($s) => $s['amount'] > 0));
        if (count($shares) < 2) {
            throw ValidationException::withMessages(['shares' => 'Split into at least two parts.']);
        }
        // What is still on it: its total less anything a credit note already took off.
        $credited = (float) DB::table('acct.credit_note_applications')->where('invoice_id', $invoice->id)->sum('amount_applied');
        $open = round((float) $invoice->total_amount - $credited, 2);
        if (abs(array_sum(array_column($shares, 'amount')) - $open) > 0.005) {
            throw ValidationException::withMessages(['shares' => 'The shares must add up to '.number_format($open, 2).'.']);
        }
        foreach ($shares as $i => $share) {
            $this->customer($invoice->company_id, $share['customer_id']);
            if ($share['unit_id'] && ! \App\Modules\Accounting\Models\CustomerUnit::where('company_id', $invoice->company_id)
                ->where('customer_id', $share['customer_id'])->whereKey($share['unit_id'])->exists()) {
                throw ValidationException::withMessages(["shares.{$i}.unit_id" => "Choose one of that customer's vehicles."]);
            }
        }
        if (! $unapplyPayments && PaymentAllocation::where('invoice_id', $invoice->id)->exists()) {
            throw ValidationException::withMessages(['shares' => 'Payments are applied to this invoice. Take them off first.']);
        }

        return $this->run($invoice->company_id, function () use ($invoice, $shares, $reason, $open) {
            $from = Customer::find($invoice->customer_id);
            $unapplied = $this->unapplyForeignPayments($invoice, '');

            $ar = $this->receivablesAccount($invoice);
            $lines = [];
            $created = [];
            foreach ($shares as $share) {
                $customer = $this->customer($invoice->company_id, $share['customer_id']);
                $new = $this->splitInvoice($invoice, $customer, $share);
                $credit = $this->splitCredit($invoice->fresh(), $from, $share['amount'], "{$new->invoice_number} · {$customer->name}");
                $lines[] = ['account_id' => $ar, 'type' => 'debit', 'amount' => $share['amount'], 'description' => "{$new->invoice_number} to {$customer->name} (from {$invoice->invoice_number})"];
                $lines[] = ['account_id' => $ar, 'type' => 'credit', 'amount' => $share['amount'], 'description' => "{$credit->credit_note_number} off {$invoice->invoice_number}"];
                $created[] = ['customer' => $customer->name, 'amount' => $share['amount'], 'invoice' => $new->invoice_number, 'invoice_id' => $new->id, 'credit_note' => $credit->credit_note_number, 'credit_note_id' => $credit->id,
                    'vehicle' => $new->unit?->name, 'quantity' => $share['quantity'], 'reference' => $share['reference']];
            }

            $sameCustomer = collect($shares)->every(fn ($s) => $s['customer_id'] === $invoice->customer_id);
            $journal = $this->journal($invoice->company_id, $invoice->currency, "Correction: {$invoice->invoice_number} split ".($sameCustomer ? 'by vehicle' : 'between customers'), $lines);
            foreach ($created as $c) {
                DB::table('acct.invoices')->where('id', $c['invoice_id'])->update(['transaction_id' => $journal]);
                DB::table('acct.credit_notes')->where('id', $c['credit_note_id'])->update(['transaction_id' => $journal]);
            }

            return $this->record($invoice->company_id, 'invoice', $invoice->id, 'split', $reason, [
                'number' => $invoice->invoice_number,
                'before' => ['customer_id' => $from?->id, 'customer' => $from?->name, 'amount' => $open],
                'after' => ['customer_id' => $from?->id, 'customer' => $from?->name, 'amount' => 0, 'shares' => $created],
                'payments_unapplied' => $unapplied,
            ], $journal);
        });
    }

    /**
     * Move a payment to the customer who really paid it. What it had paid off comes off those
     * invoices; with $applyOldestFirst it then pays the new customer's oldest unpaid invoices.
     */
    public function paymentChangeCustomer(Payment $payment, string $customerId, string $reason, bool $applyOldestFirst = true): array
    {
        $to = $this->customer($payment->company_id, $customerId);
        if ($to->id === $payment->customer_id) {
            throw ValidationException::withMessages(['customer_id' => 'That is already this payment\'s customer.']);
        }

        $result = $this->run($payment->company_id, function () use ($payment, $to, $reason) {
            $from = Customer::find($payment->customer_id);
            $unapplied = $this->unapplyPayment($payment);
            DB::table('acct.payments')->where('id', $payment->id)->update(['customer_id' => $to->id, 'updated_at' => now()]);

            $amount = round((float) $payment->amount, 2);
            $ar = $this->paymentReceivablesAccount($payment);
            $journal = $this->journal($payment->company_id, $payment->currency ?? 'PKR', "Correction: {$payment->payment_number} moved from {$from?->name} to {$to->name}", [
                ['account_id' => $ar, 'type' => 'debit', 'amount' => $amount, 'description' => "{$payment->payment_number} from {$from?->name}"],
                ['account_id' => $ar, 'type' => 'credit', 'amount' => $amount, 'description' => "{$payment->payment_number} to {$to->name}"],
            ]);

            return $this->record($payment->company_id, 'payment', $payment->id, 'change_customer', $reason, [
                'number' => $payment->payment_number,
                'before' => ['customer_id' => $from?->id, 'customer' => $from?->name],
                'after' => ['customer_id' => $to->id, 'customer' => $to->name],
                'unapplied_from' => $unapplied,
            ], $journal);
        });

        if ($applyOldestFirst) {
            $this->applyOldestFirst($payment->fresh(), $to);
        }

        return $result;
    }

    /**
     * Split a payment between the customers who paid it together. The cash came in once and stays
     * as it was posted; only who it came from changes. The payment keeps the first share (its
     * amount is corrected down to it, the before and after kept in the correction), each other share
     * becomes a payment of its own for that customer, same date and account, posted by the
     * correcting journal. Whatever the payment had paid off comes off first; every share is left on
     * account for its customer to apply.
     */
    public function paymentSplit(Payment $payment, array $shares, string $reason): array
    {
        $total = round((float) $payment->amount, 2);
        $shares = array_values(array_filter(array_map(fn ($s) => ['customer_id' => (string) $s['customer_id'], 'amount' => round((float) $s['amount'], 2)], $shares), fn ($s) => $s['amount'] > 0));
        if (count($shares) < 2) {
            throw ValidationException::withMessages(['shares' => 'Split between at least two customers.']);
        }
        if (abs(array_sum(array_column($shares, 'amount')) - $total) > 0.005) {
            throw ValidationException::withMessages(['shares' => 'The shares must add up to '.number_format($total, 2).'.']);
        }
        foreach ($shares as $share) {
            $this->customer($payment->company_id, $share['customer_id']);
        }

        return $this->run($payment->company_id, function () use ($payment, $shares, $reason, $total) {
            $from = Customer::find($payment->customer_id);
            $keeper = $this->customer($payment->company_id, $shares[0]['customer_id']);
            $rate = (float) ($payment->exchange_rate ?: 1);
            $unapplied = $this->unapplyPayment($payment);

            // What it keeps, all on account.
            PaymentAllocation::where('payment_id', $payment->id)->whereNull('invoice_id')->delete();
            DB::table('acct.payments')->where('id', $payment->id)->update([
                'customer_id' => $keeper->id,
                'amount' => $shares[0]['amount'],
                'base_amount' => round($shares[0]['amount'] * $rate, 2),
                'updated_at' => now(),
            ]);
            $this->onAccount($payment, $payment->id, $shares[0]['amount'], $rate);

            $ar = $this->paymentReceivablesAccount($payment);
            $lines = [];
            $created = [];
            foreach (array_slice($shares, 1) as $share) {
                $customer = $this->customer($payment->company_id, $share['customer_id']);
                $new = Payment::create([
                    'company_id' => $payment->company_id,
                    'customer_id' => $customer->id,
                    'payment_number' => Payment::generatePaymentNumber($payment->company_id),
                    'payment_date' => $payment->payment_date,
                    'amount' => $share['amount'],
                    'currency' => $payment->currency,
                    'exchange_rate' => $payment->exchange_rate,
                    'base_currency' => $payment->base_currency,
                    'base_amount' => round($share['amount'] * $rate, 2),
                    'transaction_charge' => 0,
                    'base_transaction_charge' => 0,
                    'payment_method' => $payment->payment_method,
                    'deposit_account_id' => $payment->deposit_account_id,
                    'reference_number' => $payment->reference_number,
                    'notes' => "Split from {$payment->payment_number}",
                    'created_by_user_id' => Auth::id(),
                ]);
                $this->onAccount($payment, $new->id, $share['amount'], $rate);
                $lines[] = ['account_id' => $ar, 'type' => 'debit', 'amount' => $share['amount'], 'description' => "{$payment->payment_number} share off {$from?->name}"];
                $lines[] = ['account_id' => $ar, 'type' => 'credit', 'amount' => $share['amount'], 'description' => "{$new->payment_number} from {$customer->name}"];
                $created[] = ['customer' => $customer->name, 'amount' => $share['amount'], 'payment' => $new->payment_number, 'payment_id' => $new->id];
            }
            if ($keeper->id !== ($from?->id)) {
                $lines[] = ['account_id' => $ar, 'type' => 'debit', 'amount' => $shares[0]['amount'], 'description' => "{$payment->payment_number} from {$from?->name}"];
                $lines[] = ['account_id' => $ar, 'type' => 'credit', 'amount' => $shares[0]['amount'], 'description' => "{$payment->payment_number} to {$keeper->name}"];
            }

            $journal = $this->journal($payment->company_id, $payment->currency, "Correction: {$payment->payment_number} split between customers", $lines);
            DB::table('acct.payments')->whereIn('id', array_column($created, 'payment_id'))->update(['transaction_id' => $journal]);

            return $this->record($payment->company_id, 'payment', $payment->id, 'split', $reason, [
                'number' => $payment->payment_number,
                'before' => ['customer_id' => $from?->id, 'customer' => $from?->name, 'amount' => $total],
                'after' => ['customer_id' => $keeper->id, 'customer' => $keeper->name, 'amount' => $shares[0]['amount'], 'shares' => $created],
                'unapplied_from' => $unapplied,
            ], $journal);
        });
    }

    /** Every correction made to a record, newest first. */
    public function history(string $companyId, string $entityType, string $entityId): array
    {
        return DB::table('acct.corrections as c')
            ->leftJoin('auth.users as u', 'u.id', '=', 'c.created_by_user_id')
            ->where('c.company_id', $companyId)->where('c.entity_type', $entityType)->where('c.entity_id', $entityId)
            ->orderByDesc('c.created_at')
            ->get(['c.id', 'c.correction_number', 'c.action', 'c.reason', 'c.changes', 'c.transaction_id', 'c.created_at', 'u.name as by'])
            ->map(fn ($c) => [...(array) $c, 'changes' => json_decode($c->changes, true)])
            ->all();
    }

    // ------------------------------------------------------------------------------------------

    private function customer(string $companyId, string $id): Customer
    {
        $customer = Customer::where('company_id', $companyId)->find($id);
        if (! $customer) {
            throw ValidationException::withMessages(['customer_id' => 'Not a customer of this company.']);
        }

        return $customer;
    }

    /**
     * Takes other customers' payments off an invoice, back to their on-account credit. An empty
     * $keeperId takes every payment off.
     */
    private function unapplyForeignPayments(Invoice $invoice, string $keeperId): array
    {
        $done = [];
        $allocations = PaymentAllocation::where('company_id', $invoice->company_id)->where('invoice_id', $invoice->id)->get();
        foreach ($allocations as $allocation) {
            $payment = Payment::find($allocation->payment_id);
            if (! $payment || $payment->customer_id === $keeperId) {
                continue;
            }
            // Fresh each time: the previous allocation already moved paid_amount.
            $this->moveAllocationOnAccount($allocation, Invoice::find($invoice->id));
            $done[] = ['payment' => $payment->payment_number, 'amount' => round((float) $allocation->amount_allocated, 2)];
        }

        return $done;
    }

    /** Takes a payment off every invoice it paid; the money stays with the payment, on account. */
    private function unapplyPayment(Payment $payment): array
    {
        $done = [];
        foreach (PaymentAllocation::where('payment_id', $payment->id)->whereNotNull('invoice_id')->get() as $allocation) {
            $invoice = Invoice::find($allocation->invoice_id);
            $this->moveAllocationOnAccount($allocation, $invoice);
            $done[] = ['invoice' => $invoice?->invoice_number, 'amount' => round((float) $allocation->amount_allocated, 2)];
        }

        return $done;
    }

    private function moveAllocationOnAccount(PaymentAllocation $allocation, ?Invoice $invoice): void
    {
        $amount = (float) $allocation->amount_allocated;
        if ($invoice) {
            $paid = max(0.0, round((float) $invoice->paid_amount - $amount, 6));
            $balance = round((float) $invoice->total_amount - $paid, 6);
            DB::table('acct.invoices')->where('id', $invoice->id)->update([
                'paid_amount' => $paid,
                'balance' => $balance,
                'status' => $paid <= 0.000001 ? 'sent' : ($balance <= 0.000001 ? 'paid' : 'partial'),
                'paid_at' => $balance <= 0.000001 ? $invoice->paid_at : null,
                'updated_at' => now(),
            ]);
        }
        $allocation->update(['invoice_id' => null]);
    }

    /** A payment's unapplied money, as the null-invoice allocation row the app reads it from. */
    private function onAccount(Payment $source, string $paymentId, float $amount, float $rate): void
    {
        PaymentAllocation::create([
            'company_id' => $source->company_id,
            'payment_id' => $paymentId,
            'invoice_id' => null,
            'amount_allocated' => $amount,
            'base_amount_allocated' => round($amount * $rate, 2),
            'applied_at' => now(),
        ]);
    }

    private function applyOldestFirst(Payment $payment, Customer $customer): void
    {
        $left = round((float) PaymentAllocation::where('payment_id', $payment->id)->whereNull('invoice_id')->sum('amount_allocated'), 2);
        $open = Invoice::where('company_id', $payment->company_id)->where('customer_id', $customer->id)
            ->whereNotIn('status', ['draft', 'void', 'cancelled', 'paid'])->where('balance', '>', 0)
            ->orderBy('invoice_date')->orderBy('created_at')->get();
        foreach ($open as $invoice) {
            if ($left <= 0.005) {
                break;
            }
            $amount = round(min($left, (float) $invoice->balance), 2);
            app(CommandBus::class)->dispatch('payment.apply_credit', [
                'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'amount' => $amount,
            ], Auth::user(), true);
            $left = round($left - $amount, 2);
        }
    }

    private function splitInvoice(Invoice $original, Customer $customer, array $share): Invoice
    {
        $amount = $share['amount'];
        // With litres the share keeps the sale's own litres x rate; without, one line of the amount.
        $quantity = $share['quantity'] ?? 1;
        $unitPrice = $share['quantity'] ? round($amount / $share['quantity'], 6) : $amount;
        $invoice = Invoice::create([
            'company_id' => $original->company_id,
            'customer_id' => $customer->id,
            'invoice_number' => Invoice::generateInvoiceNumber($original->company_id),
            'invoice_date' => $original->invoice_date,
            'due_date' => $original->due_date ?? $original->invoice_date,
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'balance' => $amount,
            'currency' => $original->currency,
            'base_currency' => $original->base_currency,
            'exchange_rate' => $original->exchange_rate,
            'base_amount' => $amount,
            'payment_terms' => $original->payment_terms,
            'status' => 'sent',
            'sent_at' => now(),
            'reference' => $share['reference'] ?? $original->reference,
            'unit_id' => $share['unit_id'] ?? null,
            'notes' => $original->notes,
            'internal_notes' => "Split from {$original->invoice_number}",
            'created_by_user_id' => Auth::id(),
        ]);
        $line = InvoiceLineItem::where('invoice_id', $original->id)->orderBy('line_number')->first();
        InvoiceLineItem::create([
            'company_id' => $original->company_id,
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'description' => ($line?->description ?? 'Sale')." (share of {$original->invoice_number})",
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'tax_rate' => 0,
            'discount_rate' => 0,
            'line_total' => $amount,
            'tax_amount' => 0,
            'total' => $amount,
            'income_account_id' => $line?->income_account_id,
            'item_id' => $line?->item_id,
            'created_by_user_id' => Auth::id(),
        ]);

        return $invoice;
    }

    private function splitCredit(Invoice $original, Customer $keeper, float $amount, string $newNumber): CreditNote
    {
        $credit = CreditNote::create([
            'company_id' => $original->company_id,
            'customer_id' => $keeper->id,
            'invoice_id' => $original->id,
            'credit_note_number' => CreditNote::generateCreditNoteNumber($original->company_id),
            'credit_date' => $original->invoice_date,
            'amount' => $amount,
            'currency' => $original->currency,
            'base_currency' => $original->base_currency,
            'base_amount' => $amount,
            'reason' => "Moved to {$newNumber}",
            'status' => 'applied',
            'posted_at' => now(),
            'created_by_user_id' => Auth::id(),
        ]);
        $fresh = Invoice::find($original->id);
        $after = round((float) $fresh->balance - $amount, 6);
        CreditNoteApplication::create([
            'company_id' => $original->company_id,
            'credit_note_id' => $credit->id,
            'invoice_id' => $original->id,
            'amount_applied' => $amount,
            'applied_at' => now(),
            'invoice_balance_before' => $fresh->balance,
            'invoice_balance_after' => $after,
            'user_id' => Auth::id(),
            'notes' => 'Correction: share moved to '.$newNumber,
        ]);
        DB::table('acct.invoices')->where('id', $original->id)->update([
            'paid_amount' => round((float) $fresh->total_amount - $after, 6),
            'balance' => $after,
            'status' => $after <= 0.000001 ? 'paid' : 'partial',
            'updated_at' => now(),
        ]);

        return $credit;
    }

    /** The receivables account the invoice was posted to. */
    private function receivablesAccount(Invoice $invoice): string
    {
        if ($invoice->transaction_id) {
            $line = DB::table('acct.journal_entries')->where('transaction_id', $invoice->transaction_id)
                ->where('debit_amount', '>', 0)
                ->where(fn ($q) => $q->where('description', 'ilike', '%'.$invoice->invoice_number.'%')->orWhere('description', 'ilike', 'Accounts Receivable%'))
                ->value('account_id');
            if ($line) {
                return $line;
            }
        }

        return $this->fallbackReceivables($invoice->company_id, $invoice->customer_id);
    }

    private function paymentReceivablesAccount(Payment $payment): string
    {
        $tx = $payment->transaction_id ?? null;
        if ($tx) {
            $deposit = $payment->deposit_account_id ?? null;
            $line = DB::table('acct.journal_entries')->where('transaction_id', $tx)->where('credit_amount', '>', 0)
                ->when($deposit, fn ($q) => $q->where('account_id', '!=', $deposit))->value('account_id');
            if ($line) {
                return $line;
            }
        }

        return $this->fallbackReceivables($payment->company_id, $payment->customer_id);
    }

    private function fallbackReceivables(string $companyId, ?string $customerId): string
    {
        $id = $customerId ? Customer::where('id', $customerId)->value('ar_account_id') : null;
        $id ??= DB::table('acct.accounts')->where('company_id', $companyId)->where('code', '1100')->value('id');
        if (! $id) {
            throw ValidationException::withMessages(['customer_id' => 'No receivables account to post the correction to.']);
        }

        return $id;
    }

}
