<?php

namespace App\Modules\FuelStation\Palette;

use App\Support\Palette\Commands\PaletteCommand;

/** Opens the daily close form; the close itself is too big for one sentence. */
final class CloseDayCommand extends PaletteCommand
{
    public function name(): string
    {
        return 'close day';
    }

    public function label(): string
    {
        return 'Close day';
    }

    public function sentence(): string
    {
        return 'Close day';
    }

    public function slots(): array
    {
        return [];
    }

    public function target(): string
    {
        return 'fuel.daily_close.save';
    }

    public function formUrl(): ?string
    {
        // route() would percent-encode a literal {company}; swap a plain marker in afterwards.
        return str_replace('__company__', '{company}', route('fuel.daily-close.create', ['company' => '__company__'], false));
    }
}
