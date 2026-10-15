<?php

namespace App\Modules\FuelStation\Listeners;

use App\Modules\FuelStation\Services\OpeningStockLedgerService;
use App\Modules\Inventory\Events\OpeningStockChanged;

/**
 * Ungated on purpose: the opening-stock journal was synced for every company
 * before the event existed, and it is not fuel-specific (it posts any opening
 * stock movement that has a stock account).
 */
class SyncOpeningStockLedger
{
    public function handle(OpeningStockChanged $event): void
    {
        $ledger = app(OpeningStockLedgerService::class);

        $event->quiet
            ? $ledger->syncQuietly($event->companyId, $event->userId)
            : $ledger->sync($event->companyId, $event->userId);
    }
}
