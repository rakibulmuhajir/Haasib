<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Driver;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class UpdateVisaGroupRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_UPDATE;
    }

    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        $companyId = app(CompanyContextService::class)->getCompanyId();
        $group = VisaGroup::where('company_id', $companyId)->find($this->route('group'));
        if (! $group) {
            return false;
        }

        $access = app(TravelAccessService::class);

        return ! $access->isAgentMember($companyId, $this->user())
            || $access->agentCanEditGroup($companyId, $this->user(), $group);
    }

    /**
     * Only transport_items is derived here, unlike StoreVisaGroupRequest which
     * runs the whole of deriveGroupServiceFields(). This route accepts no
     * passengers, and merging an empty passenger list would tell the request
     * a group had none.
     *
     * The guard matters as much as the merge: merging an absent key would make
     * it present, and a present-but-empty list is how this request is told to
     * clear the group's vehicles. Renaming a group must not delete its buses.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('transport_items')) {
            return;
        }

        $this->merge(['transport_items' => $this->withSeatedVehicleCounts($this->input('transport_items', []))]);
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $group = VisaGroup::where('company_id', $companyId)->find($this->route('group'));
        $access = app(TravelAccessService::class);
        $requiresReason = $group
            && ! $access->isAgentMember($companyId, $this->user())
            && $access->groupHasStarted($group);

        return [
            'vendor_id' => ['sometimes', 'nullable', 'uuid', Rule::exists(VisaVendor::class, 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->where('is_active', true)->whereNull('deleted_at'))],
            'includes_visa' => ['sometimes', 'boolean'],
            'transport_mode' => [
                'required',
                Rule::in(array_unique([VisaGroup::TRANSPORT_NONE, VisaGroup::TRANSPORT_STANDARD_BUS, $group?->transport_mode ?? VisaGroup::TRANSPORT_STANDARD_BUS])),
                $this->transportSellsSomethingRule($group?->includes_visa),
            ],
            'mandatory_transport_vendor_id' => [Rule::requiredIf($this->input('transport_mode') === VisaGroup::TRANSPORT_STANDARD_BUS), 'nullable', 'uuid', Rule::exists(VisaVendor::class, 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')->where(fn ($vendor) => $vendor->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->orWhere('provides_mandatory_transport', true)))],
            'name' => ['required', 'string', 'max:255'],
            'travel_date' => ['nullable', 'date'],
            'flight_airline' => ['nullable', 'string', 'max:255'],
            'flight_number' => ['nullable', 'string', 'max:100'],
            'flight_notes' => ['nullable', 'string', 'max:500'],
            'hotel_makkah' => ['nullable', 'string', 'max:255'],
            'hotel_madinah' => ['nullable', 'string', 'max:255'],
            'hotel_notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            // Absent means unchanged; present means "these are the group's
            // vehicles now". Present and empty is refused rather than obeyed --
            // a specialized group with no vehicles sells transport it cannot
            // provide. Removing transport is what self-arranged mode is for,
            // and that path resets the passengers too.
            'transport_items' => ['sometimes', 'array', 'min:1'],
            'transport_items.*.transport_fare_id' => ['required', 'uuid', 'distinct', $this->existsForCompany(TransportFare::class, 'Selected transport fare was not found.')],
            'transport_items.*.driver_id' => ['nullable', 'uuid', $this->existsForCompany(Driver::class, 'Selected driver was not found.')],
            'transport_items.*.scheduled_at' => ['nullable', 'date'],
            'transport_items.*.terminal' => ['required', Rule::in(['standard', 'hajj'])],
            'transport_items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'transport_items.*.passenger_count' => ['nullable', 'integer', 'min:1', 'max:500'],
            'transport_items.*.notes' => ['nullable', 'string', 'max:500'],
            'override_reason' => [Rule::requiredIf($requiresReason), 'nullable', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'transport_items.min' => 'A specialized transport group must keep at least one vehicle. Choose self-arranged transport to remove transport entirely.',
        ];
    }
}
