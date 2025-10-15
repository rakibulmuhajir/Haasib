<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => [
                'required',
                'string',
                'in:csv,xlsx,json',
            ],
            'filters' => [
                'nullable',
                'array',
            ],
            'filters.status' => [
                'nullable',
                'array',
            ],
            'filters.status.*' => [
                'string',
                'in:active,inactive,pending,blocked',
            ],
            'filters.created_after' => [
                'nullable',
                'date',
                'before_or_equal:filters.created_before',
            ],
            'filters.created_before' => [
                'nullable',
                'date',
                'after_or_equal:filters.created_after',
            ],
            'filters.has_invoices' => [
                'nullable',
                'boolean',
            ],
            'filters.has_outstanding_balance' => [
                'nullable',
                'boolean',
            ],
            'filters.country' => [
                'nullable',
                'string',
                'size:2',
                'exists:countries,code',
            ],
            'filters.payment_terms' => [
                'nullable',
                'array',
            ],
            'filters.payment_terms.*' => [
                'integer',
                'min:0',
                'max:365',
            ],
            'filters.min_balance' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'filters.max_balance' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'filters.search' => [
                'nullable',
                'string',
                'max:255',
            ],
            'columns' => [
                'nullable',
                'array',
                'min:1',
            ],
            'columns.*' => [
                'string',
                'in:id,name,email,phone,address_line_1,address_line_2,city,state,postal_code,country,tax_id,website,status,payment_terms,credit_limit,balance,total_invoiced,total_paid,currency,created_at,updated_at,custom_fields',
            ],
            'sort_by' => [
                'nullable',
                'string',
                'in:name,email,created_at,updated_at,balance,total_invoiced',
            ],
            'sort_direction' => [
                'nullable',
                'string',
                'in:asc,desc',
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
            'include_invoices' => [
                'nullable',
                'boolean',
            ],
            'include_payments' => [
                'nullable',
                'boolean',
            ],
            'include_aging' => [
                'nullable',
                'boolean',
            ],
            'options' => [
                'nullable',
                'array',
            ],
            'options.date_format' => [
                'nullable',
                'string',
                'in:Y-m-d,m/d/Y,d/m/Y',
            ],
            'options.currency_format' => [
                'nullable',
                'string',
                'in:symbol,code,both',
            ],
            'options.include_headers' => [
                'nullable',
                'boolean',
            ],
            'options.encoding' => [
                'nullable',
                'string',
                'in:utf-8,latin1',
            ],
            'compress' => [
                'nullable',
                'boolean',
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
            'format.required' => 'Export format is required.',
            'format.in' => 'Invalid export format. Must be csv, xlsx, or json.',
            'filters.status.*.in' => 'Invalid status filter. Must be active, inactive, pending, or blocked.',
            'filters.created_after.before_or_equal' => 'Created after date must be before created before date.',
            'filters.created_before.after_or_equal' => 'Created before date must be after created after date.',
            'filters.country.exists' => 'Invalid country code.',
            'filters.payment_terms.*.min' => 'Payment terms must be at least 0 days.',
            'filters.payment_terms.*.max' => 'Payment terms cannot exceed 365 days.',
            'filters.min_balance.min' => 'Minimum balance must be at least 0.',
            'filters.max_balance.min' => 'Maximum balance must be at least 0.',
            'filters.max_balance.gt' => 'Maximum balance must be greater than minimum balance.',
            'filters.search.max' => 'Search term cannot exceed 255 characters.',
            'columns.min' => 'At least one column must be selected.',
            'columns.*.in' => 'Invalid column selected.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_direction.in' => 'Sort direction must be asc or desc.',
            'limit.min' => 'Limit must be at least 1.',
            'limit.max' => 'Limit cannot exceed 10,000.',
            'options.date_format.in' => 'Invalid date format.',
            'options.currency_format.in' => 'Invalid currency format.',
            'options.encoding.in' => 'Invalid encoding format.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $filters = $this->input('filters', []);
        $options = $this->input('options', []);
        $columns = $this->input('columns', []);

        // Set default values
        $options['date_format'] = $options['date_format'] ?? 'Y-m-d';
        $options['currency_format'] = $options['currency_format'] ?? 'symbol';
        $options['include_headers'] = $options['include_headers'] ?? true;
        $options['encoding'] = $options['encoding'] ?? 'utf-8';

        // Set default columns if none provided
        if (empty($columns)) {
            $columns = [
                'id', 'name', 'email', 'phone', 'address_line_1', 'city',
                'state', 'postal_code', 'country', 'status', 'payment_terms',
                'credit_limit', 'balance', 'currency', 'created_at',
            ];
        }

        // Normalize filters
        if (isset($filters['country'])) {
            $filters['country'] = strtoupper($filters['country']);
        }

        // Set default sort
        $sortBy = $this->input('sort_by', 'name');
        $sortDirection = $this->input('sort_direction', 'asc');

        $this->merge([
            'filters' => $filters,
            'options' => $options,
            'columns' => $columns,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
            'include_invoices' => $this->boolean('include_invoices', false),
            'include_payments' => $this->boolean('include_payments', false),
            'include_aging' => $this->boolean('include_aging', false),
            'compress' => $this->boolean('compress', false),
            'limit' => $this->input('limit', null),
        ]);
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'format' => 'export format',
            'filters.status' => 'status filters',
            'filters.created_after' => 'created after date',
            'filters.created_before' => 'created before date',
            'filters.has_invoices' => 'has invoices filter',
            'filters.has_outstanding_balance' => 'has outstanding balance filter',
            'filters.country' => 'country filter',
            'filters.payment_terms' => 'payment terms filters',
            'filters.min_balance' => 'minimum balance filter',
            'filters.max_balance' => 'maximum balance filter',
            'filters.search' => 'search term',
            'columns' => 'selected columns',
            'sort_by' => 'sort field',
            'sort_direction' => 'sort direction',
            'limit' => 'export limit',
            'include_invoices' => 'include invoices option',
            'include_payments' => 'include payments option',
            'include_aging' => 'include aging data option',
            'options.date_format' => 'date format option',
            'options.currency_format' => 'currency format option',
            'options.include_headers' => 'include headers option',
            'options.encoding' => 'encoding option',
            'compress' => 'compression option',
            'notes' => 'export notes',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that max_balance is greater than min_balance
            $filters = $this->input('filters', []);
            if (isset($filters['min_balance']) && isset($filters['max_balance'])) {
                if ((float) $filters['max_balance'] <= (float) $filters['min_balance']) {
                    $validator->errors()->add('filters.max_balance', 'Maximum balance must be greater than minimum balance.');
                }
            }

            // Validate date range
            if (isset($filters['created_after']) && isset($filters['created_before'])) {
                $after = $filters['created_after'];
                $before = $filters['created_before'];

                if (strtotime($after) > strtotime($before)) {
                    $validator->errors()->add('filters.created_after', 'Created after date must be before created before date.');
                }
            }

            // Validate limit against company size (optional enhancement)
            $limit = $this->input('limit');
            if ($limit && $limit > 5000) {
                // For large exports, require compression
                if (! $this->boolean('compress')) {
                    $validator->errors()->add('limit', 'Exports over 5000 records require compression to be enabled.');
                }
            }

            // Validate column compatibility with format
            $format = $this->input('format');
            $columns = $this->input('columns', []);
            $includeInvoices = $this->boolean('include_invoices');
            $includePayments = $this->boolean('include_payments');
            $includeAging = $this->boolean('include_aging');

            if ($format === 'csv' && ($includeInvoices || $includePayments)) {
                // CSV can handle nested data but might need special handling
                // This is a warning, not an error
            }
        });
    }

    /**
     * Get the export configuration as an array.
     */
    public function getExportConfig(): array
    {
        return [
            'format' => $this->input('format'),
            'filters' => $this->input('filters', []),
            'columns' => $this->input('columns', []),
            'sort_by' => $this->input('sort_by', 'name'),
            'sort_direction' => $this->input('sort_direction', 'asc'),
            'limit' => $this->input('limit'),
            'include_invoices' => $this->boolean('include_invoices'),
            'include_payments' => $this->boolean('include_payments'),
            'include_aging' => $this->boolean('include_aging'),
            'options' => $this->input('options', []),
            'compress' => $this->boolean('compress'),
            'notes' => $this->input('notes'),
            'metadata' => $this->input('metadata', []),
        ];
    }

    /**
     * Check if this is a large export that should be processed in background.
     */
    public function isLargeExport(): bool
    {
        $limit = $this->input('limit', 1000);
        $includeInvoices = $this->boolean('include_invoices');
        $includePayments = $this->boolean('include_payments');
        $includeAging = $this->boolean('include_aging');

        // Consider it large if:
        // - Limit is over 1000 records, OR
        // - Including additional data for more than 500 records
        return $limit > 1000 || (($includeInvoices || $includePayments || $includeAging) && $limit > 500);
    }
}
