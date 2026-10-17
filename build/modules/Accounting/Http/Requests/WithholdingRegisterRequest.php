<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Services\WithholdingRegisterService;
use App\Support\CompanyClock;
use Illuminate\Validation\Rule;

class WithholdingRegisterRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::REPORT_VIEW)
            && $this->validateRlsContext();
    }

    protected function prepareForValidation(): void
    {
        $clock = app(CompanyClock::class);
        $this->merge([
            'from' => $this->input('from', $clock->now()->startOfMonth()->toDateString()),
            'to' => $this->input('to', $clock->today()),
            'side' => $this->input('side', 'all'),
        ]);
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'side' => ['required', Rule::in(WithholdingRegisterService::SIDES)],
            'format' => ['nullable', Rule::in(['csv'])],
        ];
    }
}
