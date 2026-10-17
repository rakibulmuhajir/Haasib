<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Http\Requests\RecordCardChargeRequest;
use App\Modules\FuelStation\Http\Requests\SettlePaymentChannelRequest;
use App\Modules\FuelStation\Http\Requests\SettleVendorCardRequest;
use App\Modules\FuelStation\Services\CardChargeService;
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

    public function pending(\Illuminate\Http\Request $request): Response
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
            // Card charges entered by hand (usually month end), next to the daily close's own %.
            'cardChargeChannels' => app(CardChargeService::class)->channels($company, true),
            'cardCharges' => app(CardChargeService::class)->recent($company->id),
            'canRecordCharge' => request()->user()->hasCompanyPermission(\App\Constants\Permissions::PAYMENT_CREATE),
            // A card's sales over a period, for the Record charge dialog (asked for by a partial reload).
            'cardChargeSales' => fn () => $request->filled(['charge_channel', 'charge_from', 'charge_to'])
                ? app(CardChargeService::class)->cardSales($company->id, (string) $request->query('charge_channel'),
                    \Illuminate\Support\Carbon::parse($request->query('charge_from'))->toDateString(), \Illuminate\Support\Carbon::parse($request->query('charge_to'))->toDateString())
                : null,
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

    public function recordCharge(RecordCardChargeRequest $request): RedirectResponse
    {
        $result = app(\App\Services\CommandBus::class)->dispatch('fuel.card_charge.record', $request->validated(), $request->user());

        return redirect()->back()->with('success', $result['message']);
    }

    public function undoCharge(\Illuminate\Http\Request $request): RedirectResponse
    {
        $result = app(\App\Services\CommandBus::class)->dispatch('fuel.card_charge.undo', ['id' => $request->route('charge')], $request->user());

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
