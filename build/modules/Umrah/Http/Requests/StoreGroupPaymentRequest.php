<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\GroupPayment;
use App\Services\CompanyContextService;
use Closure;
use Illuminate\Validation\Rule;

class StoreGroupPaymentRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_PAYMENT_CREATE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'payment_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(GroupPayment::class, 'payment_number', 'This payment number is already used.'),
            ],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
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
}
