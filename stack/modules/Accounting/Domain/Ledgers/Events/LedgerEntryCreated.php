<?php

namespace Modules\Accounting\Domain\Ledgers\Events;

use Illuminate\Foundation\Events\Dispatchable;

class LedgerEntryCreated
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public array $data
    ) {
        $this->data = $data;
    }

    /**
     * Get the event data.
     */
    public function getData(): array
    {
        return $this->data;
    }
}