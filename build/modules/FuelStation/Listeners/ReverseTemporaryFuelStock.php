<?php

namespace App\Modules\FuelStation\Listeners;

use App\Modules\Accounting\Events\InvoiceVoided;
use App\Modules\FuelStation\Services\TemporaryFuelStockService;

class ReverseTemporaryFuelStock
{
    public function handle(InvoiceVoided $event): void
    {
        app(TemporaryFuelStockService::class)->reverse($event->invoice, $event->reversalDate, $event->userId);
    }
}
