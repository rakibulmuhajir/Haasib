<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\PricingCategory;

class SavePricingCategoryRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_PRICING_UPDATE;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                $this->uniqueForCompanyIgnoringCase(
                    PricingCategory::class,
                    'name',
                    'A pricing category with this name already exists.',
                    $this->route('category'),
                ),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
