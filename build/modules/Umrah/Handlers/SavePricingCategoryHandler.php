<?php

namespace App\Modules\Umrah\Handlers;

use App\Modules\Umrah\Commands\SavePricingCategory;
use App\Modules\Umrah\Models\PricingCategory;

final class SavePricingCategoryHandler
{
    public function handle(SavePricingCategory $command): PricingCategory
    {
        $category = $command->categoryId
            ? PricingCategory::where('company_id', $command->companyId)->findOrFail($command->categoryId)
            : new PricingCategory(['company_id' => $command->companyId, 'is_active' => true]);

        $category->fill([
            'name' => trim($command->data['name']),
            'description' => $command->data['description'] ?? null,
        ])->save();

        return $category->fresh();
    }
}
