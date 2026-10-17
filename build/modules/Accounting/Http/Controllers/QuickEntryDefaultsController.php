<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Modules\Accounting\Http\Requests\UpdateQuickEntryDefaultsRequest;
use App\Modules\Accounting\Models\Account;
use App\Services\CompanyContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Which cash or bank accounts a typed one-line entry uses when the line names none.
 */
class QuickEntryDefaultsController extends Controller
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
            ->whereIn('subtype', ['cash', 'bank'])
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);

        $saved = $company->quickEntryDefaults();

        return Inertia::render('accounting/settings/QuickEntry', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug],
            'defaults' => [
                'receive_account_id' => $accounts->contains('id', $saved['receive_account_id']) ? $saved['receive_account_id'] : null,
                'pay_account_id' => $accounts->contains('id', $saved['pay_account_id']) ? $saved['pay_account_id'] : null,
            ],
            'accounts' => $accounts,
        ]);
    }

    public function update(UpdateQuickEntryDefaultsRequest $request): RedirectResponse
    {
        /** @var Company $company */
        $company = app(CompanyContextService::class)->requireCompany();
        $data = $request->validated();

        $company->setQuickEntryDefaults($data['receive_account_id'] ?? null, $data['pay_account_id'] ?? null);

        return back()->with('success', 'Quick entry defaults saved.');
    }
}
