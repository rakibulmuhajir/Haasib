<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Handlers\CancelTicketHandler;
use App\Modules\Umrah\Models\TicketCancellation;

/**
 * Cancels one ticket: raises a credit note against the buyer, a vendor
 * credit against the supplier, or both -- never neither -- posts each
 * through TicketPostingService, applies what it can to the invoice and
 * bill, and marks the ticket (and its booking, once nothing on it is
 * still live) cancelled.
 *
 * `buyerReturnsAmount` and `supplierReturnsAmount` are expressed in the
 * ticket's own sale_currency and supplier_currency respectively --
 * mirroring how CreateTicketBooking took each ticket's money in its own
 * two currencies -- and are converted to base using the exchange rates
 * already stored on the ticket row, never a rate re-entered here.
 */
final class CancelTicket
{
    public function __construct(
        public readonly string $ticketId,
        public readonly string $cancellationDate,
        public readonly float $buyerReturnsAmount,
        public readonly float $supplierReturnsAmount,
        public readonly ?string $reason,
        public readonly string $idempotencyKey,
    ) {}

    public function handle(CancelTicketHandler $handler): TicketCancellation
    {
        return $handler->handle($this);
    }
}
