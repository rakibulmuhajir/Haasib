<?php

namespace App\Http\Requests\JournalEntries;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreJournalEntryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('ledger.entries.create') && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'reference' => 'nullable|string|max:50',
            'description' => 'required|string|max:500',
            'batch_id' => [
                'nullable',
                'uuid',
                Rule::exists('journal_batches', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId())
                          ->where('status', 'open');
                })
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')
            ],
            'journal_lines' => 'required|array|min:2',
            'journal_lines.*.account_id' => [
                'required',
                'uuid',
                Rule::exists('accounts', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId())
                          ->where('active', true);
                })
            ],
            'journal_lines.*.description' => 'nullable|string|max:255',
            'journal_lines.*.debit' => 'required|numeric|min:0|max:999999999.99',
            'journal_lines.*.credit' => 'required|numeric|min:0|max:999999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Journal entry date is required',
            'date.date' => 'Journal entry date must be a valid date',
            'description.required' => 'Description is required',
            'description.max' => 'Description cannot exceed 500 characters',
            'batch_id.exists' => 'Selected journal batch is invalid or closed',
            'currency.required' => 'Currency is required',
            'currency.exists' => 'Invalid currency selected',
            'journal_lines.required' => 'At least 2 journal lines are required',
            'journal_lines.min' => 'At least 2 journal lines are required',
            'journal_lines.*.account_id.required' => 'Account is required for all journal lines',
            'journal_lines.*.account_id.exists' => 'Selected account is invalid or inactive',
            'journal_lines.*.debit.min' => 'Debit amount cannot be negative',
            'journal_lines.*.credit.min' => 'Credit amount cannot be negative',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that each line has either debit or credit, not both
            foreach ($this->input('journal_lines', []) as $index => $line) {
                if (($line['debit'] > 0 && $line['credit'] > 0) ||
                    ($line['debit'] == 0 && $line['credit'] == 0)) {
                    $validator->errors()->add("journal_lines.{$index}", 
                        'Each line must have either a debit or credit amount, not both and not zero.');
                }
            }

            // Validate that the entry balances
            $totalDebits = collect($this->input('journal_lines', []))->sum('debit');
            $totalCredits = collect($this->input('journal_lines', []))->sum('credit');

            if (abs($totalDebits - $totalCredits) > 0.01) {
                $validator->errors()->add('balance', 
                    "Journal entry must balance. Debits: {$totalDebits}, Credits: {$totalCredits}");
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => $this->input('reference') ?: $this->generateReference(),
        ]);
    }

    private function generateReference(): string
    {
        $date = now();
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');
        $random = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);

        return "JE{$year}{$month}{$day}{$random}";
    }
}