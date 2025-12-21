<?php

namespace App\Modules\Accounting\Actions\Account;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'name' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3|uppercase',
            'parent_id' => 'nullable|uuid',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::ACCOUNT_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $account = Account::where('company_id', $company->id)->findOrFail($params['id']);

        if ($account->is_system && isset($params['is_active']) === false) {
            throw new \InvalidArgumentException('System account can only toggle active flag');
        }

        $update = [];
        foreach (['name', 'description', 'is_active'] as $field) {
            if (array_key_exists($field, $params)) {
                $update[$field] = $params[$field];
            }
        }

        if (array_key_exists('parent_id', $params)) {
            if ($params['parent_id']) {
                $parent = Account::where('company_id', $company->id)->findOrFail($params['parent_id']);
                if ($parent->type !== $account->type) {
                    throw new \InvalidArgumentException('Parent type must match account type');
                }
            }
            $update['parent_id'] = $params['parent_id'];
        }

        if (array_key_exists('currency', $params)) {
            $incoming = $params['currency'] ?: null;
            $current = $account->currency ?: null;

            if ($incoming !== $current) {
                $hasPostedLines = DB::table('acct.journal_entries')
                    ->where('company_id', $company->id)
                    ->where('account_id', $account->id)
                    ->exists();
                if ($hasPostedLines) {
                    throw new \InvalidArgumentException('Account currency cannot be changed after postings exist');
                }
            }

            $update['currency'] = $params['currency'];
        }

        if ($account->is_system && count(array_diff(array_keys($update), ['is_active'])) > 0) {
            throw new \InvalidArgumentException('Cannot edit system account fields');
        }

        if (($update['is_active'] ?? true) === false) {
            $usedInCompanyDefaults = DB::table('auth.companies')
                ->where('id', $company->id)
                ->where(function ($q) use ($account) {
                    $q->where('ar_account_id', $account->id)
                        ->orWhere('ap_account_id', $account->id)
                        ->orWhere('income_account_id', $account->id)
                        ->orWhere('expense_account_id', $account->id)
                        ->orWhere('bank_account_id', $account->id)
                        ->orWhere('retained_earnings_account_id', $account->id)
                        ->orWhere('sales_tax_payable_account_id', $account->id)
                        ->orWhere('purchase_tax_receivable_account_id', $account->id);
                })
                ->exists();
            if ($usedInCompanyDefaults) {
                throw new \InvalidArgumentException('Cannot deactivate a company default account');
            }
        }

        $update['updated_by_user_id'] = Auth::id();

        $account->fill($update)->save();

        return [
            'message' => "Account {$account->code} updated",
            'data' => ['id' => $account->id],
        ];
    }
}
