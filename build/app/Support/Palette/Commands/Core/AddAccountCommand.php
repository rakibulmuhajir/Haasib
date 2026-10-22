<?php

namespace App\Support\Palette\Commands\Core;

use App\Support\Palette\Commands\PaletteCommand;

/** An account needs a type and a code, so Enter opens its form. */
final class AddAccountCommand extends PaletteCommand
{
    public function name(): string
    {
        return 'add account';
    }

    public function label(): string
    {
        return 'Add account';
    }

    public function sentence(): string
    {
        return 'Add account';
    }

    public function slots(): array
    {
        return [];
    }

    public function target(): string
    {
        return 'account.create';
    }

    public function formUrl(): ?string
    {
        return '/{company}/accounts/create';
    }
}
