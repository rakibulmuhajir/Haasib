<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Driver;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StoreVisaGroupRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_CREATE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'group_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(VisaGroup::class, 'group_number', 'This group number is already used.'),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'agent_id' => ['required', 'uuid', $this->existsForCompany(Agent::class, 'Selected agent was not found.')],
            'vendor_id' => ['required', 'uuid', Rule::exists('umrah.visa_vendors', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->where('is_active', true)->whereNull('deleted_at'))],
            'mandatory_transport_vendor_id' => [
                Rule::requiredIf($this->input('transport_mode') === VisaGroup::TRANSPORT_STANDARD_BUS),
                'nullable',
                'uuid',
                Rule::exists('umrah.visa_vendors', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->where('is_active', true)->whereNull('deleted_at')),
            ],
            'transport_service_id' => ['nullable', 'uuid', $this->existsForCompany(TransportService::class, 'Selected transport service was not found.')],
            'driver_id' => ['nullable', 'uuid', $this->existsForCompany(Driver::class, 'Selected driver was not found.')],
            'status' => ['nullable', Rule::in(array_keys(VisaGroup::STATUSES))],
            'travel_date' => ['nullable', 'date'],
            'flight_airline' => ['nullable', 'string', 'max:255'],
            'flight_number' => ['nullable', 'string', 'max:100'],
            'flight_notes' => ['nullable', 'string', 'max:500'],
            'hotel_makkah' => ['nullable', 'string', 'max:255'],
            'hotel_madinah' => ['nullable', 'string', 'max:255'],
            'hotel_notes' => ['nullable', 'string', 'max:500'],
            'transport_required' => ['accepted'],
            'transport_mode' => ['required', Rule::in(array_keys(VisaGroup::TRANSPORT_MODES))],
            'transport_quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'transport_pax_capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'passenger_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'visa_sale_amount' => ['nullable', 'numeric', 'min:0'],
            'transport_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'visa_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'transport_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'passengers' => ['nullable', 'array'],
            'passengers.*.full_name' => ['nullable', 'string', 'max:255'],
            'passengers.*.passport_number' => ['nullable', 'string', 'max:100'],
            'passengers.*.nationality' => ['nullable', Rule::in(array_keys(Agent::COUNTRIES))],
            'passengers.*.date_of_birth' => ['nullable', 'date'],
            'passengers.*.imported_age' => ['nullable', 'integer', 'min:0', 'max:130'],
            'passengers.*.service_type' => ['nullable', Rule::in(array_keys(Passenger::SERVICE_TYPES))],
            'passengers.*.transport_charge_amount' => ['nullable', 'numeric', 'min:0'],
            'passengers.*.visa_status' => ['nullable', Rule::in(array_keys(Passenger::STATUSES))],
            'transport_items' => [
                'nullable',
                'array',
                Rule::when($this->input('transport_mode') === VisaGroup::TRANSPORT_SPECIALIZED, ['required', 'min:1']),
            ],
            'transport_items.*.transport_fare_id' => ['required', 'uuid', 'distinct', $this->existsForCompany(TransportFare::class, 'Selected transport fare was not found.')],
            'transport_items.*.driver_id' => ['nullable', 'uuid', $this->existsForCompany(Driver::class, 'Selected driver was not found.')],
            'transport_items.*.scheduled_at' => ['nullable', 'date'],
            'transport_items.*.terminal' => ['required', Rule::in(['standard', 'hajj'])],
            'transport_items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'transport_items.*.passenger_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'transport_items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
