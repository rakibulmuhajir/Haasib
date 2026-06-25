<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;

class DestroyAgentRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_AGENT_DELETE;
    }

    public function rules(): array
    {
        return [];
    }
}
