<?php

namespace App\Modules\Accounting\Extensions;

use App\Modules\Accounting\Models\Invoice;
use App\Support\Extensions\HasHooks;

/**
 * Lets a module veto a change to an invoice it has built on (e.g. a fuel daily
 * close's credit sale). Hooks throw to block; $operation is 'save', 'delete',
 * 'update' or 'void'.
 */
class InvoiceMutationGuard
{
    use HasHooks;

    public function assert(Invoice $invoice, string $operation): void
    {
        foreach (self::hooks() as $hook) {
            $hook($invoice, $operation);
        }
    }
}
