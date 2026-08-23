<?php

use App\Modules\Accounting\Models\Customer;

require_once __DIR__.'/TicketingFixtures.php';

it('links an agent to the customer who carries the balance', function () {
    $f = ticketingCompany();
    $customer = ticketingCustomer($f->company, ['name' => 'Al-Noor Travels']);
    $agent = ticketingAgent($f->company, ['customer_id' => $customer->id]);

    expect($agent->customer->name)->toBe('Al-Noor Travels');
});

it('leaves existing agents unlinked rather than inventing a customer', function () {
    $f = ticketingCompany();

    // An agent that predates this column has no customer record to
    // point at, and inventing one would put a duplicate party on the
    // books.
    expect(ticketingAgent($f->company)->customer_id)->toBeNull();
});

it('reaches the customer through the relation', function () {
    $f = ticketingCompany();
    $customer = ticketingCustomer($f->company);
    $agent = ticketingAgent($f->company, ['customer_id' => $customer->id]);

    expect($agent->customer)->toBeInstanceOf(Customer::class)
        ->and($agent->customer->id)->toBe($customer->id);
});
