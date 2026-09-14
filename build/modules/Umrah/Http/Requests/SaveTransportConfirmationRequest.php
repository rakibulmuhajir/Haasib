<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;

class SaveTransportConfirmationRequest extends SaveHotelConfirmationRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_UPDATE;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['stay_id'], $rules['brn'], $rules['confirmation_number']);

        return [...$rules, 'booking_id' => ['required', 'string', 'max:50'], 'reference' => ['nullable', 'string', 'max:100']];
    }
}
