<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

abstract class InvoiceEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly array $context = []
    ) {}
}
