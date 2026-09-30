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
            'kind' => ['required', Rule::in(['bank', 'customer', 'supplier', 'amanat', 'employee'])],
            // One account or person, or 'all' for everyone of that kind.
            'id' => ['nullable', 'regex:/^(all|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})$/'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
