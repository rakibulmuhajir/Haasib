<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;

class VendorStatementRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_VENDOR_VIEW;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
