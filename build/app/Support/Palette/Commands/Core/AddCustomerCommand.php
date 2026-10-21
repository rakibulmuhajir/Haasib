<?php

namespace App\Support\Palette\Commands\Core;

/** A new customer by name. */
final class AddCustomerCommand extends AddRecordCommand
{
    public function name(): string
    {
        return 'add customer';
    }

    protected function noun(): string
    {
        return 'customer';
    }

    public function target(): string
    {
        return 'customer.create';
    }

    public function opens(): ?string
    {
        return '/{company}/customers/{id}';
    }
}
