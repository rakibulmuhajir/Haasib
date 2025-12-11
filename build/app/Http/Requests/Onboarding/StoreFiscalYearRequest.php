<?php

namespace App\Http\Requests\Onboarding;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreFiscalYearRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'fiscal_year_start_month' => 'required|integer|min:1|max:12',
            'period_frequency' => 'required|in:monthly,quarterly,yearly',
        ];
    }
}
