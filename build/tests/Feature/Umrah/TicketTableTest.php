<?php

use App\Modules\Umrah\Models\Ticket;

require_once __DIR__.'/TicketingFixtures.php';

it('derives commission from fare and cost rather than storing it', function () {
    $ticket = ticketingTicket([
        'gross_fare_base' => 85_000,
        'taxes_base' => 12_400,
        'supplier_cost_base' => 91_000,
    ]);

    // (85,000 + 12,400) - 91,000
    expect($ticket->commissionBase())->toBe(6_400.0);
});

it('allows a negative commission when the fare undercuts the cost', function () {
    $ticket = ticketingTicket([
        'gross_fare_base' => 80_000,
        'taxes_base' => 5_000,
        'supplier_cost_base' => 91_000,
    ]);

    expect($ticket->commissionBase())->toBe(-6_000.0);
});

it('keeps the passenger name even when the passenger record goes', function () {
    $ticket = ticketingTicket(['passenger_id' => null, 'passenger_name' => 'Fatima Bibi']);

    expect($ticket->passenger_id)->toBeNull()
        ->and($ticket->passenger_name)->toBe('Fatima Bibi');
});

it('refuses two tickets on one airline ticket number', function () {
    ticketingTicket(['airline_ticket_number' => '214-1234567890']);

    expect(fn () => ticketingTicket(['airline_ticket_number' => '214-1234567890']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows many tickets with no airline number yet', function () {
    ticketingTicket(['airline_ticket_number' => null]);
    ticketingTicket(['airline_ticket_number' => null]);

    expect(Ticket::whereNull('airline_ticket_number')->count())->toBe(2);
});
