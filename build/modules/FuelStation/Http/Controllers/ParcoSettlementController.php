<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Http\Requests\SettleParcoRequest;
use App\Modules\FuelStation\Services\ParcoSettlementService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ParcoSettlementController extends Controller
{
    public function __construct(
        private ParcoSettlementService $parcoService
    ) {}

    public function pending(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $summary = $this->parcoService->getPendingSummary($company->id);

        // Get bank accounts for settlement
        $bankAccounts = Account::where('company_id', $company->id)
            ->where('account_type', 'asset')
            ->where(function ($query) {
                $query->where('name', 'like', '%Bank%');
            })
            ->get();

        return Inertia::render('FuelStation/Parco/Pending', [
            'summary' => $summary,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function settle(SettleParcoRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        try {
            $this->parcoService->settle($company->id, $request->validated());

            return redirect()->back()->with('success', 'Parco settlement completed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
