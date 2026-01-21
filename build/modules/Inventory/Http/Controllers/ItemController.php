<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Models\CompanyCurrency;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\TaxRate;
use App\Modules\Inventory\Http\Requests\StoreItemRequest;
use App\Modules\Inventory\Http\Requests\UpdateItemRequest;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\StockLevel;
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

        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

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

        $item = Item::create(array_merge($request->validated(), [
            'company_id' => $company->id,
            'created_by_user_id' => $request->user()->id,
        ]));

        return redirect()
            ->route('items.show', ['company' => $company->slug, 'item' => $item->id])
            ->with('success', 'Item created successfully.');
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
                ->whereRaw('COALESCE(li.quantity_received, 0) < li.quantity')
                ->selectRaw('COUNT(*) as pending_count, SUM(li.quantity - COALESCE(li.quantity_received, 0)) as pending_qty')
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
            'stockLevels' => $stockLevels,
            'pendingReceiptsCount' => $pendingReceiptsCount,
            'pendingReceiptsQuantity' => $pendingReceiptsQuantity,
            'summary' => [
                'total_quantity' => (float) $totalStock,
                'total_available' => (float) $totalAvailable,
            ],
        ]);
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

        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

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

        $item->update(array_merge($request->validated(), [
            'updated_by_user_id' => $request->user()->id,
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

        // Check if item has stock
        $hasStock = StockLevel::where('item_id', $item->id)
            ->where('quantity', '!=', 0)
            ->exists();

        if ($hasStock) {
            return back()->with('error', 'Cannot delete item with stock on hand. Adjust stock to zero first.');
        }

        $item->delete();

        return redirect()
            ->route('items.index', ['company' => $company->slug])
            ->with('success', 'Item deleted successfully.');
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
