<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Http\Requests\StoreFuelSaleRequest;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Services\FuelSaleService;
use App\Modules\Inventory\Models\Item;
use App\Services\CurrentCompany;
use App\Modules\Inventory\Services\ProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FuelSaleController extends Controller
{
    public function __construct(
        private FuelSaleService $fuelSaleService
    ) {}

    /**
     * The standalone fuel sale form. Until this route existed the page below was
     * unreachable -- only POST /sales was routed -- so a credit fuel sale could only be
     * typed inside a Daily Close, and DailyCloseController::getPendingFuelInvoicesForDailyClose()
     * (which imports fuel invoices carrying credit SaleMetadata into the close for their
     * business date) could never find anything to import.
     */
    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless(request()->user()?->hasCompanyPermission(Permissions::INVOICE_CREATE), 403);

        $catalog = app(ProductCatalogService::class);
        $fuelItems = Item::where('company_id', $company->id)
            ->where('is_sellable', true)
            ->whereIn('item_type', ['product', 'non_inventory'])
            ->orderBy('name')
            ->get()
            ->filter(fn (Item $item) => $item->fuel_category || $catalog->inferFuelCategory($item->sku, $item->name))
            ->each(function (Item $item) use ($catalog) {
                $item->fuel_category = $item->fuel_category ?: $catalog->inferFuelCategory($item->sku, $item->name);
            })
            ->values();

        $rates = $fuelItems
            ->map(function (Item $item) use ($company) {
                $rate = RateChange::getCurrentRate($company->id, $item->id);

                return $rate ? [
                    'item_id' => $item->id,
                    'sale_rate' => (float) $rate->sale_rate,
                    'purchase_rate' => (float) $rate->purchase_rate,
                    'margin' => (float) $rate->sale_rate - (float) $rate->purchase_rate,
                ] : null;
            })
            ->filter()
            ->values();

        return Inertia::render('FuelStation/Sales/Form', [
            // The form labels each pump with the fuel its tank holds, reading
            // tank.linked_item — without that relation every pump reads "No fuel".
            'pumps' => Pump::where('company_id', $company->id)->where('is_active', true)
                ->with('tank.linkedItem:id,name,fuel_category')
                ->orderBy('name')
                ->get(['id', 'name', 'tank_id'])
                ->map(fn (Pump $pump) => [
                    'id' => $pump->id,
                    'name' => $pump->name,
                    'tank_id' => $pump->tank_id,
                    'tank' => $pump->tank ? [
                        'id' => $pump->tank->id,
                        'name' => $pump->tank->name,
                        'linked_item' => $pump->tank->linkedItem ? [
                            'id' => $pump->tank->linkedItem->id,
                            'name' => $pump->tank->linkedItem->name,
                            'fuel_category' => $pump->tank->linkedItem->fuel_category,
                        ] : null,
                    ] : null,
                ]),
            'fuelItems' => $fuelItems->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'fuel_category' => $item->fuel_category,
            ]),
            'customers' => Customer::where('company_id', $company->id)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'rates' => $rates,
        ]);
    }

    public function store(StoreFuelSaleRequest $request): RedirectResponse
    {
        try {
            $this->fuelSaleService->createSale($request->validated());

            return redirect()->back()->with('success', 'Fuel sale recorded successfully.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
