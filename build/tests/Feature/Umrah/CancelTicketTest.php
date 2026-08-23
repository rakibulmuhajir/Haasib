<?php

use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Umrah\Commands\CancelTicket;
use App\Modules\Umrah\Models\Ticket;
use App\Modules\Umrah\Models\TicketBooking;
use App\Modules\Umrah\Models\TicketCancellation;
use Illuminate\Support\Facades\Bus;

require_once __DIR__.'/TicketingFixtures.php';

it('credits the buyer and debits the supplier', function () {
    $f = ticketingSoldTicket();

    $cancellation = Bus::dispatch(new CancelTicket(
        ticketId: $f->ticket->id,
        cancellationDate: '2026-09-05',
        buyerReturnsAmount: 80_000,
        supplierReturnsAmount: 85_000,
        reason: 'Passenger withdrew',
        idempotencyKey: 'cancel-1',
    ));

    expect($cancellation->buyerCreditNote->amount)->toEqual(80_000.00)
        ->and($cancellation->supplierVendorCredit->amount)->toEqual(85_000.00)
        ->and($cancellation->costBase())->toBe(-5_000.0)
        ->and(ticketingAccountBalance($f->company, '4160'))->toBe(-5_000.0);
});

it('raises no credit note when the buyer got nothing back', function () {
    $f = ticketingSoldTicket();

    $cancellation = Bus::dispatch(new CancelTicket(
        ticketId: $f->ticket->id,
        cancellationDate: '2026-09-05',
        buyerReturnsAmount: 0,
        supplierReturnsAmount: 85_000,
        reason: 'No-show, fully forfeit',
        idempotencyKey: 'cancel-2',
    ));

    expect($cancellation->buyer_credit_note_id)->toBeNull()
        ->and($cancellation->supplierVendorCredit)->not->toBeNull();
});

it('succeeds against a fully paid invoice and leaves the credit unapplied', function () {
    $f = ticketingSoldTicket();
    ticketingPayInFull($f->invoice);

    // Applying zero to a settled invoice is not an error. The whole
    // credit stays available for the buyer's next booking.
    $cancellation = Bus::dispatch(new CancelTicket(
        ticketId: $f->ticket->id,
        cancellationDate: '2026-09-05',
        buyerReturnsAmount: 80_000,
        supplierReturnsAmount: 85_000,
        reason: 'Cancelled after payment',
        idempotencyKey: 'cancel-3',
    ));

    expect($cancellation->buyerCreditNote->balance)->toEqual(80_000.00)
        ->and($f->invoice->fresh()->status)->toBe('paid');
});

it('applies only what the invoice still owes', function () {
    $f = ticketingSoldTicket();
    ticketingPayPartial($f->invoice, 90_000);      // 6,900 left outstanding

    $cancellation = Bus::dispatch(new CancelTicket(
        ticketId: $f->ticket->id,
        cancellationDate: '2026-09-05',
        buyerReturnsAmount: 80_000,
        supplierReturnsAmount: 85_000,
        reason: 'Cancelled part-paid',
        idempotencyKey: 'cancel-4',
    ));

    expect($f->invoice->fresh()->balance)->toEqual(0.00)
        ->and($cancellation->buyerCreditNote->balance)->toEqual(73_100.00);
});

it('marks the ticket and its booking cancelled', function () {
    $f = ticketingSoldTicket();

    Bus::dispatch(new CancelTicket($f->ticket->id, '2026-09-05', 80_000, 85_000, null, 'cancel-5'));

    expect($f->ticket->fresh()->status)->toBe('cancelled')
        ->and($f->booking->fresh()->status)->toBe('cancelled');
});

it('cancels once for a repeated key', function () {
    $f = ticketingSoldTicket();
    $command = new CancelTicket($f->ticket->id, '2026-09-05', 80_000, 85_000, null, 'cancel-6');

    $first = Bus::dispatch($command);
    $second = Bus::dispatch(new CancelTicket($f->ticket->id, '2026-09-05', 80_000, 85_000, null, 'cancel-6'));

    expect($second->id)->toBe($first->id)
        ->and(TicketCancellation::count())->toBe(1);
});

it('refuses a ticket that is already cancelled under a different key', function () {
    $f = ticketingSoldTicket();
    Bus::dispatch(new CancelTicket($f->ticket->id, '2026-09-05', 80_000, 85_000, null, 'cancel-7a'));

    expect(fn () => Bus::dispatch(new CancelTicket($f->ticket->id, '2026-09-06', 10_000, 10_000, null, 'cancel-7b')))
        ->toThrow(\RuntimeException::class, 'already been cancelled');

    expect(TicketCancellation::count())->toBe(1);
});

it('writes nothing at all when the supplier side cannot be posted', function () {
    $f = ticketingSoldTicketWithoutVendorCreditTemplate();

    expect(fn () => Bus::dispatch(new CancelTicket(
        ticketId: $f->ticket->id,
        cancellationDate: '2026-09-05',
        buyerReturnsAmount: 80_000,
        supplierReturnsAmount: 85_000,
        reason: null,
        idempotencyKey: 'cancel-8',
    )))->toThrow(\RuntimeException::class);

    expect(TicketCancellation::count())->toBe(0)
        ->and(CreditNote::count())->toBe(0)
        ->and($f->ticket->fresh()->status)->toBe('issued')
        ->and($f->booking->fresh()->status)->toBe('confirmed')
        ->and($f->invoice->fresh()->balance)->toEqual($f->invoice->total_amount)
        ->and(ticketingAccountBalance($f->company, '4160'))->toBe(0.0);
});
