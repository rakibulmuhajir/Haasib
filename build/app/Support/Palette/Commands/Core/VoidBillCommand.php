<?php

namespace App\Support\Palette\Commands\Core;

/** Voids one bill. */
final class VoidBillCommand extends VoidDocumentCommand
{
    protected function noun(): string
    {
        return 'bill';
    }
}
