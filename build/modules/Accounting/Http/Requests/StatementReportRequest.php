<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Support\CompanyClock;
use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Extensions\StatementKinds;
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
            'from' => $this->input('from', app(CompanyClock::class)->now()->startOfMonth()->toDateString()),
            'to' => $this->input('to', app(CompanyClock::class)->today()),
        ]);
    }

    public function rules(): array
    {
        $company = \App\Facades\CompanyContext::getCompany();
        $extra = $company ? array_keys(app(StatementKinds::class)->availableFor($company)) : [];

        return [
            'kind' => ['required', Rule::in(['bank', 'customer', 'supplier', 'employee', 'expense', 'partner', ...$extra])],
            // One account or person, or 'all' for everyone of that kind.
            'id' => ['nullable', 'regex:/^(all|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})$/'],
            // Several people of the kind, comma-separated: a group, or any the user ticked.
            'ids' => ['nullable', 'string', 'regex:/^[0-9a-fA-F-]{36}(,[0-9a-fA-F-]{36})*$/'],
            // Every customer in one category (customer kind only); must be this company's.
            'category_id' => ['nullable', 'uuid', Rule::exists(\App\Modules\Accounting\Models\CustomerCategory::class, 'id')->where('company_id', \App\Facades\CompanyContext::getCompany()?->id)],
            'reversed' => ['nullable', 'boolean'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
