<?php

namespace App\Modules\Umrah\Handlers;

use App\Modules\Umrah\Commands\AssignAgentPricingCategory;
use App\Modules\Umrah\Models\Agent;

final class AssignAgentPricingCategoryHandler
{
    public function handle(AssignAgentPricingCategory $command): Agent
    {
        $agent = Agent::where('company_id', $command->companyId)->findOrFail($command->agentId);
        $agent->update(['pricing_category_id' => $command->categoryId]);

        return $agent->fresh('pricingCategory');
    }
}
