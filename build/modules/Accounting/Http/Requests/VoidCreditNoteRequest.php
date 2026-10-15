<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class VoidCreditNoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::CREDIT_NOTE_VOID) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }
}
