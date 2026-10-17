<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class UpdateQuickEntryDefaultsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::ACCOUNT_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        // An active cash or bank account of this company, or nothing at all.
        $cashOrBank = fn () => Rule::exists(Account::class, 'id')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereIn('subtype', ['cash', 'bank'])
            ->whereNull('deleted_at');

        return [
            'receive_account_id' => ['nullable', 'uuid', $cashOrBank()],
            'pay_account_id' => ['nullable', 'uuid', $cashOrBank()],
        ];
    }

    public function messages(): array
    {
        return [
            'receive_account_id.exists' => 'Choose an active cash or bank account.',
            'pay_account_id.exists' => 'Choose an active cash or bank account.',
        ];
    }
}
