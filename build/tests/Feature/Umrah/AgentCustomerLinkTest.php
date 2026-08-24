<?php

use App\Modules\Accounting\Models\Customer;

require_once __DIR__.'/TicketingFixtures.php';

it('links an agent to the customer who carries the balance', function () {
    $f = ticketingCompany();
    $customer = ticketingCustomer($f->company, ['name' => 'Al-Noor Travels']);
    $agent = ticketingAgent($f->company, ['customer_id' => $customer->id]);

    expect($agent->customer->name)->toBe('Al-Noor Travels');
});

it('gives an agent created with only a name the customer that name belongs to', function () {
    $f = ticketingCompany();

    // No customer_id was passed. There is still one, because an agent is a
    // customer with an umrah profile attached and cannot be created without
    // the party it extends -- which is what "The customer id field is
    // required." was really reporting when the booking form asked for it.
    $agent = ticketingAgent($f->company, ['name' => 'Al-Noor Travels']);

    expect($agent->customer_id)->not->toBeNull()
        ->and($agent->customer->name)->toBe('Al-Noor Travels')
        ->and($agent->customer->customer_type)->toBe(Customer::TYPE_AGENT)
        ->and($agent->name)->toBe('Al-Noor Travels');
});

it('reuses the customer that already answers to an agent email', function () {
    $f = ticketingCompany();
    $existing = ticketingCustomer($f->company, [
        'name' => 'Al-Noor Travels',
        'email' => 'book@alnoor.pk',
    ]);

    $agent = ticketingAgent($f->company, [
        'name' => 'Al-Noor Travels',
        'email' => 'BOOK@alnoor.pk',
    ]);

    // One party, one ledger. A second row here would split what Al-Noor owes
    // across two statements, and the match is case-insensitive because an
    // email address is.
    expect($agent->customer_id)->toBe($existing->id)
        ->and($existing->fresh()->customer_type)->toBe(Customer::TYPE_AGENT)
        ->and(Customer::where('company_id', $f->company->id)->count())->toBe(1);
});

it('writes an edited agent name through to the customer', function () {
    $f = ticketingCompany();
    $agent = ticketingAgent($f->company, ['name' => 'Al-Noor Travels']);

    $agent->update(['name' => 'Al-Noor Travel Services']);

    expect($agent->customer->fresh()->name)->toBe('Al-Noor Travel Services');
});

it('reaches the customer through the relation', function () {
    $f = ticketingCompany();
    $customer = ticketingCustomer($f->company);
    $agent = ticketingAgent($f->company, ['customer_id' => $customer->id]);

    expect($agent->customer)->toBeInstanceOf(Customer::class)
        ->and($agent->customer->id)->toBe($customer->id);
});
