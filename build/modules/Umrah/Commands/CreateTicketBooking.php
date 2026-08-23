<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Handlers\CreateTicketBookingHandler;
use App\Modules\Umrah\Models\TicketBooking;

/**
 * Sells a ticket as one atomic booking: one buyer invoice, one supplier
 * bill, and their postings, all created inside a single transaction
 * keyed on idempotencyKey.
 *
 * `agentId` is deliberately not derived from `customerId`, and vice versa.
 * The spec (umrah-ticketing-design.md S4) treats "who is billed" and "who
 * sold it" as two different questions that happen to be enforced
 * *consistent* with each other -- "the command enforces
 * booking.customer_id === agent.customer_id; a mismatch is a bug, not a
 * configuration" -- not one derived from the other. So both are inputs:
 * `customerId` is required (every buyer is an acct.customer, always),
 * `agentId` is optional (set only when an agent sold it), and the handler
 * refuses a mismatch rather than silently trusting one over the other.
 */
final class CreateTicketBooking
{
    /**
     * @param  array<int, array{
     *     passenger_name: string,
     *     passenger_id?: ?string,
     *     passport_number?: ?string,
     *     airline?: ?string,
     *     route?: ?string,
     *     travel_date?: ?string,
     *     airline_ticket_number?: ?string,
     *     gross_fare: float,
     *     taxes?: float,
     *     discount?: float,
     *     service_fee?: float,
     *     supplier_cost: float,
     *     sale_currency: string,
     *     sale_exchange_rate?: ?float,
     *     supplier_currency: string,
     *     supplier_exchange_rate?: ?float,
     * }>  $tickets
     */
    public function __construct(
        public readonly string $companyId,
        public readonly string $customerId,
        public readonly string $supplierVendorId,
        public readonly string $bookingDate,
        public readonly ?string $pnr,
        public readonly array $tickets,
        public readonly string $idempotencyKey,
        public readonly ?string $agentId = null,
    ) {}

    public function handle(CreateTicketBookingHandler $handler): TicketBooking
    {
        return $handler->handle($this);
    }
}
