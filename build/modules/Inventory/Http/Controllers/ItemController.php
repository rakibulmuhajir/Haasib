<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\TaxRate;
use App\Modules\FuelStation\Services\FuelProductAccountMapper;
use App\Modules\Inventory\Http\Requests\SaveItemPriceRequest;
use App\Modules\Inventory\Http\Requests\StoreItemRequest;
use App\Modules\Inventory\Http\Requests\UpdateItemRequest;
use App\Modules\Inventory\Http\Requests\UpdateItemStatusRequest;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Services\ItemDeletionService;
use App\Modules\Inventory\Services\ItemPriceService;
use App\Modules\Inventory\Services\OpeningStockService;
use App\Modules\Inventory\Services\ProductCatalogService;
use App\Services\CommandBus;
use App\Services\CompanyCurrencyOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = Item::where('company_id', $company->id)
            ->with('category:id,name,code');

        if ($request->has('search') && $request->search) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('sku', 'ilike', "%{$term}%")
                    ->orWhere('barcode', 'ilike', "%{$term}%");
            });
        }

        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('item_type') && $request->item_type) {
            $query->where('item_type', $request->item_type);
        }

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy === 'sku' ? 'sku' : 'name', $sortDir);

        $items = $query->paginate(25)->withQueryString();

        // Get stock totals for displayed items
        $itemIds = $items->pluck('id');
        $stockTotals = StockLevel::whereIn('item_id', $itemIds)
            ->selectRaw('item_id, SUM(quantity) as total_quantity, SUM(available_quantity) as total_available')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        $items->through(function (Item $item) use ($stockTotals) {
            $stock = $stockTotals->get($item->id);

            return array_merge($item->toArray(), [
                'total_quantity' => (float) ($stock->total_quantity ?? 0),
                'total_available' => (float) ($stock->total_available ?? 0),
            ]);
        });

        $categories = ItemCategory::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('inventory/items/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'items' => $items,
            'categories' => $categories,
            'filters' => [
                'search' => $request->search ?? '',
                'category_id' => $request->category_id ?? '',
                'item_type' => $request->item_type ?? '',
                'include_inactive' => $request->boolean('include_inactive'),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function create(): Response
    {
        $company = CompanyContext::getCompany();

        $categories = ItemCategory::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $currencies = app(CompanyCurrencyOptions::class)->forCompany($company);

        $taxRates = TaxRate::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'rate']);

        $incomeAccounts = Account::where('company_id', $company->id)
            ->where('type', 'revenue')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $expenseAccounts = Account::where('company_id', $company->id)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $assetAccounts = Account::where('company_id', $company->id)
            ->where('type', 'asset')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('inventory/items/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'categories' => $categories,
            'currencies' => $currencies,
            'taxRates' => $taxRates,
            'incomeAccounts' => $incomeAccounts,
            'expenseAccounts' => $expenseAccounts,
            'assetAccounts' => $assetAccounts,
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $data = $request->validated();
        $this->defaultStockAccounts($data, $company, $request->user()->id);
        $openingStock = app(OpeningStockService::class);

        $item = DB::transaction(function () use ($data, $company, $request, $openingStock) {
            $item = app(ProductCatalogService::class)->save(array_merge($data, [
                'company_id' => $company->id,
                'user_id' => $request->user()->id,
            ]));

            if ($item->track_inventory && (float) ($data['opening_quantity'] ?? 0) > 0) {
                $openingStock->record(
                    $company->id,
                    $item,
                    (float) $data['opening_quantity'],
                    isset($data['opening_unit_cost']) ? (float) $data['opening_unit_cost'] : null,
                    $data['opening_date'] ?? null,
                    null,
                    $request->user()->id,
                    null,
                    false
                );
            }

            return $item;
        });
        $openingStock->syncLedger($company->id, $request->user()->id);

        return redirect()
            ->route('items.show', ['company' => $company->slug, 'item' => $item->id])
            ->with('success', 'Item created successfully.');
    }

    /**
     * A stocked, sellable product at a fuel station gets the same stock, sales and cost accounts
     * the fuel quick add gives a packaged product, so either screen makes the same item (and its
     * opening stock can reach the books).
     */
    private function defaultStockAccounts(array &$data, $company, string $userId): void
    {
        $stocked = ($data['track_inventory'] ?? false) && ($data['item_type'] ?? '') === 'product';
        if (! $stocked || $company->industry_code !== 'fuel_station' || ! empty($data['asset_account_id'])) {
            return;
        }

        $accounts = app(FuelProductAccountMapper::class)->resolveAccounts($company->id, 'lubricant_packaged', $userId);
        $data['asset_account_id'] = $accounts['asset']->id;
        $data['income_account_id'] ??= $accounts['income']->id;
        $data['expense_account_id'] ??= $accounts['expense']->id;
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $itemId = $request->route('item');
        $item = Item::where('company_id', $company->id)
            ->with(['category:id,name,code', 'taxRate:id,name,code,rate'])
            ->findOrFail($itemId);

        $stockLevels = StockLevel::where('item_id', $item->id)
            ->with('warehouse:id,name,code')
            ->get();

        $totalStock = $stockLevels->sum('quantity');
        $totalAvailable = $stockLevels->sum('available_quantity');

        $pendingReceiptsCount = 0;
        $pendingReceiptsQuantity = 0.0;
        if ($item->track_inventory && $item->delivery_mode === 'requires_receiving') {
            $pendingReceiptSummary = DB::table('acct.bill_line_items as li')
                ->join('acct.bills as b', 'b.id', '=', 'li.bill_id')
                ->join('inv.items as items', 'items.id', '=', 'li.item_id')
                ->where('b.company_id', $company->id)
                ->whereNull('b.deleted_at')
                ->whereNull('li.deleted_at')
                ->whereNull('items.deleted_at')
                ->where('b.status', 'paid')
                ->whereNull('b.goods_received_at')
                ->where('items.track_inventory', true)
                ->where('items.delivery_mode', 'requires_receiving')
                ->where('li.item_id', $item->id)
                ->whereRaw('COALESCE(li.quantity_received, 0) < li.quantity - COALESCE(li.direct_quantity, 0)')
                ->selectRaw('COUNT(*) as pending_count, SUM(li.quantity - COALESCE(li.direct_quantity, 0) - COALESCE(li.quantity_received, 0)) as pending_qty')
                ->first();

            $pendingReceiptsCount = (int) ($pendingReceiptSummary?->pending_count ?? 0);
            $pendingReceiptsQuantity = (float) ($pendingReceiptSummary?->pending_qty ?? 0);
        }

        return Inertia::render('inventory/items/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'item' => $item,
            'priceHistory' => [
                'rows' => app(ItemPriceService::class)->timeline($item, $company->slug),
                'is_fuel' => (bool) $item->fuel_category,
            ],
            'stockLevels' => $stockLevels,
            'pendingReceiptsCount' => $pendingReceiptsCount,
            'pendingReceiptsQuantity' => $pendingReceiptsQuantity,
            'summary' => [
                'total_quantity' => (float) $totalStock,
                'total_available' => (float) $totalAvailable,
            ],
        ]);
    }

    public function savePrice(SaveItemPriceRequest $request): RedirectResponse
    {
        try {
            app(CommandBus::class)->dispatch('item_price.save', [
                ...$request->validated(),
                'item_id' => $request->route('item'),
                'user_id' => $request->user()->id,
            ], $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Price saved.');
    }

    public function deletePrice(Request $request): RedirectResponse
    {
        try {
            app(CommandBus::class)->dispatch('item_price.delete', [
                'price_id' => $request->route('price'),
                'user_id' => $request->user()->id,
            ], $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Price removed.');
    }

    public function edit(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $itemId = $request->route('item');
        $item = Item::where('company_id', $company->id)->findOrFail($itemId);

        $categories = ItemCategory::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $currencies = app(CompanyCurrencyOptions::class)->forCompany($company);

        $taxRates = TaxRate::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'rate']);

        $incomeAccounts = Account::where('company_id', $company->id)
            ->where('type', 'revenue')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $expenseAccounts = Account::where('company_id', $company->id)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $assetAccounts = Account::where('company_id', $company->id)
            ->where('type', 'asset')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('inventory/items/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'item' => $item,
            'categories' => $categories,
            'currencies' => $currencies,
            'taxRates' => $taxRates,
            'incomeAccounts' => $incomeAccounts,
            'expenseAccounts' => $expenseAccounts,
            'assetAccounts' => $assetAccounts,
        ]);
    }

    public function update(UpdateItemRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $itemId = $request->route('item');
        $item = Item::where('company_id', $company->id)->findOrFail($itemId);

        $item = app(ProductCatalogService::class)->save(array_merge($request->validated(), [
            'id' => $item->id,
            'item' => $item,
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
        ]));

        return redirect()
            ->route('items.show', ['company' => $company->slug, 'item' => $item->id])
            ->with('success', 'Item updated successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $itemId = $request->route('item');
        $item = Item::where('company_id', $company->id)->findOrFail($itemId);

        if (! app(ItemDeletionService::class)->delete($item, $request->user()?->id)) {
            return back()->with('error', ItemDeletionService::USED_MESSAGE);
        }

        if ($request->input('return_to') === 'back') {
            return back()->with('success', 'Product deleted successfully.');
        }

        return redirect()
            ->route('items.index', ['company' => $company->slug])
            ->with('success', 'Item deleted successfully.');
    }

    public function updateStatus(UpdateItemStatusRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $itemId = $request->route('item');
        $item = Item::where('company_id', $company->id)->findOrFail($itemId);
        $item->update([
            'is_active' => (bool) $request->validated('is_active'),
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return back()->with('success', $item->is_active ? 'Product activated.' : 'Product deactivated.');
    }

    public function search(Request $request): JsonResponse
    {
        $company = CompanyContext::getCompany();
        $query = $request->get('q', '');
        $limit = min((int) $request->get('limit', 10), 50);

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $items = Item::where('company_id', $company->id)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ilike', "%{$query}%")
                    ->orWhere('sku', 'ilike', "%{$query}%")
                    ->orWhere('barcode', 'ilike', "%{$query}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'sku', 'name', 'selling_price', 'cost_price', 'currency', 'item_type']);

        return response()->json(['results' => $items]);
    }
}
