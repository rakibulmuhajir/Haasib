<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class ReceiveSupplierClaimRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BILL_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'receipt_line_id' => 'required|uuid',
            'received_date' => 'required|date',
            'received_amount' => 'required|numeric|min:0.01',
            'received_account_id' => 'required|uuid',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
