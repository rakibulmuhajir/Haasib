<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class ApplyCreditNoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::CREDIT_NOTE_APPLY) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return ['invoice_id' => ['required', 'uuid'], 'amount' => ['required', 'numeric', 'gt:0'],
            'applied_at' => ['required', 'date_format:Y-m-d']];
    }
}
