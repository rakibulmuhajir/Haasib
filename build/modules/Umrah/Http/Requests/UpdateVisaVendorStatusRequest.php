<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;

class UpdateVisaVendorStatusRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_VENDOR_UPDATE;
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
