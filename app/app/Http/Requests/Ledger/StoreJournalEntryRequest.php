<?php

namespace App\Http\Requests\Ledger;

use Brick\Money\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJournalEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Assuming you have a permission system set up.
        // return $this->user()->can('create', \App\Models\JournalEntry::class);
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $companyId = $this->user()->current_company_id;

        return [
            'description' => 'required|string|max:255',
            'date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => [
                'required',
                'string',
                Rule::exists('ledger.ledger_accounts', 'id')->where('company_id', $companyId),
            ],
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $lines = $validator->safe()->lines ?? [];

                if (empty($lines)) {
                    return;
                }

                // Use the company's base currency for accurate calculations
                $currencyCode = $this->user()->currentCompany->base_currency ?? 'USD';

                $totalDebit = Money::of(0, $currencyCode);
                $totalCredit = Money::of(0, $currencyCode);

                foreach ($lines as $line) {
                    if (($line['debit_amount'] ?? 0) > 0 && ($line['credit_amount'] ?? 0) > 0) {
                        $validator->errors()->add('lines', 'A line cannot have both a debit and a credit amount.');
                        break;
                    }
                    $totalDebit = $totalDebit->plus(Money::of($line['debit_amount'] ?? 0, $currencyCode));
                    $totalCredit = $totalCredit->plus(Money::of($line['credit_amount'] ?? 0, $currencyCode));
                }

                if (! $totalDebit->isEqualTo($totalCredit)) {
                    $validator->errors()->add('lines', 'The journal entry is not balanced. Total debits must equal total credits.');
                }
            },
        ];
    }
}
