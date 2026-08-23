<?php

require_once __DIR__.'/TicketingFixtures.php';

it('cancels a ticket from the booking page', function () {
    $f = ticketingSoldTicketWithUsers();

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets/{$f->ticket->id}/cancel", [
            'cancellation_date' => '2026-09-05',
            'buyer_returns_amount' => 80_000,
            'supplier_returns_amount' => 85_000,
            'reason' => 'Passenger withdrew',
            'idempotency_key' => 'ui-cancel-1',
        ])
        ->assertRedirect();

    expect($f->ticket->fresh()->status)->toBe('cancelled');
});

it('stops an agent from cancelling', function () {
    $f = ticketingSoldTicketWithUsers();

    $this->actingAs($f->agentUser)
        ->post("/{$f->company->slug}/umrah/tickets/{$f->ticket->id}/cancel", [
            'cancellation_date' => '2026-09-05',
            'buyer_returns_amount' => 80_000,
            'supplier_returns_amount' => 0,
            'idempotency_key' => 'ui-cancel-2',
        ])
        ->assertForbidden();

    expect($f->ticket->fresh()->status)->not->toBe('cancelled');
});

it('refuses a cancellation where neither side returns anything', function () {
    $f = ticketingSoldTicketWithUsers();

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets/{$f->ticket->id}/cancel", [
            'cancellation_date' => '2026-09-05',
            'buyer_returns_amount' => 0,
            'supplier_returns_amount' => 0,
            'idempotency_key' => 'ui-cancel-3',
        ])
        ->assertSessionHasErrors();
});

it('surfaces an already-cancelled ticket as a readable flash, not a 500', function () {
    $f = ticketingSoldTicketWithUsers();

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets/{$f->ticket->id}/cancel", [
            'cancellation_date' => '2026-09-05',
            'buyer_returns_amount' => 80_000,
            'supplier_returns_amount' => 85_000,
            'idempotency_key' => 'ui-cancel-4',
        ])
        ->assertRedirect();

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets/{$f->ticket->id}/cancel", [
            'cancellation_date' => '2026-09-06',
            'buyer_returns_amount' => 10_000,
            'supplier_returns_amount' => 0,
            'idempotency_key' => 'ui-cancel-5',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'This ticket has already been cancelled.');
});

it('shows a manager a booking with its cost columns', function () {
    $f = ticketingSoldTicketWithUsers();

    $this->actingAs($f->manager)
        ->get("/{$f->company->slug}/umrah/tickets/{$f->booking->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/Tickets/Show')
            ->where('booking.id', $f->booking->id)
            ->has('booking.tickets.0.supplier_cost_base')
            ->has('booking.tickets.0.commission_base'));
});

it('shows an agent their own booking without cost columns', function () {
    $f = ticketingSoldTicketWithUsers();

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/tickets/{$f->booking->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('booking.id', $f->booking->id)
            ->missing('booking.tickets.0.supplier_cost_base')
            ->missing('booking.tickets.0.commission_base')
            ->missing('booking.bill'));
});

it('stops an agent reaching another agent\'s booking by guessing its URL', function () {
    $f = ticketingTwoBookingsForDifferentAgents();

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/tickets/{$f->bookingTwo->id}")
        ->assertForbidden();
});
