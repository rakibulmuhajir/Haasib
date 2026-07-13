<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;

class ImportMutamersRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_CREATE;
    }

    public function rules(): array
    {
        return [
            'mutamers_file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ];
    }
}
