<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Http\Requests\SettleVendorCardRequest;
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
            ->where('account_type', 'asset')
            ->where(function ($query) {
                $query->where('name', 'like', '%Bank%');
            })
            ->get();

        return Inertia::render('FuelStation/VendorCards/Settlement', [
            'summary' => $summary,
            'pendingSales' => $pendingSales,
            'todaySettlements' => [],
            'bankAccounts' => $bankAccounts,
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
}
