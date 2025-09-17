<?php

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Or add specific authorization logic, e.g., $this->user()->can('create', Payment::class)
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'payment_number' => 'required|string|max:50',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency_id' => 'required|exists:currencies,id',
            'payment_method' => 'required|string|max:50',
            'reference_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'auto_allocate' => 'boolean',
            'invoice_allocations' => 'sometimes|array',
            'invoice_allocations.*.invoice_id' => 'required_with:invoice_allocations|exists:invoices,id',
            'invoice_allocations.*.amount' => 'required_with:invoice_allocations|numeric|min:0.01',
        ];
    }
}
