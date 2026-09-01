<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Models\CompanyCurrency;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRefundRequest extends UmrahFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'refund_number' => $this->input('refund_number'),
        ]);
    }

    protected function permission(): string
    {
        return Permissions::UMRAH_REFUND_CREATE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $baseCurrency = app(CompanyContextService::class)->getCompany()?->base_currency;
        $partyType = $this->input('party_type');

        return [
            'refund_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(Refund::class, 'refund_number', 'This refund number is already used.'),
            ],
            'party_type' => ['required', Rule::in(array_keys(Refund::PARTY_TYPES))],
            'party_id' => [
                'required',
                'uuid',
                function (string $attribute, mixed $value, Closure $fail) use ($companyId, $partyType): void {
                    $exists = match ($partyType) {
                        Refund::PARTY_AGENT => Agent::where('company_id', $companyId)->whereKey($value)->whereNull('deleted_at')->exists(),
                        Refund::PARTY_VISA_VENDOR => VisaVendor::where('company_id', $companyId)->whereKey($value)->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->whereNull('deleted_at')->exists(),
                        Refund::PARTY_TRANSPORT_VENDOR => VisaVendor::where('company_id', $companyId)->whereKey($value)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->whereNull('deleted_at')->exists(),
                        Refund::PARTY_HOTEL_VENDOR => HotelVendor::where('company_id', $companyId)->whereKey($value)->whereNull('deleted_at')->exists(),
                        default => false,
                    };

                    if (! $exists) {
                        $fail('The selected party was not found.');
                    }
                },
            ],
            'visa_group_id' => ['nullable', 'uuid', $this->existsForCompany(VisaGroup::class, 'Selected group was not found.')],
            // Party-aware: a supplier can only be refunding what it
            // supplies, and an agent only what their package contained.
            'service' => ['required', Rule::in(array_keys(Refund::servicesFor($partyType)))],
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
            'exchange_rate' => [
                'nullable',
                'numeric',
                'min:0.00000001',
                'decimal:0,8',
                Rule::requiredIf(fn () => $this->input('currency') && $this->input('currency') !== $baseCurrency),
                Rule::prohibitedIf(fn () => $this->input('currency') === $baseCurrency && $this->filled('exchange_rate')),
            ],
            'reason' => ['required', 'string', 'max:2000'],
            // Countable beside the sentence, so the same question can be
            // asked of every refund at once rather than read one by one.
            'reason_category' => ['nullable', Rule::in(array_keys(Refund::reasonsFor($partyType)))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            // A supplier credit names a group too, and for a reason: the
            // trip that supplier billed is the trip whose cost comes down.
            // What differs is what naming it does -- an agent refund draws
            // on that group's payments, a supplier credit lowers its cost.
        }];
    }

    public function messages(): array
    {
        return [
            'service.in' => 'Choose which part of the package this refund pays back.',
        ];
    }
}
