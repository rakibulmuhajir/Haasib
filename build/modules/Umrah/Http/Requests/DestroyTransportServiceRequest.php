<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;

class DestroyTransportServiceRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_SETTINGS_DELETE;
    }

    public function rules(): array
    {
        return [];
    }
}
