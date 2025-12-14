<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICE_CREATE)
            && $this->hasCompanyPermission(Permissions::PAYMENT_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'sale_date' => ['required', 'date'],
            'deposit_account_id' => [
                'required',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true)),
            ],

            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:500'],
            'line_items.*.amount' => ['required', 'numeric', 'min:0.01'],
            'line_items.*.income_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->where('type', 'revenue')
                    ->where('is_active', true)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('line_items', []);
            if (!is_array($items)) {
                return;
            }

            $sum = 0.0;
            foreach ($items as $item) {
                $sum += (float) ($item['amount'] ?? 0);
            }

            if ($sum <= 0) {
                $validator->errors()->add('line_items', 'Enter at least one line item amount.');
            }
        });
    }
}

