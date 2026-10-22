<?php

namespace App\Support\Palette\Commands\Core;

use App\Support\Palette\Commands\PaletteCommand;
use App\Support\Palette\Commands\Slot;

/** Opens the new invoice form. That form reads ?customer_id=, so the browser adds the customer it resolved. */
final class InvoiceCommand extends PaletteCommand
{
    public function name(): string
    {
        return 'invoice';
    }

    public function aliases(): array
    {
        return ['inv'];
    }

    public function label(): string
    {
        return 'New invoice';
    }

    public function sentence(): string
    {
        return 'Invoice {customer?}';
    }

    public function slots(): array
    {
        return ['customer' => Slot::search('customer')];
    }

    public function target(): string
    {
        return 'invoice.create';
    }

    public function formUrl(): ?string
    {
        return '/{company}/invoices/create';
    }
}
