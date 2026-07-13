<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\TransportSector;

class StoreTransportSectorRequest extends UmrahFormRequest
{
    protected function permission(): string { return Permissions::UMRAH_SETTINGS_UPDATE; }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', $this->uniqueForCompany(TransportSector::class, 'code', 'This sector code is already used.')],
            'name' => ['required', 'string', 'max:150'],
            'origin' => ['required', 'string', 'max:150'],
            'destination' => ['required', 'string', 'max:150'],
        ];
    }
}
