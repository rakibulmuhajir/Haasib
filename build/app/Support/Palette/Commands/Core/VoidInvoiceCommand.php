<?php

namespace App\Support\Palette\Commands\Core;

/** Voids one invoice. */
final class VoidInvoiceCommand extends VoidDocumentCommand
{
    protected function noun(): string
    {
        return 'invoice';
    }
}
