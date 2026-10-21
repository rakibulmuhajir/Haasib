<?php

namespace App\Support\Palette\Commands\Core;

use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/** `Add customer {name}`: a new record from its name alone, for actions whose only required field is the name. */
abstract class AddRecordCommand extends CoreCommand
{
    /** The record's noun: 'customer', 'vendor'. */
    abstract protected function noun(): string;

    public function label(): string
    {
        return 'Add '.$this->noun();
    }

    public function sentence(): string
    {
        return 'Add '.$this->noun().' {name}';
    }

    public function slots(): array
    {
        return ['name' => Slot::text()];
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $name = $in->text('name');
        if ($name === null) {
            return Resolution::pending(['name' => ['state' => 'missing']]);
        }

        return Resolution::ready(
            $this->target(),
            ['name' => $name],
            ['what' => 'New '.$this->noun(), 'who' => $name, 'currency' => $ctx->defaults['currency']],
            'Add '.$this->noun().' '.$name,
        );
    }
}
