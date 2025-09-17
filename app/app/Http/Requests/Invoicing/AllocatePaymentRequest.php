<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class AllocatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->payment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'invoice_allocations' => 'required|array|min:1',
            'invoice_allocations.*.invoice_id' => 'required|exists:invoices,id',
            'invoice_allocations.*.amount' => 'required|numeric|min:0.01',
        ];
    }
}
