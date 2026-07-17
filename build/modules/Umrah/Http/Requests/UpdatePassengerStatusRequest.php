<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class UpdatePassengerStatusRequest extends UmrahFormRequest
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
            'visa_status' => ['required', Rule::in(array_keys(Passenger::STATUSES))],
            'override_reason' => [Rule::requiredIf($requiresReason), 'nullable', 'string', 'min:5', 'max:1000'],
        ];
    }
}
