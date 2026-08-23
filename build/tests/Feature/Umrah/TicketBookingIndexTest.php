<?php

require_once __DIR__.'/TicketingFixtures.php';

it('lists every booking for a manager', function () {
    $f = ticketingTwoBookingsForDifferentAgents();

    $this->actingAs($f->manager)
        ->get("/{$f->company->slug}/umrah/tickets")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/Tickets/Index')
            ->has('bookings.data', 2));
});

it('shows an agent only their own bookings', function () {
    $f = ticketingTwoBookingsForDifferentAgents();

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/tickets")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('bookings.data', 1));
});

it('sends no supplier cost to an agent', function () {
    $f = ticketingTwoBookingsForDifferentAgents();

    $this->actingAs($f->agentUser)
        ->get("/{$f->company->slug}/umrah/tickets")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('bookings.data.0.supplier_cost_base')
            ->missing('bookings.data.0.commission_base'));
});

it('turns away a user with no ticket permission at all', function () {
    // A genuine outsider never reaches the controller's permission check:
    // RequireModuleEnabled (already on every umrah route) turns away
    // anyone who is not an active member of the company before the
    // request gets that far, redirecting rather than 403ing. Either way
    // the register itself is never returned to them -- see
    // ticketingTwoBookingsForDifferentAgents() for why no active
    // membership in this app can carry zero ticket permissions.
    $f = ticketingTwoBookingsForDifferentAgents();

    $response = $this->actingAs($f->outsider)
        ->get("/{$f->company->slug}/umrah/tickets");

    $response->assertRedirect();
    expect($response->status())->not->toBe(200);
});
