<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class RemovePassengerRequest extends UmrahFormRequest
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
        $passenger = Passenger::where('company_id', $companyId)->where('visa_group_id', $group?->id)->find($this->route('passenger'));
        $access = app(TravelAccessService::class);

        return $group && $passenger && (! $access->isAgentMember($companyId, $this->user()) || $access->agentCanEditGroup($companyId, $this->user(), $group));
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $group = VisaGroup::where('company_id', $companyId)->find($this->route('group'));
        $access = app(TravelAccessService::class);
        $required = $group && ! $access->isAgentMember($companyId, $this->user()) && $access->groupHasStarted($group);

        return ['reason' => [Rule::requiredIf($required), 'nullable', 'string', 'min:5', 'max:1000']];
    }
}
