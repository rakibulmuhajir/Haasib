<?php

namespace App\Modules\Umrah\Handlers;

use App\Models\Company;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\CreditNoteApplication;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Models\VendorCreditApplication;
use App\Modules\Accounting\Services\TicketPostingService;
use App\Modules\Umrah\Commands\CancelTicket;
use App\Modules\Umrah\Models\Ticket;
use App\Modules\Umrah\Models\TicketBooking;
use App\Modules\Umrah\Models\TicketCancellation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors CreateTicketBookingHandler's shape: idempotency lookup by
 * (company_id, idempotency_key) before any write, then everything --
 * both credit documents, both applications, the ticket and booking
 * status, and the cancellation row itself -- inside one transaction.
 *
 * The sign convention that matters: postTicketCreditNote() debits 4160
 * and credits AR for the buyer return; postTicketVendorCredit() debits
 * AP and credits 4160 for the supplier return. Net, 4160 carries
 * buyer_returns_base - supplier_returns_base, exactly what
 * TicketCancellation::costBase() computes -- the ledger and the model
 * agree because both read the same two numbers the same way.
 */
final class CancelTicketHandler
{
    public function __construct(private readonly TicketPostingService $postingService) {}

    public function handle(CancelTicket $command): TicketCancellation
    {
        $ticket = Ticket::find($command->ticketId);

        if (! $ticket) {
            throw new \InvalidArgumentException('Ticket not found.');
        }

        $companyId = $ticket->company_id;

        // Idempotency comes first, before any write, and outside the
        // transaction: a replay must not even open one.
        $existing = TicketCancellation::where('company_id', $companyId)
            ->where('idempotency_key', $command->idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        // The database backs this too (ticket_id is unique on
        // umrah.ticket_cancellations), but a constraint violation is not
        // a sentence a person can read.
        if (TicketCancellation::where('ticket_id', $ticket->id)->exists()) {
            throw new \RuntimeException('This ticket has already been cancelled.');
        }

        if ($command->buyerReturnsAmount <= 0.0 && $command->supplierReturnsAmount <= 0.0) {
            throw new \InvalidArgumentException('A cancellation must return something to the buyer, the supplier, or both.');
        }

        return DB::transaction(function () use ($command, $ticket) {
            $booking = TicketBooking::find($ticket->ticket_booking_id);

            if (! $booking instanceof TicketBooking) {
                throw new \RuntimeException('Ticket booking missing.');
            }

            $company = Company::findOrFail($ticket->company_id);
            $invoice = Invoice::findOrFail($booking->invoice_id);
            $bill = Bill::findOrFail($booking->bill_id);

            $saleIsBase = $ticket->sale_currency === $company->base_currency;
            $supplierIsBase = $ticket->supplier_currency === $company->base_currency;

            $buyerReturnsBase = $saleIsBase
                ? round($command->buyerReturnsAmount, 2)
                : round($command->buyerReturnsAmount * (float) $ticket->sale_exchange_rate, 2);

            $supplierReturnsBase = $supplierIsBase
                ? round($command->supplierReturnsAmount, 2)
                : round($command->supplierReturnsAmount * (float) $ticket->supplier_exchange_rate, 2);

            $buyerCreditNoteId = null;
            $supplierVendorCreditId = null;

            if ($command->buyerReturnsAmount > 0.0) {
                $buyerCreditNoteId = $this->raiseBuyerCreditNote(
                    $company, $booking, $invoice, $ticket, $command, $saleIsBase, $buyerReturnsBase,
                );
            }

            if ($command->supplierReturnsAmount > 0.0) {
                $supplierVendorCreditId = $this->raiseSupplierVendorCredit(
                    $company, $booking, $bill, $ticket, $command, $supplierIsBase, $supplierReturnsBase,
                );
            }

            $ticket->update(['status' => 'cancelled']);

            $remainingLive = Ticket::where('ticket_booking_id', $booking->id)
                ->where('status', '!=', 'cancelled')
                ->count();

            if ($remainingLive === 0) {
                $booking->update(['status' => 'cancelled']);
            }

            $cancellation = TicketCancellation::create([
                'company_id' => $company->id,
                'ticket_id' => $ticket->id,
                'cancellation_date' => $command->cancellationDate,
                'supplier_returns_currency' => $ticket->supplier_currency,
                'supplier_returns_exchange_rate' => $supplierIsBase ? null : $ticket->supplier_exchange_rate,
                'supplier_returns_amount' => round($command->supplierReturnsAmount, 6),
                'supplier_returns_base' => $supplierReturnsBase,
                'buyer_returns_currency' => $ticket->sale_currency,
                'buyer_returns_exchange_rate' => $saleIsBase ? null : $ticket->sale_exchange_rate,
                'buyer_returns_amount' => round($command->buyerReturnsAmount, 6),
                'buyer_returns_base' => $buyerReturnsBase,
                'base_currency' => $company->base_currency,
                'buyer_credit_note_id' => $buyerCreditNoteId,
                'supplier_vendor_credit_id' => $supplierVendorCreditId,
                'idempotency_key' => $command->idempotencyKey,
                'reason' => $command->reason,
            ]);

            return $cancellation->fresh(['buyerCreditNote', 'supplierVendorCredit']);
        });
    }

    /**
     * The invoice is always denominated in the company's base currency
     * (CreateTicketBookingHandler::createInvoice() docblock), so the
     * credit note is applied to it in base -- min(return, current
     * balance) -- never in the ticket's own sale currency.
     */
    private function raiseBuyerCreditNote(
        Company $company,
        TicketBooking $booking,
        Invoice $invoice,
        Ticket $ticket,
        CancelTicket $command,
        bool $saleIsBase,
        float $buyerReturnsBase,
    ): string {
        $creditNote = CreditNote::create([
            'company_id' => $company->id,
            'customer_id' => $booking->customer_id,
            'invoice_id' => $invoice->id,
            'credit_note_number' => CreditNote::generateCreditNoteNumber($company->id),
            'credit_date' => $command->cancellationDate,
            'amount' => round($command->buyerReturnsAmount, 2),
            'currency' => $ticket->sale_currency,
            'exchange_rate' => $saleIsBase ? null : $ticket->sale_exchange_rate,
            'base_currency' => $company->base_currency,
            'base_amount' => $buyerReturnsBase,
            'reason' => $command->reason ?? 'Ticket cancellation',
            'status' => 'issued',
        ]);

        $this->postingService->postTicketCreditNote($creditNote, $buyerReturnsBase);

        $applied = min($buyerReturnsBase, (float) $invoice->balance);

        // Bypasses CreditNote\ApplyAction on purpose: it throws on a
        // paid/void/cancelled invoice and on amount 0, both of which are
        // the correct, non-error outcome here -- a fully paid invoice
        // applies zero and leaves the whole credit available. The
        // allocation is written directly instead of reusing the palette
        // action's guard.
        if ($applied > 0.0) {
            $before = (float) $invoice->balance;
            $after = round(max(0.0, $before - $applied), 6);

            CreditNoteApplication::create([
                'company_id' => $company->id,
                'credit_note_id' => $creditNote->id,
                'invoice_id' => $invoice->id,
                'amount_applied' => $applied,
                'invoice_balance_before' => $before,
                'invoice_balance_after' => $after,
                'applied_at' => $command->cancellationDate,
                'user_id' => Auth::id(),
                'notes' => 'Applied from ticket cancellation',
            ]);

            $invoice->update([
                'paid_amount' => round((float) $invoice->total_amount - $after, 6),
                'balance' => $after,
                'status' => $after <= 0 ? 'paid' : 'partial',
                'paid_at' => $after <= 0 ? now() : null,
            ]);

            // Mirrors CreditNote\ApplyAction's status transition, which the
            // direct write below bypasses otherwise -- a fully-consumed
            // credit must read 'applied', not sit at 'issued' forever.
            $totalApplied = (float) CreditNoteApplication::where('credit_note_id', $creditNote->id)->sum('amount_applied');
            if ($totalApplied >= (float) $creditNote->amount) {
                $creditNote->update(['status' => 'applied']);
            } elseif ($creditNote->status === 'draft') {
                $creditNote->update(['status' => 'issued']);
            }
        }

        return $creditNote->id;
    }

    /**
     * The bill keeps its own (possibly foreign) currency
     * (CreateTicketBookingHandler::createBill()), so the vendor credit
     * is applied to it in that same transaction currency, not base.
     */
    private function raiseSupplierVendorCredit(
        Company $company,
        TicketBooking $booking,
        Bill $bill,
        Ticket $ticket,
        CancelTicket $command,
        bool $supplierIsBase,
        float $supplierReturnsBase,
    ): string {
        $vendorCredit = VendorCredit::create([
            'company_id' => $company->id,
            'vendor_id' => $booking->supplier_vendor_id,
            'bill_id' => $bill->id,
            'credit_number' => $this->nextVendorCreditNumber($company->id),
            'credit_date' => $command->cancellationDate,
            'amount' => round($command->supplierReturnsAmount, 6),
            'currency' => $ticket->supplier_currency,
            'exchange_rate' => $supplierIsBase ? null : $ticket->supplier_exchange_rate,
            'base_currency' => $company->base_currency,
            'base_amount' => $supplierReturnsBase,
            'reason' => $command->reason ?? 'Ticket cancellation',
            'status' => 'issued',
        ]);

        $this->postingService->postTicketVendorCredit($vendorCredit, $supplierReturnsBase);

        $applied = min((float) $command->supplierReturnsAmount, (float) $bill->balance);

        if ($applied > 0.0) {
            $before = (float) $bill->balance;
            $after = round(max(0.0, $before - $applied), 6);

            VendorCreditApplication::create([
                'company_id' => $company->id,
                'vendor_credit_id' => $vendorCredit->id,
                'bill_id' => $bill->id,
                'amount_applied' => $applied,
                'bill_balance_before' => $before,
                'bill_balance_after' => $after,
                'applied_at' => $command->cancellationDate,
                'user_id' => Auth::id(),
            ]);

            $bill->update([
                'paid_amount' => round((float) $bill->total_amount - $after, 6),
                'balance' => $after,
                'status' => $after <= 0 ? 'paid' : 'partial',
            ]);

            // Mirrors VendorCredit\ApplyAction's status transition -- unlike
            // the credit-note action, it has no draft-to-issued branch, so
            // neither does this: the vendor credit is created 'issued'
            // already and only ever advances to 'applied' from here.
            $totalApplied = (float) VendorCreditApplication::where('vendor_credit_id', $vendorCredit->id)->sum('amount_applied');
            if ($totalApplied >= (float) $vendorCredit->amount) {
                $vendorCredit->update(['status' => 'applied']);
            }
        }

        return $vendorCredit->id;
    }

    private function nextVendorCreditNumber(string $companyId): string
    {
        $last = VendorCredit::where('company_id', $companyId)
            ->whereNotNull('credit_number')
            ->where('credit_number', 'like', 'TICKVC-%')
            ->lockForUpdate()
            ->orderByDesc('credit_number')
            ->value('credit_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return 'TICKVC-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
