<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Handlers\SetCommercialPricingStatusHandler;

final class SetCommercialPricingStatus
{
    public function __construct(
        public readonly string $companyId,
        public readonly string $recordType,
        public readonly string $recordId,
        public readonly bool $isActive,
    ) {}

    public function handle(SetCommercialPricingStatusHandler $handler): void
    {
        $handler->handle($this);
    }
}
