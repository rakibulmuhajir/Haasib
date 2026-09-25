<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StatementReportRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::REPORT_VIEW)
            && $this->validateRlsContext();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kind' => $this->input('kind', 'bank'),
            'from' => $this->input('from', now()->startOfMonth()->toDateString()),
            'to' => $this->input('to', now()->toDateString()),
        ]);
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['bank', 'customer', 'supplier'])],
            'id' => ['nullable', 'uuid'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
