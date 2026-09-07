<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Handlers\AssignAgentPricingCategoryHandler;
use App\Modules\Umrah\Models\Agent;

final class AssignAgentPricingCategory
{
    public function __construct(
        public readonly string $companyId,
        public readonly string $agentId,
        public readonly ?string $categoryId,
    ) {}

    public function handle(AssignAgentPricingCategoryHandler $handler): Agent
    {
        return $handler->handle($this);
    }
}
