<?php

require_once __DIR__.'/TicketingFixtures.php';

it('costs the difference between what the buyer got back and what the supplier returned', function () {
    $cancellation = ticketingCancellation([
        'buyer_returns_base' => 85_000,
        'supplier_returns_base' => 80_000,
    ]);

    // The company gave back 5,000 more than it got back.
    expect($cancellation->costBase())->toBe(5_000.0);
});

it('shows a negative cost when the cancellation made money', function () {
    $cancellation = ticketingCancellation([
        'buyer_returns_base' => 80_000,
        'supplier_returns_base' => 85_000,
    ]);

    expect($cancellation->costBase())->toBe(-5_000.0);
});

it('cancels a ticket only once', function () {
    $cancellation = ticketingCancellation();

    expect(fn () => ticketingCancellation(['ticket_id' => $cancellation->ticket_id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows a cancellation with no credit note when the buyer got nothing back', function () {
    $cancellation = ticketingCancellation([
        'buyer_returns_base' => 0,
        'buyer_credit_note_id' => null,
    ]);

    expect($cancellation->buyer_credit_note_id)->toBeNull();
});
