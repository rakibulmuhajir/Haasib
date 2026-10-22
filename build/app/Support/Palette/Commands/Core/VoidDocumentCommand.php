<?php

namespace App\Support\Palette\Commands\Core;

use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/** `Void invoice {invoice}`: names one document and voids it through the action's own checks. */
abstract class VoidDocumentCommand extends CoreCommand
{
    /** The document's noun: 'invoice', 'bill'. */
    abstract protected function noun(): string;

    public function name(): string
    {
        return 'void '.$this->noun();
    }

    public function label(): string
    {
        return 'Void '.$this->noun();
    }

    public function sentence(): string
    {
        return 'Void '.$this->noun().' {'.$this->noun().'}';
    }

    public function slots(): array
    {
        return [$this->noun() => Slot::search($this->noun())];
    }

    public function target(): string
    {
        return $this->noun().'.void';
    }

    public function opens(): ?string
    {
        return '/{company}/'.$this->noun().'s/{id}';
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $states = [];
        $doc = $this->record($this->noun(), $this->noun(), $in, $ctx, $states);
        if ($doc === null) {
            return Resolution::pending($states);
        }

        return Resolution::ready(
            $this->target(),
            ['id' => $doc['id']],
            ['what' => 'Void', 'who' => $doc['label'], 'currency' => $ctx->defaults['currency']],
            'Void '.$this->noun().' '.$doc['label'],
        );
    }
}
