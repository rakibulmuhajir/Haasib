<?php

namespace App\Http\Requests\Ledger;

use App\Models\BankStatement;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ImportBankStatementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::user()->can('bank_statements.import');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'bank_account_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('ledger.chart_of_accounts', 'id')->where(function ($query) {
                    $query->where('company_id', Auth::user()->current_company_id)
                        ->where('account_type', 'asset')
                        ->where('account_subtype', 'bank')
                        ->where('is_active', true);
                }),
            ],
            'statement_file' => [
                'required',
                'file',
                'max:10240', // 10MB
                'mimes:csv,ofx,qfx',
                'mimetypes:text/plain,text/csv,application/x-ofx,application/qfx',
            ],
            'statement_period_start' => [
                'required',
                'date',
                'before_or_equal:statement_period_end',
                'before_or_equal:today',
            ],
            'statement_period_end' => [
                'required',
                'date',
                'after_or_equal:statement_period_start',
                'before_or_equal:today',
            ],
            'opening_balance' => [
                'required',
                'numeric',
                'between:-999999999.99,999999999.99',
            ],
            'closing_balance' => [
                'required',
                'numeric',
                'between:-999999999.99,999999999.99',
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::in(['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'CNY', 'NZD', 'SEK', 'NOK', 'DKK']),
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'bank_account_id.required' => 'Please select a bank account.',
            'bank_account_id.exists' => 'The selected bank account is invalid or not available.',
            'statement_file.required' => 'Please select a statement file to upload.',
            'statement_file.max' => 'The file size must not exceed 10MB.',
            'statement_file.mimes' => 'The file must be a CSV, OFX, or QFX format.',
            'statement_period_start.required' => 'Please enter the statement period start date.',
            'statement_period_start.before_or_equal' => 'The start date must be before or equal to the end date.',
            'statement_period_start.before_or_equal' => 'The start date cannot be in the future.',
            'statement_period_end.required' => 'Please enter the statement period end date.',
            'statement_period_end.after_or_equal' => 'The end date must be after or equal to the start date.',
            'statement_period_end.before_or_equal' => 'The end date cannot be in the future.',
            'opening_balance.required' => 'Please enter the opening balance.',
            'opening_balance.numeric' => 'The opening balance must be a valid number.',
            'opening_balance.between' => 'The opening balance amount is out of range.',
            'closing_balance.required' => 'Please enter the closing balance.',
            'closing_balance.numeric' => 'The closing balance must be a valid number.',
            'closing_balance.between' => 'The closing balance amount is out of range.',
            'currency.required' => 'Please select a currency.',
            'currency.size' => 'The currency code must be exactly 3 characters.',
            'currency.in' => 'The selected currency is not supported.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'bank_account_id' => 'bank account',
            'statement_file' => 'statement file',
            'statement_period_start' => 'period start date',
            'statement_period_end' => 'period end date',
            'opening_balance' => 'opening balance',
            'closing_balance' => 'closing balance',
            'currency' => 'currency',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateDateRange();
            $this->validateBalanceLogic();
            $this->validateDuplicateStatement();
            $this->validateAccountCurrency();
        });
    }

    /**
     * Validate the date range logic.
     */
    private function validateDateRange(): void
    {
        $startDate = $this->input('statement_period_start');
        $endDate = $this->input('statement_period_end');

        if ($startDate && $endDate) {
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);
            $daysDiff = $start->diffInDays($end);

            // Don't allow statement periods longer than 1 year
            if ($daysDiff > 365) {
                $this->validator->errors()->add('statement_period_end',
                    'Statement period cannot exceed 1 year.');
            }

            // Don't allow very short periods (less than 1 day)
            if ($daysDiff < 0) {
                $this->validator->errors()->add('statement_period_end',
                    'End date must be after or equal to start date.');
            }
        }
    }

    /**
     * Validate balance logic.
     */
    private function validateBalanceLogic(): void
    {
        $openingBalance = (float) $this->input('opening_balance');
        $closingBalance = (float) $this->input('closing_balance');

        // Allow zero balances but validate they're not both zero with no file activity
        if ($openingBalance === 0.0 && $closingBalance === 0.0) {
            // This might be valid for a new account, so we'll allow it but add a warning
            // through the controller instead of validation error
        }
    }

    /**
     * Check for duplicate statements.
     */
    private function validateDuplicateStatement(): void
    {
        $accountId = $this->input('bank_account_id');
        $startDate = $this->input('statement_period_start');
        $endDate = $this->input('statement_period_end');
        $file = $this->file('statement_file');

        if ($accountId && $startDate && $endDate && $file) {
            // Generate statement UID to check for duplicates
            $statementUid = hash('sha256', serialize([
                'account_id' => $accountId,
                'period_start' => $startDate,
                'period_end' => $endDate,
                'file_hash' => hash('sha256', $file->get()),
            ]));

            $exists = BankStatement::where('company_id', Auth::user()->current_company_id)
                ->where('ledger_account_id', $accountId)
                ->where('statement_uid', $statementUid)
                ->exists();

            if ($exists) {
                $this->validator->errors()->add('statement_file',
                    'A statement for this period and account with the same file already exists.');
            }
        }
    }

    /**
     * Validate that the selected account supports the selected currency.
     */
    private function validateAccountCurrency(): void
    {
        $accountId = $this->input('bank_account_id');
        $currency = $this->input('currency');

        if ($accountId && $currency) {
            $account = ChartOfAccount::find($accountId);

            if ($account && $account->currency && $account->currency !== $currency) {
                $this->validator->errors()->add('currency',
                    'The selected currency does not match the bank account currency ('.$account->currency.').');
            }
        }
    }

    /**
     * Get additional data for the statement import.
     */
    public function getImportData(): array
    {
        return array_merge($this->validated(), [
            'file_size' => $this->file('statement_file')->getSize(),
            'file_name' => $this->file('statement_file')->getClientOriginalName(),
            'file_mime_type' => $this->file('statement_file')->getMimeType(),
        ]);
    }
}
