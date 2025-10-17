<?php

namespace Modules\Ledger\Events;

use App\Models\BankReconciliation;
use Illuminate\Foundation\Events\Dispatchable;

class AutoMatchCompleted
{
    use Dispatchable;

    public function __construct(
        public BankReconciliation $reconciliation,
        public int $matchesCreated
    ) {}
}
