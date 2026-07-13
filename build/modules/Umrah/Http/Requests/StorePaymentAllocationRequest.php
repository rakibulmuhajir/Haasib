<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\VisaGroup;

class StorePaymentAllocationRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_PAYMENT_CREATE;
    }

    public function rules(): array
    {
        return [
            'visa_group_id' => ['required', 'uuid', $this->existsForCompany(VisaGroup::class, 'Selected group was not found.')],
            'base_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
