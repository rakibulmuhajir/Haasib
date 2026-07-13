<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportPackage;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Models\TransportService;
use Illuminate\Validation\Rule;

class StoreTransportFareRequest extends UmrahFormRequest
{
    protected function permission(): string { return Permissions::UMRAH_SETTINGS_UPDATE; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'transport_service_id' => ['required', 'uuid', $this->existsForCompany(TransportService::class, 'Selected vehicle was not found.')],
            'transport_sector_id' => ['nullable', 'uuid', 'required_without:transport_package_id', 'prohibited_with:transport_package_id', $this->existsForCompany(TransportSector::class, 'Selected sector was not found.')],
            'transport_package_id' => ['nullable', 'uuid', 'required_without:transport_sector_id', 'prohibited_with:transport_sector_id', $this->existsForCompany(TransportPackage::class, 'Selected journey package was not found.')],
            'charging_basis' => ['required', Rule::in(array_keys(TransportFare::BASES))],
            'sale_amount' => ['required', 'numeric', 'min:0'],
            'cost_amount' => ['required', 'numeric', 'min:0'],
            'hajj_terminal_sale_amount' => ['nullable', 'numeric', 'min:0'],
            'hajj_terminal_cost_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
