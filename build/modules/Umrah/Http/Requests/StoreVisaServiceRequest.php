<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\VisaService;
use App\Modules\Umrah\Models\VisaVendor;

class StoreVisaServiceRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_SETTINGS_UPDATE;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                $this->uniqueForCompany(VisaService::class, 'name', 'This visa service already exists.'),
            ],
            'vendor_id' => ['nullable', 'uuid', $this->existsForCompany(VisaVendor::class, 'Selected vendor was not found.')],
            'retail_amount' => ['nullable', 'numeric', 'min:0'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
