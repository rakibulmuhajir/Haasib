<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\Accounting\Http\Requests\UpdateDefaultAccountsRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\PostingTemplateInstaller;
use App\Services\CompanyContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingDefaultsController extends Controller
{
    public function edit(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        if (! $request->user()?->hasCompanyPermission(Permissions::ACCOUNT_UPDATE)) {
            abort(403);
        }

        $accounts = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype']);

        return Inertia::render('accounting/settings/DefaultAccounts', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'defaults' => [
                'ar_account_id' => $company->ar_account_id,
                'ap_account_id' => $company->ap_account_id,
                'income_account_id' => $company->income_account_id,
                'expense_account_id' => $company->expense_account_id,
                'bank_account_id' => $company->bank_account_id,
                'retained_earnings_account_id' => $company->retained_earnings_account_id,
                'sales_tax_payable_account_id' => $company->sales_tax_payable_account_id,
                'purchase_tax_receivable_account_id' => $company->purchase_tax_receivable_account_id,
            ],
            'accounts' => $accounts,
        ]);
    }

    public function update(UpdateDefaultAccountsRequest $request): RedirectResponse
    {
        /** @var Company $company */
        $company = app(CompanyContextService::class)->requireCompany();

        $data = $request->validated();

        $company->update([
            'ar_account_id' => $data['ar_account_id'],
            'ap_account_id' => $data['ap_account_id'],
            'income_account_id' => $data['income_account_id'],
            'expense_account_id' => $data['expense_account_id'],
            'bank_account_id' => $data['bank_account_id'],
            'retained_earnings_account_id' => $data['retained_earnings_account_id'],
            'sales_tax_payable_account_id' => $data['sales_tax_payable_account_id'] ?? null,
            'purchase_tax_receivable_account_id' => $data['purchase_tax_receivable_account_id'] ?? null,
        ]);

        app(PostingTemplateInstaller::class)->ensureDefaults($company->fresh());

        return back()->with('success', 'Default accounts updated.');
    }
}

