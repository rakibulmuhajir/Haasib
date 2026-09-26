<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Http\Requests\StoreFuelSaleRequest;
use App\Modules\FuelStation\Models\CustomerFuelDiscount;
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
            // Per-customer, per-fuel-item discounts, keyed by customer id then item id, so
            // the form can prefill without a round trip once both are picked. See
            // CustomerFuelDiscountService for the single place this same rate is priced.
            'customerFuelDiscounts' => CustomerFuelDiscount::where('company_id', $company->id)
                ->get()
                ->groupBy('customer_id')
                ->map(fn ($rows) => $rows->keyBy('item_id')->map(fn (CustomerFuelDiscount $d) => [
                    'discount_type' => $d->discount_type,
                    'value' => (float) $d->value,
                ])),
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

    /**
     * Fuel sold straight from the supplier's tanker to a customer. Unlike a pump sale it goes
     * through no meter and no tank, so nothing else will ever count it: the invoice itself
     * posts the income (invoice.create, flagged is_direct_delivery so the Daily Close leaves
     * it out of its credit rows). The litres' cost was already booked to COGS by the bill's
     * "Sold directly" quantity. Paid in cash, the money goes into the station's cash account
     * on the sale date, so that day's close counts it as money in -- the same payment the
     * close's "Received in cash" button records.
     */
    public function storeDirect(\App\Modules\FuelStation\Http\Requests\StoreDirectFuelSaleRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();
        $item = Item::where('company_id', $company->id)->findOrFail($data['item_id']);
        $paidInCash = (bool) $data['paid_in_cash'];
        $cashAccountId = $paidInCash
            ? app(\App\Modules\FuelStation\Services\DailyCloseService::class)->cashAccountId($company->id)
            : null;
        if ($paidInCash && ! $cashAccountId) {
            return back()->with('error', 'No cash account is set for this station.');
        }

        try {
            $invoiceNumber = \Illuminate\Support\Facades\DB::transaction(function () use ($company, $data, $item, $paidInCash, $cashAccountId, $request) {
                $bus = app(\App\Services\CommandBus::class);
                // No customer on a cash sale: the same walk-in fuel customer a retail pump sale uses.
                $customerId = $this->fuelSaleService->resolveCustomerId($company, \App\Modules\FuelStation\Models\SaleMetadata::TYPE_RETAIL, $data);
                $result = $bus->dispatch('invoice.create', [
                    'customer' => $customerId,
                    'currency' => $company->base_currency ?: 'PKR',
                    'date' => $data['sale_date'],
                    'payment_terms' => $paidInCash ? 0 : null,
                    'is_direct_delivery' => true,
                    'line_items' => [[
                        'description' => rtrim(rtrim(number_format((float) $data['quantity'], 2, '.', ''), '0'), '.')." L {$item->name} - direct from tanker",
                        'quantity' => $data['quantity'],
                        'unit_price' => $data['unit_price'],
                        'income_account_id' => $item->income_account_id,
                    ]],
                ], $request->user());

                $invoice = \App\Modules\Accounting\Models\Invoice::where('company_id', $company->id)->findOrFail($result['data']['id']);
                if ($paidInCash) {
                    $amount = round((float) $invoice->balance, 2);
                    $bus->dispatch('payment.create', [
                        'customer_id' => $invoice->customer_id,
                        'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
                        'amount' => $amount,
                        'method' => 'cash',
                        'date' => $data['sale_date'],
                        'deposit_account_id' => $cashAccountId,
                        'reference' => $invoice->invoice_number,
                    ], $request->user());
                }

                return $invoice->invoice_number;
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $paidInCash
            ? "Direct sale {$invoiceNumber} recorded and paid in cash. It is counted in that day's money in."
            : "Direct sale {$invoiceNumber} recorded on the customer's account.");
    }
}
