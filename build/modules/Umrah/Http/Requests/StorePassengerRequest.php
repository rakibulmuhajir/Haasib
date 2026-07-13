<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use Illuminate\Validation\Rule;

class StorePassengerRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_UPDATE;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'passport_number' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', Rule::in(array_keys(Agent::COUNTRIES))],
            'date_of_birth' => ['nullable', 'date'],
            'imported_age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'service_type' => ['nullable', Rule::in(array_keys(Passenger::SERVICE_TYPES))],
            'transport_charge_amount' => ['nullable', 'numeric', 'min:0'],
            'visa_status' => ['nullable', Rule::in(array_keys(Passenger::STATUSES))],
            'notes' => ['nullable', 'string'],
        ];
    }
}
