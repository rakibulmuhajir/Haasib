<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use Illuminate\Validation\Rule;

class StoreBankTransactionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BANK_ACCOUNT_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = $this->companyId();

        return [
            'kind' => ['required', Rule::in(['deposit', 'withdrawal', 'transfer', 'charge'])],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'cash_account_id' => ['required_if:kind,deposit,withdrawal', 'nullable', 'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('subtype', 'cash')->where('is_active', true)->whereNull('deleted_at'))],
            'bank_account_id' => ['required_if:kind,deposit,withdrawal,charge', 'nullable', 'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('subtype', 'bank')->where('is_active', true)->whereNull('deleted_at'))],
            'from_bank_account_id' => ['required_if:kind,transfer', 'nullable', 'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('subtype', 'bank')->where('is_active', true)->whereNull('deleted_at'))],
            'to_bank_account_id' => ['required_if:kind,transfer', 'nullable', 'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('subtype', 'bank')->where('is_active', true)->whereNull('deleted_at'))],
            'expense_account_id' => ['required_if:kind,charge', 'nullable', 'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('type', 'expense')->where('is_active', true)->whereNull('deleted_at'))],
        ];
    }

    private function companyId(): ?string
    {
        return app(\App\Services\CurrentCompany::class)->get()?->id;
    }
}
