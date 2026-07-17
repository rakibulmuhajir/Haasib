<?php

namespace App\Modules\Umrah\Http\Requests;

class SplitVoucherPassengersRequest extends VoucherPassengerActionRequest
{
    public function rules(): array
    {
        return $this->commonRules();
    }
}
