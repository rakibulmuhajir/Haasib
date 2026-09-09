<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Services\VoucherPrintProfiles;

class SaveVoucherProfileRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_SETTINGS_UPDATE;
    }

    public function rules(): array
    {
        return [
            'target' => ['required', 'string', 'max:50', 'regex:/^(company|(agent|vendor):[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})$/'],
            ...VoucherPrintProfiles::rules('details'),
            'details' => ['required', 'array:footer_text,contacts', 'required_array_keys:contacts'],
        ];
    }

    public function attributes(): array
    {
        return VoucherPrintProfiles::attributes('details');
    }
}
