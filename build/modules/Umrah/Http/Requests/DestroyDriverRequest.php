<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;

class DestroyDriverRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_SETTINGS_UPDATE;
    }

    public function rules(): array
    {
        return [];
    }
}
