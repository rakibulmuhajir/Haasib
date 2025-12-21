<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class UpdatePostingTemplateRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines', []);
        if (is_array($lines)) {
            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                if (($line['account_id'] ?? null) === '') {
                    $lines[$index]['account_id'] = null;
                }
            }
        }

        $this->merge([
            'effective_to' => $this->input('effective_to') === '' ? null : $this->input('effective_to'),
            'lines' => $lines,
        ]);
    }

    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::POSTING_TEMPLATE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.role' => ['required', 'string'],
            'lines.*.account_id' => ['nullable', 'uuid'],
        ];
    }
}
