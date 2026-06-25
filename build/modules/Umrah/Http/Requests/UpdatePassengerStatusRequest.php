<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Passenger;
use Illuminate\Validation\Rule;

class UpdatePassengerStatusRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'visa_status' => ['required', Rule::in(array_keys(Passenger::STATUSES))],
        ];
    }
}
