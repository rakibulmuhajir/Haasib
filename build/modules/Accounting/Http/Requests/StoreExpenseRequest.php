<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Services\TransactionAttachmentService;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::EXPENSE_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(\App\Services\CurrentCompany::class)->get()?->id;

        return [
            'date' => ['required', 'date'],
            'account_id' => ['required', 'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('type', 'expense')->where('is_active', true))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_from_account_id' => ['required', 'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereIn('subtype', ['cash', 'bank'])->where('is_active', true))],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            // The bill behind the expense. Optional, because a tea-and-biscuits entry has
            // no paper, but an electricity bill does and the books are worth more with it.
            'attachment' => ['nullable', 'file', 'max:'.TransactionAttachmentService::MAX_KILOBYTES,
                'mimes:'.implode(',', TransactionAttachmentService::ACCEPTED)],
        ];
    }
}
