<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreOpeningBalancesRequest;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpeningBalanceController extends Controller
{
    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();
        abort_unless($request->user()->hasCompanyPermission(Permissions::OPENING_BALANCE_VIEW), 403);

        $opening = app(CommandBus::class)->dispatch('opening_balance.view', [], $request->user());

        return Inertia::render('accounting/opening-balances/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug],
            'opening' => $opening,
            'canManage' => $request->user()->hasCompanyPermission(Permissions::OPENING_BALANCE_MANAGE),
        ]);
    }

    public function store(StoreOpeningBalancesRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        try {
            $result = app(CommandBus::class)->dispatch('opening_balance.save', $request->validated(), $request->user());
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('accounting.opening-balances.show', ['company' => $company->slug])
            ->with('success', $result['message']);
    }

    public function lock(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        abort_unless($request->user()->hasCompanyPermission(Permissions::OPENING_BALANCE_MANAGE), 403);

        try {
            $result = app(CommandBus::class)->dispatch('opening_balance.lock', [], $request->user());
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('accounting.opening-balances.show', ['company' => $company->slug])
            ->with('success', $result['message']);
    }
}
