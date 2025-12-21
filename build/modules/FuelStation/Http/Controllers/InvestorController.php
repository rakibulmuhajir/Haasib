<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\PayCommissionRequest;
use App\Modules\FuelStation\Http\Requests\StoreInvestorRequest;
use App\Modules\FuelStation\Http\Requests\StoreLotRequest;
use App\Modules\FuelStation\Http\Requests\UpdateInvestorRequest;
use App\Modules\FuelStation\Models\Investor;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Services\InvestorService;
use App\Modules\Inventory\Models\Item;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvestorController extends Controller
{
    public function __construct(
        private InvestorService $investorService
    ) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $investors = Investor::where('company_id', $company->id)
            ->with(['activeLots'])
            ->orderBy('name')
            ->paginate(50);

        // Get summary stats
        $summary = $this->investorService->getInvestorSummary($company->id);

        return Inertia::render('FuelStation/Investors/Index', [
            'investors' => $investors,
            'summary' => $summary,
        ]);
    }

    public function store(StoreInvestorRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        Investor::create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'cnic' => $data['cnic'] ?? null,
            'total_invested' => 0,
            'total_commission_earned' => 0,
            'total_commission_paid' => 0,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Investor created successfully.');
    }

    public function show(Investor $investor): Response
    {
        $company = app(CurrentCompany::class)->get();

        $investor->load(['lots' => function ($query) {
            $query->orderByDesc('deposit_date');
        }, 'investorAccount']);

        // Get current rates for display - use fuel_category column
        $fuelItems = Item::where('company_id', $company->id)
            ->whereNotNull('fuel_category')
            ->get();

        $currentRates = [];
        foreach ($fuelItems as $item) {
            $rate = RateChange::getCurrentRate($company->id, $item->id);
            if ($rate) {
                $currentRates[$item->id] = [
                    'item' => $item,
                    'purchase_rate' => $rate->purchase_rate,
                    'sale_rate' => $rate->sale_rate,
                    'margin' => $rate->margin,
                ];
            }
        }

        return Inertia::render('FuelStation/Investors/Show', [
            'investor' => $investor,
            'currentRates' => $currentRates,
        ]);
    }

    public function update(UpdateInvestorRequest $request, Investor $investor): RedirectResponse
    {
        $investor->update($request->validated());

        return redirect()->back()->with('success', 'Investor updated successfully.');
    }

    public function addLot(StoreLotRequest $request, Investor $investor): RedirectResponse
    {
        try {
            $this->investorService->createLot($investor, $request->validated());

            return redirect()->back()->with('success', 'Investment lot added successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function payCommission(PayCommissionRequest $request, Investor $investor): RedirectResponse
    {
        try {
            $this->investorService->payCommission($investor, $request->validated());

            return redirect()->back()->with('success', 'Commission payment processed successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
