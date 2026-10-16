<?php

namespace App\Modules\Inventory\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after opening stock is recorded, adjusted or removed. Listeners run
 * synchronously inside the caller's transaction; a throw rolls it back unless
 * $quiet is true, in which case the listener is expected to log and swallow its
 * own problems. Never make a listener queued or ShouldDispatchAfterCommit.
 */
class OpeningStockChanged
{
    use Dispatchable;

    public function __construct(public string $companyId, public ?string $userId, public bool $quiet = true) {}
}
