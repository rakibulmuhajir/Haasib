<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CurrentCompany;

class JoinExistingPassengersRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_VOUCHER_UPDATE;
    }

    public function authorize(): bool
    {
        return parent::authorize() && ! app(TravelAccessService::class)
            ->isAgentMember(app(CurrentCompany::class)->get()->id, $this->user());
    }

    public function rules(): array
    {
        return ['passenger_ids' => ['required', 'array', 'min:1', 'max:50'],
            'passenger_ids.*' => ['required', 'uuid', 'distinct']];
    }
}
