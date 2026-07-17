<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Modules\Umrah\Models\Passenger;
use App\Services\CompanyContextService;

class UpdatePassengerRequest extends StorePassengerRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return Passenger::where('company_id', $companyId)
            ->where('visa_group_id', $this->route('group'))
            ->whereKey($this->route('passenger'))->exists();
    }
}
