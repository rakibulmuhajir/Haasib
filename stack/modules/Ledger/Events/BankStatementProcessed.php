<?php

namespace Modules\Ledger\Events;

use App\Models\BankStatement;
use Illuminate\Foundation\Events\Dispatchable;

class BankStatementProcessed
{
    use Dispatchable;

    public function __construct(
        public BankStatement $bankStatement,
        public array $summary
    ) {}
}
