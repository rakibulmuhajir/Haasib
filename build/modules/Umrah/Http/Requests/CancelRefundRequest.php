<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;

class CancelRefundRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_REFUND_CANCEL;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
