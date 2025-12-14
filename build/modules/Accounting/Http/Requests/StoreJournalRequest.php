<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreJournalRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::JOURNAL_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'posting_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'post' => ['nullable', 'boolean'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.account_id' => ['required', 'uuid', 'exists:acct.accounts,id'],
            'entries.*.type' => ['required', 'in:debit,credit'],
            'entries.*.amount' => ['required', 'numeric', 'min:0.01'],
            'entries.*.description' => ['nullable', 'string'],
        ];
    }
}
