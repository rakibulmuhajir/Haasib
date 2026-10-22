<?php

namespace App\Support\Palette\Commands\Core;

/** A new vendor by name. */
final class AddVendorCommand extends AddRecordCommand
{
    public function name(): string
    {
        return 'add vendor';
    }

    protected function noun(): string
    {
        return 'vendor';
    }

    public function target(): string
    {
        return 'vendor.create';
    }

    public function opens(): ?string
    {
        return '/{company}/vendors/{id}';
    }
}
