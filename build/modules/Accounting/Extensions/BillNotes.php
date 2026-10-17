<?php

namespace App\Modules\Accounting\Extensions;

use App\Modules\Accounting\Models\Bill;
use App\Support\Extensions\HasHooks;

/**
 * Read-only lines a module adds to a bill's page -- e.g. the share of a card charge the money
 * that paid this bill carried. A hook takes the bill and returns a list of
 * ['label' => string, 'detail' => ?string, 'amount' => float]; nothing here posts anything.
 */
class BillNotes
{
    use HasHooks;

    public function for(Bill $bill): array
    {
        $notes = [];
        foreach (self::hooks() as $hook) {
            array_push($notes, ...$hook($bill));
        }

        return $notes;
    }
}
