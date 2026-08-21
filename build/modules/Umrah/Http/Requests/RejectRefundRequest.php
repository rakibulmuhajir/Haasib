<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;

class RejectRefundRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_REFUND_APPROVE;
    }

    public function rules(): array
    {
        return [
            'review_remarks' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
