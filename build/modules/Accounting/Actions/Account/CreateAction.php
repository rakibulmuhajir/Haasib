<?php

namespace App\Modules\Accounting\Actions\Account;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateAction implements PaletteAction
{
    private array $typeMap = [
        'asset' => ['bank', 'cash', 'accounts_receivable', 'other_current_asset', 'inventory', 'fixed_asset', 'other_asset'],
        'liability' => ['accounts_payable', 'credit_card', 'other_current_liability', 'other_liability', 'loan_payable'],
        'equity' => ['equity', 'retained_earnings'],
        'revenue' => ['revenue'],
        'expense' => ['expense'],
        'cogs' => ['cogs'],
        'other_income' => ['other_income'],
        'other_expense' => ['other_expense'],
    ];

    private array $normalBalance = [
        'asset' => 'debit',
        'expense' => 'debit',
        'cogs' => 'debit',
        'liability' => 'credit',
        'equity' => 'credit',
        'revenue' => 'credit',
        'other_income' => 'credit',
        'other_expense' => 'debit',
    ];

    private array $foreignCapable = [
        'bank', 'cash', 'accounts_receivable', 'accounts_payable', 'credit_card',
        'other_current_asset', 'other_asset', 'other_current_liability', 'other_liability',
    ];

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'subtype' => 'required|string|max:50',
            'currency' => 'nullable|string|size:3|uppercase',
            'parent_id' => 'nullable|uuid',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::ACCOUNT_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        if (!isset($this->typeMap[$params['type']]) || !in_array($params['subtype'], $this->typeMap[$params['type']], true)) {
            throw new \InvalidArgumentException('Subtype does not match type');
        }

        $currency = $params['currency'] ?? null;
        if ($currency && !in_array($params['subtype'], $this->foreignCapable, true)) {
            throw new \InvalidArgumentException('Currency not allowed for this subtype');
        }

        $exists = Account::where('company_id', $company->id)
            ->where('code', $params['code'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('Account code already exists');
        }

        $parentId = $params['parent_id'] ?? null;
        if ($parentId) {
            $parent = Account::where('company_id', $company->id)->findOrFail($parentId);
            if ($parent->type !== $params['type']) {
                throw new \InvalidArgumentException('Parent type must match account type');
            }
        }

        $account = Account::create([
            'company_id' => $company->id,
            'parent_id' => $parentId,
            'code' => $params['code'],
            'name' => $params['name'],
            'type' => $params['type'],
            'subtype' => $params['subtype'],
            'normal_balance' => $this->normalBalance[$params['type']] ?? 'debit',
            'currency' => $currency,
            'is_active' => $params['is_active'] ?? true,
            'is_system' => false,
            'description' => $params['description'] ?? null,
            'created_by_user_id' => Auth::id(),
        ]);

        return [
            'message' => "Account {$account->code} created",
            'data' => ['id' => $account->id],
        ];
    }
}
