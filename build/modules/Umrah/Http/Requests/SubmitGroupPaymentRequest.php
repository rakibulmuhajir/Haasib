<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Models\CompanyCurrency;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitGroupPaymentRequest extends UmrahFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_date' => $this->input('payment_date', now()->toDateString()),
            'method' => $this->input('method', GroupPayment::METHOD_CASH),
            'reference' => $this->input('reference'),
            'notes' => $this->input('notes'),
        ]);
    }

    protected function permission(): string
    {
        return Permissions::UMRAH_PAYMENT_SUBMIT;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $baseCurrency = app(CompanyContextService::class)->getCompany()?->base_currency;
        $agentId = app(TravelAccessService::class)->linkedAgent($companyId, $this->user())?->id;

        return [
            'visa_group_id' => [
                'nullable',
                'uuid',
                Rule::exists(VisaGroup::class, 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('agent_id', $agentId)->whereNull('deleted_at')),
            ],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => [
                'required',
                'string',
                'size:3',
                'uppercase',
                function (string $attribute, mixed $value, Closure $fail) use ($baseCurrency, $companyId): void {
                    if ($value === $baseCurrency) {
                        return;
                    }

                    if (! CompanyCurrency::query()
                        ->where('company_id', $companyId)
                        ->where('currency_code', $value)
                        ->exists()) {
                        $fail('The selected currency is not enabled for this company.');
                    }
                },
            ],
            'exchange_rate' => ['prohibited'],
            'method' => ['required', Rule::in(array_keys(GroupPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $companyId = app(CompanyContextService::class)->getCompanyId();
            $agentId = app(TravelAccessService::class)->linkedAgent($companyId, $this->user())?->id;
            if (! $agentId) {
                $validator->errors()->add('agent_id', 'Only a linked agent may submit a payment.');
            }
        }];
    }
}
