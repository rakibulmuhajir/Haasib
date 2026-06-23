<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VehicleType;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaService;
use App\Modules\Umrah\Models\VisaVendor;
use Illuminate\Validation\Rule;

class StoreVisaGroupRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_CREATE;
    }

    public function rules(): array
    {
        return [
            'group_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(VisaGroup::class, 'group_number', 'This group number is already used.'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'agent_id' => ['required', 'uuid', $this->existsForCompany(Agent::class, 'Selected agent was not found.')],
            'vendor_id' => ['nullable', 'uuid', $this->existsForCompany(VisaVendor::class, 'Selected vendor was not found.')],
            'vehicle_type_id' => ['nullable', 'uuid', $this->existsForCompany(VehicleType::class, 'Selected vehicle type was not found.')],
            'visa_service_id' => ['nullable', 'uuid', $this->existsForCompany(VisaService::class, 'Selected visa service was not found.')],
            'transport_service_id' => ['nullable', 'uuid', $this->existsForCompany(TransportService::class, 'Selected transport service was not found.')],
            'status' => ['nullable', Rule::in(array_keys(VisaGroup::STATUSES))],
            'travel_date' => ['nullable', 'date'],
            'flight_airline' => ['nullable', 'string', 'max:255'],
            'flight_number' => ['nullable', 'string', 'max:100'],
            'flight_notes' => ['nullable', 'string', 'max:500'],
            'hotel_makkah' => ['nullable', 'string', 'max:255'],
            'hotel_madinah' => ['nullable', 'string', 'max:255'],
            'hotel_notes' => ['nullable', 'string', 'max:500'],
            'transport_required' => ['boolean'],
            'transport_quantity' => ['nullable', 'integer', 'min:0', 'max:100'],
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
            'passengers.*.nationality' => ['nullable', 'string', 'max:100'],
            'passengers.*.visa_status' => ['nullable', Rule::in(array_keys(Passenger::STATUSES))],
        ];
    }
}
