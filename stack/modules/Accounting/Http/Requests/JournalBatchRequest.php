<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JournalBatchRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'journal_entry_ids' => 'required|array|min:1',
            'journal_entry_ids.*' => 'uuid|exists:journal_entries,id',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Batch name is required.',
            'name.max' => 'Batch name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'journal_entry_ids.required' => 'At least one journal entry must be selected.',
            'journal_entry_ids.min' => 'At least one journal entry must be selected.',
            'journal_entry_ids.*.uuid' => 'Invalid journal entry format.',
            'journal_entry_ids.*.exists' => 'One or more selected journal entries do not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'journal_entry_ids' => 'journal entries',
        ];
    }
}
