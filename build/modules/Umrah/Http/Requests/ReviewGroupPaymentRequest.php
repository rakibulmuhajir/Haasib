<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Models\CompanyCurrency;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\GroupPayment;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewGroupPaymentRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_PAYMENT_APPROVE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $baseCurrency = app(CompanyContextService::class)->getCompany()?->base_currency;
        $payment = $this->payment();
        $correctedCurrency = strtoupper((string) $this->input('currency', $payment?->currency));

        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_remarks' => ['nullable', 'string', 'max:2000'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'method' => ['nullable', Rule::in(array_keys(GroupPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:255'],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                'uppercase',
                function (string $attribute, mixed $value, Closure $fail) use ($baseCurrency, $companyId): void {
                    if ($value === null || $value === $baseCurrency) {
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
                Rule::requiredIf(fn () => $this->input('decision') === 'approve' && $correctedCurrency !== $baseCurrency),
                Rule::prohibitedIf(fn () => $correctedCurrency === $baseCurrency && $this->filled('exchange_rate')),
            ],
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
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('decision') === 'reject' && ! $this->filled('review_remarks')) {
                $validator->errors()->add('review_remarks', 'Remarks are required to reject a payment.');

                return;
            }

            if ($this->input('decision') !== 'approve') {
                return;
            }

            $payment = $this->payment();
            if (! $payment) {
                return;
            }

            $corrected = false;
            foreach (['amount', 'payment_date', 'method', 'reference', 'currency', 'exchange_rate', 'account_id'] as $field) {
                if (! $this->has($field)) {
                    continue;
                }

                $incoming = $this->input($field);
                $stored = $field === 'payment_date'
                    ? optional($payment->payment_date)->toDateString()
                    : $payment->{$field};

                if ($field === 'currency' && $incoming !== null) {
                    $incoming = strtoupper($incoming);
                }

                $normalizedIncoming = $incoming === '' ? null : $incoming;
                $normalizedStored = $stored === '' ? null : $stored;

                if (is_numeric($normalizedIncoming) && is_numeric($normalizedStored)) {
                    $corrected = $corrected || (round((float) $normalizedIncoming, 6) !== round((float) $normalizedStored, 6));
                } else {
                    $corrected = $corrected || ((string) $normalizedIncoming !== (string) $normalizedStored);
                }
            }

            if ($corrected && ! $this->filled('review_remarks')) {
                $validator->errors()->add('review_remarks', 'Remarks are required when correcting the submitted details.');
            }
        }];
    }

    private function payment(): ?GroupPayment
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $paymentId = $this->route('payment');

        if (! $companyId || ! $paymentId) {
            return null;
        }

        return GroupPayment::where('company_id', $companyId)->find($paymentId);
    }
}
