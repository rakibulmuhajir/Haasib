<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JournalEntrySearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->companies()->where('companies.id', $this->validated_company_id)->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:draft,submitted,approved,posted,void',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'account_id' => 'nullable|uuid|exists:accounts,id',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0|gt:min_amount',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'sort_by' => 'nullable|in:date,amount,description,status,created_at',
            'sort_order' => 'nullable|in:asc,desc',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Search term cannot exceed 100 characters.',
            'status.in' => 'Invalid status value.',
            'date_to.after_or_equal' => 'End date must be after or equal to start date.',
            'account_id.uuid' => 'Invalid account format.',
            'account_id.exists' => 'Selected account does not exist.',
            'min_amount.numeric' => 'Minimum amount must be a number.',
            'max_amount.numeric' => 'Maximum amount must be a number.',
            'max_amount.gt' => 'Maximum amount must be greater than minimum amount.',
            'per_page.max' => 'Cannot display more than 100 items per page.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_order.in' => 'Sort order must be ascending or descending.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'per_page' => $this->per_page ?: 25,
            'sort_by' => $this->sort_by ?: 'created_at',
            'sort_order' => $this->sort_order ?: 'desc',
        ]);
    }
}
