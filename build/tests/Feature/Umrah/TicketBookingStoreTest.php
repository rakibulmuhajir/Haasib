<?php

require_once __DIR__.'/TicketingFixtures.php';

it('creates a booking from the form', function () {
    $f = ticketingFormContext();

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets", ticketingFormPayload($f))
        ->assertRedirect();

    expect(\App\Modules\Umrah\Models\TicketBooking::count())->toBe(1);
});

it('does not create a second booking when the form is submitted twice', function () {
    $f = ticketingFormContext();
    $payload = ticketingFormPayload($f);       // carries one idempotency_key

    $this->actingAs($f->manager)->post("/{$f->company->slug}/umrah/tickets", $payload);
    $this->actingAs($f->manager)->post("/{$f->company->slug}/umrah/tickets", $payload);

    expect(\App\Modules\Umrah\Models\TicketBooking::count())->toBe(1);
});

it('returns a field error rather than a toast for a missing passenger name', function () {
    $f = ticketingFormContext();
    $payload = ticketingFormPayload($f);
    $payload['tickets'][0]['passenger_name'] = '';

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets", $payload)
        ->assertSessionHasErrors('tickets.0.passenger_name');
});

it('stops an agent from creating a booking', function () {
    $f = ticketingFormContext();

    $this->actingAs($f->agentUser)
        ->post("/{$f->company->slug}/umrah/tickets", ticketingFormPayload($f))
        ->assertForbidden();
});

it('rejects a booking whose agent is linked to a different customer', function () {
    $f = ticketingFormContext();
    $otherCustomer = ticketingCustomer($f->company);
    $payload = ticketingFormPayload($f, ['customer_id' => $otherCustomer->id]);

    $this->actingAs($f->manager)
        ->post("/{$f->company->slug}/umrah/tickets", $payload)
        ->assertSessionHasErrors('agent_id');

    expect(\App\Modules\Umrah\Models\TicketBooking::count())->toBe(0);
});
