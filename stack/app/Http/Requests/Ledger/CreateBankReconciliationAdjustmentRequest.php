<?php

namespace App\Http\Requests\Ledger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateBankReconciliationAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()->can('bank_reconciliation_adjustments.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'adjustment_type' => [
                'required',
                'string',
                Rule::in(['bank_fee', 'interest', 'write_off', 'timing']),
            ],
            'amount' => [
                'required',
                'numeric',
                'between:-999999999.99,999999999.99',
                'not_in:0',
            ],
            'description' => [
                'required',
                'string',
                'max:500',
                'min:3',
            ],
            'statement_line_id' => [
                'nullable',
                'string',
                'uuid',
                'exists:ops.bank_statement_lines,id',
            ],
            'post_journal_entry' => [
                'boolean',
            ],
            'journal_entry_id' => [
                'nullable',
                'string',
                'uuid',
                'exists:ledger.journal_entries,id',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'adjustment_type.required' => 'Please select an adjustment type.',
            'adjustment_type.in' => 'The selected adjustment type is invalid.',
            'amount.required' => 'Please enter an adjustment amount.',
            'amount.numeric' => 'The amount must be a valid number.',
            'amount.between' => 'The amount is out of valid range.',
            'amount.not_in' => 'The amount cannot be zero.',
            'description.required' => 'Please enter a description.',
            'description.max' => 'The description cannot exceed 500 characters.',
            'description.min' => 'The description must be at least 3 characters.',
            'statement_line_id.exists' => 'The selected statement line is invalid.',
            'journal_entry_id.exists' => 'The selected journal entry is invalid.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'adjustment_type' => 'adjustment type',
            'amount' => 'amount',
            'description' => 'description',
            'statement_line_id' => 'statement line',
            'post_journal_entry' => 'post journal entry',
            'journal_entry_id' => 'journal entry',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateAdjustmentLogic();
            $this->validateStatementLineContext();
            $this->validateJournalEntryContext();
        });
    }

    /**
     * Validate adjustment logic based on type.
     */
    private function validateAdjustmentLogic(): void
    {
        $adjustmentType = $this->input('adjustment_type');
        $amount = (float) $this->input('amount');

        switch ($adjustmentType) {
            case 'bank_fee':
            case 'write_off':
                if ($amount > 0) {
                    $validator->errors()->add('amount',
                        ucfirst($adjustmentType).' adjustments must be negative amounts.');
                }
                break;
            case 'interest':
                if ($amount < 0) {
                    $validator->errors()->add('amount',
                        'Interest adjustments must be positive amounts.');
                }
                break;
            case 'timing':
                // Timing adjustments can be positive or negative
                break;
        }
    }

    /**
     * Validate that the statement line belongs to the same reconciliation.
     */
    private function validateStatementLineContext(): void
    {
        $statementLineId = $this->input('statement_line_id');

        if (! $statementLineId) {
            return;
        }

        // Get the reconciliation from route parameters
        $reconciliation = $this->route('reconciliation');

        if (! $reconciliation) {
            return;
        }

        $statementLine = \App\Models\BankStatementLine::find($statementLineId);

        if (! $statementLine || $statementLine->statement_id !== $reconciliation->statement_id) {
            $validator->errors()->add('statement_line_id',
                'The selected statement line does not belong to this reconciliation.');
        }
    }

    /**
     * Validate journal entry context.
     */
    private function validateJournalEntryContext(): void
    {
        $journalEntryId = $this->input('journal_entry_id');

        if (! $journalEntryId) {
            return;
        }

        $reconciliation = $this->route('reconciliation');

        if (! $reconciliation) {
            return;
        }

        $journalEntry = \App\Models\JournalEntry::find($journalEntryId);

        if (! $journalEntry || $journalEntry->company_id !== $reconciliation->company_id) {
            $validator->errors()->add('journal_entry_id',
                'The selected journal entry does not belong to the same company.');
        }
    }

    /**
     * Get the validated data with processed values.
     */
    public function getValidatedData(): array
    {
        $data = parent::validated();

        // Apply amount sign based on adjustment type
        $data['amount'] = $this->applyAmountSign(
            $data['adjustment_type'],
            (float) $data['amount']
        );

        return $data;
    }

    /**
     * Apply the correct amount sign based on adjustment type.
     */
    private function applyAmountSign(string $adjustmentType, float $amount): float
    {
        switch ($adjustmentType) {
            case 'bank_fee':
            case 'write_off':
                return -abs($amount);
            case 'interest':
                return abs($amount);
            case 'timing':
                return $amount;
            default:
                return $amount;
        }
    }
}
