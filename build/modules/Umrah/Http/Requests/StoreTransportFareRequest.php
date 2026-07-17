<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportPackage;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StoreTransportFareRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_SETTINGS_UPDATE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'name' => ['required', 'string', 'max:150'],
            'transport_vendor_id' => ['required', 'uuid', Rule::exists('umrah.visa_vendors', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->where('is_active', true)->whereNull('deleted_at'))],
            'transport_service_id' => ['required', 'uuid', $this->activeForCompany(TransportService::class, 'Select an active vehicle.')],
            'transport_sector_id' => ['nullable', 'uuid', 'required_without:transport_package_id', 'prohibited_with:transport_package_id', $this->activeForCompany(TransportSector::class, 'Select an active sector.')],
            'transport_package_id' => ['nullable', 'uuid', 'required_without:transport_sector_id', 'prohibited_with:transport_sector_id', $this->activeForCompany(TransportPackage::class, 'Select an active journey package.')],
            'charging_basis' => ['required', Rule::in(array_keys(TransportFare::BASES))],
            'sale_amount' => ['required', 'numeric', 'min:0'],
            'cost_amount' => ['required', 'numeric', 'min:0'],
            'hajj_terminal_sale_amount' => ['nullable', 'numeric', 'min:0'],
            'hajj_terminal_cost_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
