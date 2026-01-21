<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StoreRateChangeRequest;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Services\RateChangeService;
use App\Modules\Inventory\Models\Item;
use App\Services\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RateChangeController extends Controller
{
    public function __construct(
        private readonly RateChangeService $rateChangeService,
    ) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $rates = RateChange::where('company_id', $company->id)
            ->with('item')
            ->orderByDesc('effective_date')
            ->get();

        $fuelItems = Item::where('company_id', $company->id)
            ->whereNotNull('fuel_category')
            ->get();

        // Get current stock for each fuel item
        $stockLevels = [];
        foreach ($fuelItems as $item) {
            $stockLevels[$item->id] = $this->rateChangeService->getCurrentStock($company->id, $item->id);
        }

        return Inertia::render('FuelStation/Rates/Index', [
            'rates' => $rates,
            'items' => $fuelItems,
            'stockLevels' => $stockLevels,
        ]);
    }

    public function store(StoreRateChangeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $rateChange = $this->rateChangeService->createWithRevaluation($data);

            $message = 'Rate change recorded successfully.';
            if ($rateChange->hasRevaluation()) {
                $revalAmount = abs($rateChange->revaluation_amount);
                $type = $rateChange->revaluation_amount > 0 ? 'gain' : 'loss';
                $message = sprintf(
                    'Rate change recorded. Stock revaluation of %s %s posted (%s).',
                    number_format($revalAmount, 2),
                    $rateChange->item->company->base_currency ?? 'PKR',
                    $type
                );
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to record rate change: ' . $e->getMessage());
        }
    }

    // JSON endpoint by design - used for API calls to get current rates
    public function current(): JsonResponse
    {
        $company = app(CurrentCompany::class)->get();

        $fuelItems = Item::where('company_id', $company->id)
            ->whereNotNull('fuel_category')
            ->get();

        $rates = [];
        foreach ($fuelItems as $item) {
            $currentRate = RateChange::getCurrentRate($company->id, $item->id);
            if ($currentRate) {
                $rates[$item->id] = [
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'fuel_category' => $item->fuel_category,
                    'purchase_rate' => $currentRate->purchase_rate,
                    'sale_rate' => $currentRate->sale_rate,
                    'margin' => $currentRate->margin,
                    'effective_date' => $currentRate->effective_date,
                ];
            }
        }

        return response()->json(['rates' => $rates]);
    }
}
