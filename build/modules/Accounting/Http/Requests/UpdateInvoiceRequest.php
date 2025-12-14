<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'required', 'uuid', 'exists:acct.customers,id'],
            'line_items' => ['sometimes', 'required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.income_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->where('type', 'revenue')
                    ->where('is_active', true)),
            ],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'uppercase'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'description' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'invoice_date' => ['sometimes', 'required', 'date'],
            'ar_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->where('subtype', 'accounts_receivable')
                    ->where('is_active', true)),
            ],
        ];
    }
}
