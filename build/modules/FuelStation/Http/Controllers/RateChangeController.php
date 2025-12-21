<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StoreRateChangeRequest;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\Inventory\Models\Item;
use App\Services\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RateChangeController extends Controller
{
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

        return Inertia::render('FuelStation/Rates/Index', [
            'rates' => $rates,
            'items' => $fuelItems,
        ]);
    }

    public function store(StoreRateChangeRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        // Calculate margin impact if stock quantity provided
        if (isset($data['stock_quantity_at_change'])) {
            $previousRate = RateChange::getCurrentRate($company->id, $data['item_id']);
            if ($previousRate) {
                $oldMargin = $previousRate->sale_rate - $previousRate->purchase_rate;
                $newMargin = $data['sale_rate'] - $data['purchase_rate'];
                $data['margin_impact'] = ($newMargin - $oldMargin) * $data['stock_quantity_at_change'];
            }
        }

        RateChange::create([
            'company_id' => $company->id,
            'created_by_user_id' => auth()->id(),
            ...$data,
        ]);

        return redirect()->back()->with('success', 'Rate change recorded successfully.');
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
