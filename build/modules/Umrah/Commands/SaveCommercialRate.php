<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Handlers\SaveCommercialRateHandler;
use App\Modules\Umrah\Models\CommercialRate;

final class SaveCommercialRate
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $companyId,
        public readonly ?string $userId,
        public readonly array $data,
        public readonly ?string $rateId = null,
    ) {}

    public function handle(SaveCommercialRateHandler $handler): CommercialRate
    {
        return $handler->handle($this);
    }
}
