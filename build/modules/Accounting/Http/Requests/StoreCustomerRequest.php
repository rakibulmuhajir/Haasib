<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::CUSTOMER_CREATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'base_currency' => ['nullable', 'string', 'size:3', 'uppercase'],
            'payment_terms' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'billing_address' => ['nullable', 'array'],
            'billing_address.street' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['nullable', 'string', 'max:100'],
            'billing_address.state' => ['nullable', 'string', 'max:100'],
            'billing_address.zip' => ['nullable', 'string', 'max:20'],
            'billing_address.country' => ['nullable', 'string', 'max:2'],
            'shipping_address' => ['nullable', 'array'],
            'shipping_address.street' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['nullable', 'string', 'max:100'],
            'shipping_address.state' => ['nullable', 'string', 'max:100'],
            'shipping_address.zip' => ['nullable', 'string', 'max:20'],
            'shipping_address.country' => ['nullable', 'string', 'max:2'],
            'logo_url' => ['nullable', 'string', 'max:500'],
        ];
    }
}
