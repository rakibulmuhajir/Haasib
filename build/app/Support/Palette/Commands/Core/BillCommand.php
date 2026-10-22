<?php

namespace App\Support\Palette\Commands\Core;

use App\Support\Palette\Commands\PaletteCommand;
use App\Support\Palette\Commands\Slot;

/** Opens the new bill form. That form reads ?vendor_id=, so the browser adds the vendor it resolved. */
final class BillCommand extends PaletteCommand
{
    public function name(): string
    {
        return 'bill';
    }

    public function label(): string
    {
        return 'New bill';
    }

    public function sentence(): string
    {
        return 'Bill {vendor?}';
    }

    public function slots(): array
    {
        return ['vendor' => Slot::search('vendor')];
    }

    public function target(): string
    {
        return 'bill.create';
    }

    public function formUrl(): ?string
    {
        return '/{company}/bills/create';
    }
}
