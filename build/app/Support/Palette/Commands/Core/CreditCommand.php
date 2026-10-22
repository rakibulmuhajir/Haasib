<?php

namespace App\Support\Palette\Commands\Core;

use App\Support\Palette\Commands\PaletteCommand;
use App\Support\Palette\Commands\Slot;

/** Opens the new credit note form. That form reads no customer parameter, so a typed customer is not carried over. */
final class CreditCommand extends PaletteCommand
{
    public function name(): string
    {
        return 'credit';
    }

    public function label(): string
    {
        return 'New credit note';
    }

    public function sentence(): string
    {
        return 'Credit {customer?}';
    }

    public function slots(): array
    {
        return ['customer' => Slot::search('customer')];
    }

    public function target(): string
    {
        return 'credit_note.create';
    }

    public function formUrl(): ?string
    {
        return '/{company}/credit-notes/create';
    }
}
