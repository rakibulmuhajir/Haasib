<?php

namespace App\Http\Requests\Ledger;

use App\Models\BankStatementLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBankReconciliationMatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'statement_line_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('ops.bank_statement_lines', 'id'),
            ],
            'source_type' => [
                'required',
                'string',
                Rule::in(['ledger.journal_entry', 'acct.payment', 'acct.credit_note']),
            ],
            'source_id' => [
                'required',
                'string',
                'uuid',
            ],
            'amount' => [
                'required',
                'numeric',
                'between:-999999999.99,999999999.99',
                'not_in:0',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'statement_line_id.required' => 'Please select a statement line.',
            'statement_line_id.exists' => 'The selected statement line is invalid.',
            'source_type.required' => 'Please select a source type.',
            'source_type.in' => 'The selected source type is invalid.',
            'source_id.required' => 'Please select a source transaction.',
            'amount.required' => 'Please enter an amount.',
            'amount.numeric' => 'The amount must be a valid number.',
            'amount.between' => 'The amount is out of valid range.',
            'amount.not_in' => 'The amount cannot be zero.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'statement_line_id' => 'statement line',
            'source_type' => 'source type',
            'source_id' => 'source transaction',
            'amount' => 'amount',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateMatchLogic();
            $this->validateSourceExists();
        });
    }

    /**
     * Validate match logic and consistency.
     */
    private function validateMatchLogic(): void
    {
        $statementLineId = $this->input('statement_line_id');
        $amount = (float) $this->input('amount');

        if ($statementLineId) {
            $statementLine = BankStatementLine::find($statementLineId);

            if ($statementLine) {
                // Check if amount is reasonable
                $statementAmount = abs($statementLine->amount);

                if (abs($amount - $statementAmount) > 0.01) {
                    // Allow some tolerance for rounding differences
                    $tolerance = $statementAmount * 0.05; // 5% tolerance

                    if (abs($amount - $statementAmount) > $tolerance) {
                        $validator->errors()->add('amount',
                            'Match amount is significantly different from statement line amount.');
                    }
                }

                // Check if statement line is already matched
                if ($statementLine->isMatched()) {
                    $validator->errors()->add('statement_line_id',
                        'This statement line is already matched to another transaction.');
                }
            }
        }
    }

    /**
     * Validate that the source transaction exists.
     */
    private function validateSourceExists(): void
    {
        $sourceType = $this->input('source_type');
        $sourceId = $this->input('source_id');

        if (! $sourceType || ! $sourceId) {
            return;
        }

        $source = $this->findSourceModel($sourceType, $sourceId);

        if (! $source) {
            $validator->errors()->add('source_id',
                'The selected source transaction does not exist.');
        }
    }

    /**
     * Find the source model based on type and ID.
     */
    private function findSourceModel(string $sourceType, string $sourceId)
    {
        switch ($sourceType) {
            case 'ledger.journal_entry':
                return \App\Models\JournalEntry::find($sourceId);
            case 'acct.payment':
                return \App\Models\Payment::find($sourceId);
            case 'acct.credit_note':
                return \App\Models\CreditNote::find($sourceId);
            default:
                return null;
        }
    }

    /**
     * Get the statement line model.
     */
    public function getStatementLine(): BankStatementLine
    {
        return BankStatementLine::findOrFail($this->input('statement_line_id'));
    }

    /**
     * Check if the statement line belongs to the same company as the current user.
     */
    public function validateCompanyContext(): bool
    {
        $statementLine = $this->getStatementLine();
        $reconciliation = $this->route('reconciliation');

        if (! $reconciliation) {
            return false;
        }

        return $statementLine->company_id === $reconciliation->company_id &&
               $reconciliation->company_id === auth()->user()->current_company_id;
    }
}
