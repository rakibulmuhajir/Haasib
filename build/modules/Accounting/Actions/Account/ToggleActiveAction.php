<?php

namespace App\Modules\Accounting\Actions\Account;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;

class ToggleActiveAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'is_active' => 'required|boolean',
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

        if ($account->is_active && $params['is_active'] === false) {
            $hasPostedLines = DB::table('acct.journal_entries')
                ->where('company_id', $company->id)
                ->where('account_id', $account->id)
                ->exists();
            if ($hasPostedLines) {
                throw new \InvalidArgumentException('Cannot deactivate account with posted journal entries');
            }

            $usedInPostingTemplates = DB::table('acct.posting_template_lines as l')
                ->join('acct.posting_templates as t', 't.id', '=', 'l.template_id')
                ->where('t.company_id', $company->id)
                ->whereNull('t.deleted_at')
                ->where('l.account_id', $account->id)
                ->exists();
            if ($usedInPostingTemplates) {
                throw new \InvalidArgumentException('Cannot deactivate account used in posting templates');
            }

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

        $account->is_active = $params['is_active'];
        $account->save();

        return [
            'message' => "Account {$account->code} is now " . ($account->is_active ? 'active' : 'inactive'),
        ];
    }
}
