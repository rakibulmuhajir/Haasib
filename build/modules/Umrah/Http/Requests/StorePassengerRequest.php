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
    private ?VisaGroup $group = null;

    private bool $groupResolved = false;

    /**
     * Resolved once. authorize(), rules() and prepareForValidation() each
     * need the group, and each used to fetch it again.
     */
    private function group(): ?VisaGroup
    {
        if (! $this->groupResolved) {
            $this->group = VisaGroup::where('company_id', app(CompanyContextService::class)->getCompanyId())
                ->find($this->route('group'));
            $this->groupResolved = true;
        }

        return $this->group;
    }

    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $group = $this->group();
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
        $group = $this->group();
        $access = app(TravelAccessService::class);
        $requiresReason = $group && ! $access->isAgentMember($companyId, $this->user()) && $access->groupHasStarted($group);

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'passport_number' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', Rule::in(array_keys(Agent::COUNTRIES))],
            'date_of_birth' => ['nullable', 'date'],
            'imported_age' => ['nullable', 'integer', 'min:0', 'max:130'],
            // Both are overwritten in prepareForValidation() from the group's
            // own choice, so these rules never judge operator input -- they
            // exist so the derived values survive into validated(), which
            // returns only attributes that carry a rule.
            'service_type' => ['required', Rule::in(array_keys(Passenger::SERVICE_TYPES))],
            'transport_charge_amount' => ['required', 'numeric', 'in:0'],
            'visa_status' => ['nullable', Rule::in(array_keys(Passenger::STATUSES))],
            'notes' => ['nullable', 'string'],
            'override_reason' => [Rule::requiredIf($requiresReason), 'nullable', 'string', 'min:5', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // The group decided once what it sells. A passenger added afterwards
        // inherits that decision instead of making its own, so a group can
        // never come to hold passengers who disagree with it.
        $this->merge([
            'service_type' => $this->group()?->includes_visa === false
                ? Passenger::SERVICE_TRANSPORT_ONLY
                : Passenger::SERVICE_VISA_TRANSPORT,
            'transport_charge_amount' => 0,
        ]);
    }
}
