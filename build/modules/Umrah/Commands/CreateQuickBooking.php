<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Handlers\CreateQuickBookingHandler;
use App\Modules\Umrah\Models\VisaGroup;

final class CreateQuickBooking
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $companyId,
        public readonly array $data,
    ) {}

    public function handle(CreateQuickBookingHandler $handler): VisaGroup
    {
        return $handler->handle($this);
    }
}
