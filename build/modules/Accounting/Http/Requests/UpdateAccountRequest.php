<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends BaseFormRequest
{
    private array $types = [
        'asset', 'liability', 'equity', 'revenue', 'expense', 'cogs', 'other_income', 'other_expense',
    ];

    private array $foreignCapableSubtypes = [
        'bank', 'cash', 'accounts_receivable', 'accounts_payable', 'credit_card',
        'other_current_asset', 'other_asset', 'other_current_liability', 'other_liability',
    ];

    private array $subtypeMap = [
        'asset' => ['bank', 'cash', 'accounts_receivable', 'other_current_asset', 'inventory', 'fixed_asset', 'other_asset'],
        'liability' => ['accounts_payable', 'credit_card', 'other_current_liability', 'other_liability', 'loan_payable'],
        'equity' => ['equity', 'retained_earnings'],
        'revenue' => ['revenue'],
        'expense' => ['expense'],
        'cogs' => ['cogs'],
        'other_income' => ['other_income'],
        'other_expense' => ['other_expense'],
    ];

    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::ACCOUNT_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyContext = app(CompanyContextService::class);
        $companyId = $companyContext->getCompanyId();
        $baseCurrency = $companyContext->getCompany()?->base_currency;

        $accountId = $this->route('account');

        $codeRule = Rule::unique('acct.accounts', 'code')
            ->ignore($accountId)
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        return [
            'parent_id' => ['nullable', 'uuid', Rule::exists('acct.accounts', 'id')->where('company_id', $companyId)],
            'code' => ['required', 'string', 'max:50', $codeRule],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in($this->types)],
            'subtype' => ['required', 'string', 'max:50', function ($attr, $value, $fail) {
                $type = $this->input('type');
                if (!$type || !isset($this->subtypeMap[$type]) || !in_array($value, $this->subtypeMap[$type], true)) {
                    $fail('invalid subtype for type');
                }
            }],
            'normal_balance' => ['required', Rule::in(['debit', 'credit']), function ($attr, $value, $fail) {
                $type = $this->input('type');
                if ($type && in_array($type, ['asset', 'expense', 'cogs'], true) && $value !== 'debit') {
                    $fail('normal_balance must be debit for asset/expense/cogs');
                }
                if ($type && in_array($type, ['liability', 'equity', 'revenue', 'other_income', 'other_expense'], true) && $value !== 'credit') {
                    $fail('normal_balance must be credit for liability/equity/revenue/other_income/other_expense');
                }
            }],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                'uppercase',
                function ($attr, $value, $fail) use ($baseCurrency) {
                    $subtype = $this->input('subtype');
                    $type = $this->input('type');
                    if (!in_array($subtype, $this->foreignCapableSubtypes, true)) {
                        if ($value) {
                            $fail('currency must be null for base-only account types');
                        }
                        return;
                    }
                    if ($value && $baseCurrency && $value !== $baseCurrency) {
                        $fail('currency must match company base currency for now');
                    }
                },
            ],
            'is_active' => ['boolean'],
            'is_system' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
