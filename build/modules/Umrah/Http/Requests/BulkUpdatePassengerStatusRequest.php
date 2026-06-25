<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Passenger;
use Illuminate\Validation\Rule;

class BulkUpdatePassengerStatusRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'passenger_ids' => ['required', 'array', 'min:1'],
            'passenger_ids.*' => ['required', 'uuid'],
            'visa_status' => ['required', Rule::in(array_keys(Passenger::STATUSES))],
        ];
    }
}
