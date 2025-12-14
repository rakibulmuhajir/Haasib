<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\StoreWarehouseRequest;
use App\Modules\Inventory\Http\Requests\UpdateWarehouseRequest;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = Warehouse::where('company_id', $company->id);

        if ($request->has('search') && $request->search) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('code', 'ilike', "%{$term}%")
                    ->orWhere('city', 'ilike', "%{$term}%");
            });
        }

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $query->orderByDesc('is_primary')->orderBy('name');

        $warehouses = $query->paginate(25)->withQueryString();

        // Get stock counts per warehouse
        $warehouseIds = $warehouses->pluck('id');
        $stockCounts = StockLevel::whereIn('warehouse_id', $warehouseIds)
            ->selectRaw('warehouse_id, COUNT(DISTINCT item_id) as item_count, SUM(quantity) as total_units')
            ->groupBy('warehouse_id')
            ->get()
            ->keyBy('warehouse_id');

        $warehouses->through(function (Warehouse $warehouse) use ($stockCounts) {
            $stock = $stockCounts->get($warehouse->id);
            return array_merge($warehouse->toArray(), [
                'item_count' => (int) ($stock->item_count ?? 0),
                'total_units' => (float) ($stock->total_units ?? 0),
            ]);
        });

        return Inertia::render('inventory/warehouses/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $request->search ?? '',
                'include_inactive' => $request->boolean('include_inactive'),
            ],
        ]);
    }

    public function create(): Response
    {
        $company = CompanyContext::getCompany();

        return Inertia::render('inventory/warehouses/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
        ]);
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $data = $request->validated();

        // If setting as primary, unset other primaries
        if (! empty($data['is_primary'])) {
            Warehouse::where('company_id', $company->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $warehouse = Warehouse::create(array_merge($data, [
            'company_id' => $company->id,
            'created_by_user_id' => $request->user()->id,
        ]));

        return redirect()
            ->route('warehouses.show', ['company' => $company->slug, 'warehouse' => $warehouse->id])
            ->with('success', 'Warehouse created successfully.');
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $warehouseId = $request->route('warehouse');
        $warehouse = Warehouse::where('company_id', $company->id)->findOrFail($warehouseId);

        $stockLevels = StockLevel::where('warehouse_id', $warehouse->id)
            ->with('item:id,sku,name,unit_of_measure')
            ->where('quantity', '!=', 0)
            ->orderBy('item_id')
            ->paginate(50);

        $summary = StockLevel::where('warehouse_id', $warehouse->id)
            ->selectRaw('COUNT(DISTINCT item_id) as item_count')
            ->selectRaw('SUM(quantity) as total_units')
            ->selectRaw('SUM(reserved_quantity) as total_reserved')
            ->first();

        return Inertia::render('inventory/warehouses/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'warehouse' => $warehouse,
            'stockLevels' => $stockLevels,
            'summary' => [
                'item_count' => (int) ($summary->item_count ?? 0),
                'total_units' => (float) ($summary->total_units ?? 0),
                'total_reserved' => (float) ($summary->total_reserved ?? 0),
            ],
        ]);
    }

    public function edit(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $warehouseId = $request->route('warehouse');
        $warehouse = Warehouse::where('company_id', $company->id)->findOrFail($warehouseId);

        return Inertia::render('inventory/warehouses/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'warehouse' => $warehouse,
        ]);
    }

    public function update(UpdateWarehouseRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $warehouseId = $request->route('warehouse');
        $warehouse = Warehouse::where('company_id', $company->id)->findOrFail($warehouseId);

        $data = $request->validated();

        // If setting as primary, unset other primaries
        if (! empty($data['is_primary']) && ! $warehouse->is_primary) {
            Warehouse::where('company_id', $company->id)
                ->where('is_primary', true)
                ->where('id', '!=', $warehouse->id)
                ->update(['is_primary' => false]);
        }

        $warehouse->update($data);

        return redirect()
            ->route('warehouses.show', ['company' => $company->slug, 'warehouse' => $warehouse->id])
            ->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $warehouseId = $request->route('warehouse');
        $warehouse = Warehouse::where('company_id', $company->id)->findOrFail($warehouseId);

        // Check if warehouse has stock
        $hasStock = StockLevel::where('warehouse_id', $warehouse->id)
            ->where('quantity', '!=', 0)
            ->exists();

        if ($hasStock) {
            return back()->with('error', 'Cannot delete warehouse with stock. Transfer stock first.');
        }

        $warehouse->delete();

        return redirect()
            ->route('warehouses.index', ['company' => $company->slug])
            ->with('success', 'Warehouse deleted successfully.');
    }

    public function search(Request $request): JsonResponse
    {
        $company = CompanyContext::getCompany();
        $query = $request->get('q', '');
        $limit = min((int) $request->get('limit', 10), 50);

        $warehouses = Warehouse::where('company_id', $company->id)
            ->where('is_active', true)
            ->when(strlen($query) >= 2, function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'ilike', "%{$query}%")
                        ->orWhere('code', 'ilike', "%{$query}%");
                });
            })
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'code', 'name', 'is_primary']);

        return response()->json(['results' => $warehouses]);
    }
}
