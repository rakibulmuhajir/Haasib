<?php

namespace App\Support\Palette\Commands\Core;

use App\Support\Palette\Commands\PaletteCommand;

/** An item needs a type, unit and accounts and has no CommandBus action, so Enter opens its form. The target is the permission that gates it. */
final class AddItemCommand extends PaletteCommand
{
    public function name(): string
    {
        return 'add item';
    }

    public function label(): string
    {
        return 'Add item';
    }

    public function sentence(): string
    {
        return 'Add item';
    }

    public function slots(): array
    {
        return [];
    }

    public function target(): string
    {
        return 'item.create';
    }

    public function formUrl(): ?string
    {
        return '/{company}/items/create';
    }
}
