<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\PricingCategory;

class UpdateAgentPricingCategoryRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_PRICING_UPDATE;
    }

    public function rules(): array
    {
        return [
            'pricing_category_id' => [
                'nullable', 'uuid',
                $this->activeForCompany(PricingCategory::class, 'Select an active pricing category.'),
            ],
        ];
    }
}
