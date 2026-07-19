<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
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

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $group = VisaGroup::where('company_id', $companyId)->find($this->route('group'));
        $access = app(TravelAccessService::class);
        $requiresReason = $group
            && ! $access->isAgentMember($companyId, $this->user())
            && $access->groupHasStarted($group);

        return [
            'vendor_id' => ['sometimes', 'nullable', 'uuid', Rule::exists(VisaVendor::class, 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->where('is_active', true)->whereNull('deleted_at'))],
            'mandatory_transport_vendor_id' => ['sometimes', 'nullable', 'uuid', Rule::exists(VisaVendor::class, 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')->where(fn ($vendor) => $vendor->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orWhere('provides_mandatory_transport', true)))],
            'name' => ['required', 'string', 'max:255'],
            'travel_date' => ['nullable', 'date'],
            'flight_airline' => ['nullable', 'string', 'max:255'],
            'flight_number' => ['nullable', 'string', 'max:100'],
            'flight_notes' => ['nullable', 'string', 'max:500'],
            'hotel_makkah' => ['nullable', 'string', 'max:255'],
            'hotel_madinah' => ['nullable', 'string', 'max:255'],
            'hotel_notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'override_reason' => [Rule::requiredIf($requiresReason), 'nullable', 'string', 'min:5', 'max:1000'],
        ];
    }
}
