<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class CreateFiscalYearPeriodsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::JOURNAL_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'period_type' => ['required', 'in:monthly,quarterly,yearly'],
        ];
    }
}

