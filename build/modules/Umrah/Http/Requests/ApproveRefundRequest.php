<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;

class ApproveRefundRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_REFUND_APPROVE;
    }

    public function rules(): array
    {
        return [
            'review_remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
