<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\TransportPackage;
use App\Modules\Umrah\Models\TransportSector;

class StoreTransportPackageRequest extends UmrahFormRequest
{
    protected function permission(): string { return Permissions::UMRAH_SETTINGS_UPDATE; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', $this->uniqueForCompany(TransportPackage::class, 'name', 'This journey package already exists.')],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sector_ids' => ['required', 'array', 'min:1'],
            'sector_ids.*' => ['required', 'uuid', 'distinct', $this->existsForCompany(TransportSector::class, 'Selected sector was not found.')],
        ];
    }
}
