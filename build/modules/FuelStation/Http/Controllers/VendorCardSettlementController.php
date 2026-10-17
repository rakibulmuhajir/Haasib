<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Http\Requests\RecordCardTaxRequest;
use App\Modules\FuelStation\Http\Requests\SettlePaymentChannelRequest;
use App\Modules\FuelStation\Http\Requests\SettleVendorCardRequest;
use App\Modules\FuelStation\Services\CardTaxService;
use App\Modules\FuelStation\Services\VendorCardSettlementService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VendorCardSettlementController extends Controller
{
    public function __construct(
        private VendorCardSettlementService $vendorCardService
    ) {}

    public function pending(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $summary = $this->vendorCardService->getPendingSummary($company->id);
        $pendingSales = $summary['items'] ?? [];

        // Get bank accounts for settlement
        $bankAccounts = Account::where('company_id', $company->id)
            ->where('type', 'asset')
            ->where('subtype', 'bank')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('FuelStation/VendorCards/Settlement', [
            'summary' => $summary,
            'pendingSales' => $pendingSales,
            'clearingAccounts' => $summary['clearing_accounts'] ?? [],
            'todaySettlements' => [],
            'bankAccounts' => $bankAccounts,
            // Tax deducted from card sales: only with the company's tax switch on.
            'taxEnabled' => app(\App\Modules\Accounting\Services\WithholdingTax::class)->enabled($company),
            'cardTaxChannels' => app(CardTaxService::class)->channels($company),
            'cardTaxes' => app(CardTaxService::class)->recent($company->id),
            'canRecordTax' => request()->user()->hasCompanyPermission(\App\Constants\Permissions::PAYMENT_CREATE),
        ]);
    }

    public function settle(SettleVendorCardRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        try {
            $this->vendorCardService->settle($company->id, $request->validated());

            return redirect()->back()->with('success', 'Vendor card settlement completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function recordTax(RecordCardTaxRequest $request): RedirectResponse
    {
        $result = app(\App\Services\CommandBus::class)->dispatch('fuel.card_tax.record', $request->validated(), $request->user());

        return redirect()->back()->with('success', $result['message']);
    }

    public function undoTax(\Illuminate\Http\Request $request): RedirectResponse
    {
        $result = app(\App\Services\CommandBus::class)->dispatch('fuel.card_tax.undo', ['id' => $request->route('deduction')], $request->user());

        return redirect()->back()->with('success', $result['message']);
    }

    public function settleClearing(SettlePaymentChannelRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        try {
            $this->vendorCardService->settleClearingAccount($company->id, $request->validated());

            return redirect()->back()->with('success', 'Payment channel clearing settlement completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
