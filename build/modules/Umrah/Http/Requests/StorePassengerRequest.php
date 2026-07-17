<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StorePassengerRequest extends UmrahFormRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $group = VisaGroup::where('company_id', $companyId)->find($this->route('group'));
        $access = app(TravelAccessService::class);

        return $group && (! $access->isAgentMember($companyId, $this->user()) || $access->agentCanEditGroup($companyId, $this->user(), $group));
    }

    protected function permission(): string
    {
        return Permissions::UMRAH_GROUP_UPDATE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $group = VisaGroup::where('company_id', $companyId)->find($this->route('group'));
        $access = app(TravelAccessService::class);
        $requiresReason = $group && ! $access->isAgentMember($companyId, $this->user()) && $access->groupHasStarted($group);

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
            'override_reason' => [Rule::requiredIf($requiresReason), 'nullable', 'string', 'min:5', 'max:1000'],
        ];
    }
}
