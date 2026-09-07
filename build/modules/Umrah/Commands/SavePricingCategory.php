<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Handlers\SavePricingCategoryHandler;
use App\Modules\Umrah\Models\PricingCategory;

final class SavePricingCategory
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $companyId,
        public readonly array $data,
        public readonly ?string $categoryId = null,
    ) {}

    public function handle(SavePricingCategoryHandler $handler): PricingCategory
    {
        return $handler->handle($this);
    }
}
