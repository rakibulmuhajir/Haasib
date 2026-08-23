<?php

use App\Modules\Umrah\Models\TicketBooking;
use Illuminate\Database\QueryException;

require_once __DIR__.'/TicketingFixtures.php';

it('stores a booking with both its documents', function () {
    $f = ticketingBookingFixture();

    $booking = TicketBooking::create([
        'company_id' => $f->company->id,
        'customer_id' => $f->customer->id,
        'agent_id' => $f->agent->id,
        'supplier_vendor_id' => $f->vendor->id,
        'invoice_id' => $f->invoice->id,
        'bill_id' => $f->bill->id,
        'booking_reference' => 'TB-00001',
        'pnr' => 'X4K9QZ',
        'booking_date' => '2026-09-01',
        'status' => 'confirmed',
        'idempotency_key' => 'test-key-1',
    ]);

    expect($booking->fresh()->pnr)->toBe('X4K9QZ');
});

it('refuses a booking with no supplier bill', function () {
    $f = ticketingBookingFixture();

    // A bill raised later would be converted at a different rate and
    // leave a residual in the clearing account that nothing closes.
    expect(fn () => TicketBooking::create(ticketingBookingAttributes($f, ['bill_id' => null])))
        ->toThrow(QueryException::class);
});

it('refuses a booking with no buyer', function () {
    $f = ticketingBookingFixture();

    expect(fn () => TicketBooking::create(ticketingBookingAttributes($f, ['customer_id' => null])))
        ->toThrow(QueryException::class);
});

it('refuses a second booking on the same idempotency key', function () {
    $f = ticketingBookingFixture();
    TicketBooking::create(ticketingBookingAttributes($f));

    expect(fn () => TicketBooking::create(ticketingBookingAttributes($f, [
        'booking_reference' => 'TB-00002',
        'invoice_id' => $f->secondInvoice->id,
        'bill_id' => $f->secondBill->id,
    ])))->toThrow(QueryException::class);
});

it('refuses two bookings against one invoice', function () {
    $f = ticketingBookingFixture();
    TicketBooking::create(ticketingBookingAttributes($f));

    expect(fn () => TicketBooking::create(ticketingBookingAttributes($f, [
        'booking_reference' => 'TB-00003',
        'idempotency_key' => 'test-key-2',
        'bill_id' => $f->secondBill->id,
    ])))->toThrow(QueryException::class);
});
