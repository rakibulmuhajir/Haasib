<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Support\CompanyClock;
use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class CustomerPeriodDiscountRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::CREDIT_NOTE_CREATE)
            && $this->hasCompanyPermission(Permissions::CREDIT_NOTE_APPLY) && $this->validateRlsContext();
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('get')) {
            $this->merge([
                'from' => $this->input('from', app(CompanyClock::class)->now()->startOfMonth()->toDateString()),
                'to' => $this->input('to', app(CompanyClock::class)->today()),
                'method' => $this->input('method', 'fixed'), 'value' => $this->input('value', 0),
                'credit_date' => $this->input('credit_date', max(app(CompanyClock::class)->today(), (string) $this->input('to', app(CompanyClock::class)->today()))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'credit_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:to'],
            'method' => ['required', 'in:fixed,percent,per_litre'],
            'value' => ['required', 'numeric', $this->isMethod('get') ? 'min:0' : 'gt:0', 'max:999999999999.99',
                ...($this->input('method') === 'percent' ? ['max:100'] : [])],
            'preview_token' => [$this->isMethod('post') ? 'required' : 'nullable', 'string', 'size:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
