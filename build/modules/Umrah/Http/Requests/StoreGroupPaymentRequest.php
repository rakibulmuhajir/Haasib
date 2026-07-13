<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGroupPaymentRequest extends UmrahFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_number' => $this->input('payment_number'),
            'payment_date' => $this->input('payment_date', now()->toDateString()),
            'method' => $this->input('method', GroupPayment::METHOD_CASH),
            'account_id' => $this->input('account_id'),
            'reference' => $this->input('reference'),
            'notes' => $this->input('notes'),
        ]);
    }

    protected function permission(): string
    {
        return Permissions::UMRAH_PAYMENT_CREATE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $baseCurrency = app(CompanyContextService::class)->getCompany()?->base_currency;

        return [
            'payment_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(GroupPayment::class, 'payment_number', 'This payment number is already used.'),
            ],
            'payment_date' => ['required', 'date'],
            'direction' => ['required', Rule::in(array_keys(GroupPayment::DIRECTIONS))],
            'agent_id' => ['nullable', 'uuid', $this->existsForCompany(Agent::class, 'Selected agent was not found.')],
            'visa_group_id' => ['nullable', 'uuid', $this->existsForCompany(VisaGroup::class, 'Selected group was not found.')],
            'visa_vendor_id' => ['nullable', 'uuid', $this->existsForCompany(VisaVendor::class, 'Selected vendor was not found.')],
            'hotel_vendor_id' => ['nullable', 'uuid', $this->existsForCompany(HotelVendor::class, 'Selected hotel vendor was not found.')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => [
                'required',
                'string',
                'size:3',
                'uppercase',
                Rule::exists('auth.company_currencies', 'currency_code')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'exchange_rate' => [
                'nullable',
                'numeric',
                'min:0.00000001',
                'decimal:0,8',
                Rule::requiredIf(fn () => $this->input('currency') && $this->input('currency') !== $baseCurrency),
                Rule::prohibitedIf(fn () => $this->input('currency') === $baseCurrency && $this->filled('exchange_rate')),
            ],
            'method' => ['required', Rule::in(array_keys(GroupPayment::METHODS))],
            'account_id' => [
                'nullable',
                'uuid',
                function (string $attribute, mixed $value, Closure $fail) use ($companyId): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (! Account::query()
                        ->where('company_id', $companyId)
                        ->whereKey($value)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->exists()) {
                        $fail('Selected payment account was not found.');
                    }
                },
            ],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $visaVendor = $this->filled('visa_vendor_id');
            $hotelVendor = $this->filled('hotel_vendor_id');
            if ($this->input('direction') === GroupPayment::DIRECTION_RECEIVED && ! $this->filled('agent_id')) {
                $validator->errors()->add('agent_id', 'Select the agent who made the payment.');
            }
            if ($this->input('direction') === GroupPayment::DIRECTION_SENT && $this->filled('agent_id')) {
                $validator->errors()->add('agent_id', 'Sent payments cannot have an agent payer.');
            }
            if ($this->input('direction') === GroupPayment::DIRECTION_SENT && ($visaVendor === $hotelVendor)) {
                $validator->errors()->add('vendor_id', 'Select one vendor for a sent payment.');
            }
            if ($this->input('direction') === GroupPayment::DIRECTION_RECEIVED && ($visaVendor || $hotelVendor)) {
                $validator->errors()->add('vendor_id', 'Received payments cannot have a vendor payee.');
            }
        }];
    }
}
