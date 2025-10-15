<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_type' => [
                'required',
                'string',
                'in:csv,json,manual',
            ],
            'file' => [
                'required_if:source_type,csv',
                'file',
                'mimes:csv,txt',
                'max:5120', // 5MB max
            ],
            'entries' => [
                'required_if:source_type,json,manual',
                'array',
                'min:1',
                'max:1000', // Maximum 1000 entries at once
            ],
            'entries.*.name' => [
                'required',
                'string',
                'max:255',
            ],
            'entries.*.email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'entries.*.phone' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[\d\s\-\+\(\)]+$/',
            ],
            'entries.*.address_line_1' => [
                'nullable',
                'string',
                'max:255',
            ],
            'entries.*.address_line_2' => [
                'nullable',
                'string',
                'max:255',
            ],
            'entries.*.city' => [
                'nullable',
                'string',
                'max:100',
            ],
            'entries.*.state' => [
                'nullable',
                'string',
                'max:100',
            ],
            'entries.*.postal_code' => [
                'nullable',
                'string',
                'max:20',
            ],
            'entries.*.country' => [
                'nullable',
                'string',
                'size:2',
                'exists:countries,code',
            ],
            'entries.*.tax_id' => [
                'nullable',
                'string',
                'max:50',
            ],
            'entries.*.website' => [
                'nullable',
                'url',
                'max:255',
            ],
            'entries.*.payment_terms' => [
                'nullable',
                'integer',
                'min:0',
                'max:365',
            ],
            'entries.*.credit_limit' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'entries.*.currency' => [
                'nullable',
                'string',
                'size:3',
                'exists:currencies,code',
            ],
            'entries.*.notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'entries.*.custom_fields' => [
                'nullable',
                'array',
            ],
            'options' => [
                'nullable',
                'array',
            ],
            'options.skip_duplicates' => [
                'nullable',
                'boolean',
            ],
            'options.update_existing' => [
                'nullable',
                'boolean',
            ],
            'options.validate_data' => [
                'nullable',
                'boolean',
            ],
            'options.send_welcome' => [
                'nullable',
                'boolean',
            ],
            'options.default_currency' => [
                'nullable',
                'string',
                'size:3',
                'exists:currencies,code',
            ],
            'options.default_payment_terms' => [
                'nullable',
                'integer',
                'min:0',
                'max:365',
            ],
            'options.default_credit_limit' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'source_type.required' => 'Import source type is required.',
            'source_type.in' => 'Invalid source type. Must be csv, json, or manual.',
            'file.required_if' => 'File is required when importing from CSV.',
            'file.mimes' => 'File must be a CSV file.',
            'file.max' => 'File size cannot exceed 5MB.',
            'entries.required_if' => 'Entries are required when importing from JSON or manual input.',
            'entries.min' => 'At least one entry is required.',
            'entries.max' => 'Cannot import more than 1000 entries at once.',
            'entries.*.name.required' => 'Customer name is required for all entries.',
            'entries.*.name.max' => 'Customer name cannot exceed 255 characters.',
            'entries.*.email.email' => 'Invalid email format.',
            'entries.*.phone.regex' => 'Phone number format is invalid.',
            'entries.*.country.exists' => 'Invalid country code.',
            'entries.*.website.url' => 'Website must be a valid URL.',
            'entries.*.currency.exists' => 'Invalid currency code.',
            'options.default_currency.exists' => 'Invalid default currency.',
            'options.default_payment_terms.min' => 'Payment terms must be at least 0 days.',
            'options.default_payment_terms.max' => 'Payment terms cannot exceed 365 days.',
            'options.default_credit_limit.min' => 'Credit limit must be at least 0.',
            'options.default_credit_limit.max' => 'Credit limit cannot exceed 999,999,999.99.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $options = $this->input('options', []);

        // Set default values for boolean options
        $options['skip_duplicates'] = $options['skip_duplicates'] ?? true;
        $options['update_existing'] = $options['update_existing'] ?? false;
        $options['validate_data'] = $options['validate_data'] ?? true;
        $options['send_welcome'] = $options['send_welcome'] ?? false;

        $this->merge([
            'options' => $options,
            'entries' => $this->sanitizeEntries($this->input('entries', [])),
        ]);
    }

    /**
     * Sanitize and normalize customer entries.
     */
    private function sanitizeEntries(array $entries): array
    {
        return array_map(function ($entry) {
            // Trim string values
            foreach ($entry as $key => $value) {
                if (is_string($value)) {
                    $entry[$key] = trim($value);
                }
            }

            // Normalize country code to uppercase
            if (! empty($entry['country'])) {
                $entry['country'] = strtoupper($entry['country']);
            }

            // Normalize currency code to uppercase
            if (! empty($entry['currency'])) {
                $entry['currency'] = strtoupper($entry['currency']);
            }

            // Convert numeric strings to proper numbers
            if (! empty($entry['credit_limit']) && is_numeric($entry['credit_limit'])) {
                $entry['credit_limit'] = (float) $entry['credit_limit'];
            }

            if (! empty($entry['payment_terms']) && is_numeric($entry['payment_terms'])) {
                $entry['payment_terms'] = (int) $entry['payment_terms'];
            }

            // Validate and format website URL
            if (! empty($entry['website'])) {
                $website = $entry['website'];
                if (! str_starts_with($website, ['http://', 'https://'])) {
                    $entry['website'] = 'https://'.$website;
                }
            }

            return $entry;
        }, $entries);
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'source_type' => 'import source type',
            'file' => 'import file',
            'entries' => 'customer entries',
            'options.skip_duplicates' => 'skip duplicates option',
            'options.update_existing' => 'update existing option',
            'options.validate_data' => 'validate data option',
            'options.send_welcome' => 'send welcome emails option',
            'options.default_currency' => 'default currency',
            'options.default_payment_terms' => 'default payment terms',
            'options.default_credit_limit' => 'default credit limit',
            'notes' => 'import notes',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->source_type === 'csv' && ! $this->hasFile('file')) {
                $validator->errors()->add('file', 'CSV file is required for CSV imports.');
            }

            if ($this->source_type === 'json' && empty($this->input('entries'))) {
                $validator->errors()->add('entries', 'Entries are required for JSON imports.');
            }

            // Check file size for CSV uploads
            if ($this->hasFile('file') && $this->file('file')->getSize() > 5 * 1024 * 1024) {
                $validator->errors()->add('file', 'File size cannot exceed 5MB.');
            }
        });
    }
}
