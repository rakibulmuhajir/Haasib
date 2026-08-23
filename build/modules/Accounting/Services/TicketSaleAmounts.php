<?php

namespace App\Modules\Accounting\Services;

/**
 * Every amount here is already in the company's base currency. Converting
 * from whatever the ticket was actually quoted in is the caller's job,
 * because only the caller knows the ticket's two exchange rates (buyer and
 * supplier legs can each carry their own).
 */
final class TicketSaleAmounts
{
    public function __construct(
        public readonly float $supplierCostBase,
        public readonly float $commissionBase,
        public readonly float $serviceFeeBase,
        public readonly float $discountBase,
    ) {}
}
