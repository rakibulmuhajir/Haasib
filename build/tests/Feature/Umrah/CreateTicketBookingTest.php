<?php

use App\Modules\Umrah\Models\TicketBooking;
use Illuminate\Support\Facades\Bus;

require_once __DIR__.'/TicketingFixtures.php';

it('raises both documents and leaves clearing at zero', function () {
    $f = ticketingBookingContext();

    $booking = Bus::dispatch(ticketingBookingCommand($f));

    expect($booking->invoice->total_amount)->toEqual(96_900.00)
        ->and($booking->bill->total_amount)->toEqual(91_000.00)
        ->and(ticketingAccountBalance($f->company, '2350'))->toBe(0.0);
});

it('puts one line on the buyer invoice and no supplier cost anywhere on it', function () {
    $f = ticketingBookingContext();

    $booking = Bus::dispatch(ticketingBookingCommand($f));
    $invoice = $booking->invoice;

    expect($invoice->lineItems)->toHaveCount(1)
        ->and($invoice->lineItems->first()->line_total)->toEqual(98_900.00)
        ->and($invoice->discount_amount)->toEqual(2_000.00);

    // The supplier's price must not be reconstructable from the document
    // the agent receives.
    $printed = $invoice->lineItems->pluck('description')->join(' ')
        .' '.$invoice->lineItems->pluck('line_total')->join(' ');
    expect($printed)->not->toContain('91000')
        ->and($printed)->not->toContain('91,000');
});

it('returns the same booking for a repeated key rather than a second one', function () {
    $f = ticketingBookingContext();
    $command = ticketingBookingCommand($f);

    $first = Bus::dispatch($command);
    $second = Bus::dispatch(ticketingBookingCommand($f));   // same key

    expect($second->id)->toBe($first->id)
        ->and(TicketBooking::count())->toBe(1);
});

it('writes nothing at all when the bill cannot be raised', function () {
    $f = ticketingBookingContext();
    ticketingBreakTheSupplierVendor($f);   // deactivate it

    // Pest's toThrow() checks class_exists($exception); \Throwable is an
    // interface, so class_exists('Throwable') is false and Pest silently
    // falls back to a *substring* match against the exception message
    // instead of an instanceof check. \Exception::class is a real class
    // (RuntimeException extends it) and preserves "any exception aborts
    // the transaction" while actually doing an instanceof check.
    expect(fn () => Bus::dispatch(ticketingBookingCommand($f)))->toThrow(\Exception::class);

    expect(TicketBooking::count())->toBe(0)
        ->and(ticketingAccountBalance($f->company, '2350'))->toBe(0.0)
        ->and(ticketingAccountBalance($f->company, '1100'))->toBe(0.0);
});

it('refuses an agent whose customer is not the buyer on the booking', function () {
    $f = ticketingBookingContext();
    $other = ticketingCustomer($f->company);

    expect(fn () => Bus::dispatch(ticketingBookingCommand($f, ['customerId' => $other->id])))
        ->toThrow(\InvalidArgumentException::class);
});

it('converts each side at its own rate', function () {
    $f = ticketingBookingContext();

    // Sale in PKR at base; supplier in USD at 280.
    $booking = Bus::dispatch(ticketingBookingCommand($f, [
        'tickets' => [ticketingUsdSupplierTicket()],
    ]));

    $ticket = $booking->tickets->first();
    expect($ticket->supplier_cost)->toEqual(325.00)
        ->and($ticket->supplier_exchange_rate)->toEqual(280.00000000)
        ->and($ticket->supplier_cost_base)->toEqual(91_000.00);
});
