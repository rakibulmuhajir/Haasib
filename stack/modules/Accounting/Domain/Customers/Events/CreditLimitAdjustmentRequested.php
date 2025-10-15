<?php

namespace Modules\Accounting\Domain\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerCreditLimit;

class CreditLimitAdjustmentRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CustomerCreditLimit $creditLimit,
        public readonly Customer $customer,
        public readonly array $options = []
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [];
    }
}
