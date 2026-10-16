<?php

namespace App\Modules\Accounting\Events;

use App\Modules\Accounting\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired inside VoidAction's transaction, before the invoice row is marked void.
 * Listeners run synchronously; a throw rolls the void back. Never make a
 * listener queued or ShouldDispatchAfterCommit.
 */
class InvoiceVoided
{
    use Dispatchable;

    public function __construct(public Invoice $invoice, public string $reversalDate, public ?string $userId) {}
}
