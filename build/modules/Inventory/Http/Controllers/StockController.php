<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Inventory\Http\Requests\StoreStockAdjustmentRequest;
use App\Modules\Inventory\Http\Requests\StoreStockTransferRequest;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReceipt;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = StockLevel::where('inv.stock_levels.company_id', $company->id)
            ->join('inv.items', 'inv.stock_levels.item_id', '=', 'inv.items.id')
            ->join('inv.warehouses', 'inv.stock_levels.warehouse_id', '=', 'inv.warehouses.id')
            ->select(
                'inv.stock_levels.*',
                'inv.items.sku',
                'inv.items.name as item_name',
                'inv.items.unit_of_measure',
                'inv.items.reorder_point as item_reorder_point',
                'inv.warehouses.name as warehouse_name',
                'inv.warehouses.code as warehouse_code'
            );

        if ($request->has('search') && $request->search) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('inv.items.name', 'ilike', "%{$term}%")
                    ->orWhere('inv.items.sku', 'ilike', "%{$term}%");
            });
        }

        if ($request->has('warehouse_id') && $request->warehouse_id) {
            $query->where('inv.stock_levels.warehouse_id', $request->warehouse_id);
        }

        if ($request->boolean('low_stock_only')) {
            $query->whereRaw('inv.stock_levels.quantity < COALESCE(inv.stock_levels.reorder_point, inv.items.reorder_point, 0)');
        }

        $query->orderBy('inv.items.name');

        $stockLevels = $query->paginate(50)->withQueryString();

        $itemIds = $stockLevels->getCollection()->pluck('item_id')->filter()->unique()->values();
        $pendingReceipts = collect();
        if ($itemIds->isNotEmpty()) {
            $pendingReceipts = DB::table('acct.bill_line_items as li')
                ->join('acct.bills as b', 'b.id', '=', 'li.bill_id')
                ->join('inv.items as items', 'items.id', '=', 'li.item_id')
                ->where('b.company_id', $company->id)
                ->whereNull('b.deleted_at')
                ->whereNull('li.deleted_at')
                ->whereNull('items.deleted_at')
                ->whereIn('li.item_id', $itemIds)
                ->where('b.status', 'paid')
                ->whereNull('b.goods_received_at')
                ->where('items.track_inventory', true)
                ->where('items.delivery_mode', 'requires_receiving')
                ->whereRaw('COALESCE(li.quantity_received, 0) < li.quantity')
                ->groupBy('li.item_id')
                ->selectRaw('li.item_id, COUNT(*) as pending_count, SUM(li.quantity - COALESCE(li.quantity_received, 0)) as pending_qty')
                ->get()
                ->keyBy('item_id');
        }

        $stockLevels->getCollection()->transform(function ($level) use ($pendingReceipts) {
            $pending = $pendingReceipts->get($level->item_id);
            $level->pending_receipts = (int) ($pending?->pending_count ?? 0);
            $level->pending_receipts_qty = (float) ($pending?->pending_qty ?? 0);
            return $level;
        });

        $warehouses = Warehouse::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('inventory/stock/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'stockLevels' => $stockLevels,
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $request->search ?? '',
                'warehouse_id' => $request->warehouse_id ?? '',
                'low_stock_only' => $request->boolean('low_stock_only'),
            ],
        ]);
    }

    public function movements(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = StockMovement::where('inv.stock_movements.company_id', $company->id)
            ->with(['item:id,sku,name', 'warehouse:id,name,code', 'createdBy:id,name'])
            ->orderByDesc('created_at');

        if ($request->has('item_id') && $request->item_id) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->has('warehouse_id') && $request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->has('movement_type') && $request->movement_type) {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->where('movement_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('movement_date', '<=', $request->date_to);
        }

        $movements = $query->paginate(50)->withQueryString();

        $warehouses = Warehouse::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('inventory/stock/Movements', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'movements' => $movements,
            'warehouses' => $warehouses,
            'filters' => [
                'item_id' => $request->item_id ?? '',
                'warehouse_id' => $request->warehouse_id ?? '',
                'movement_type' => $request->movement_type ?? '',
                'date_from' => $request->date_from ?? '',
                'date_to' => $request->date_to ?? '',
            ],
        ]);
    }

    public function receipts(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $pendingBills = Bill::query()
            ->select('acct.bills.*')
            ->with('vendor:id,name')
            ->where('company_id', $company->id)
            ->where('status', 'paid')
            ->whereNull('goods_received_at')
            ->whereExists(function ($sub) use ($company) {
                $sub->selectRaw('1')
                    ->from('acct.bill_line_items as li')
                    ->join('inv.items as items', 'items.id', '=', 'li.item_id')
                    ->whereColumn('li.bill_id', 'acct.bills.id')
                    ->where('li.company_id', $company->id)
                    ->whereNull('li.deleted_at')
                    ->whereNull('items.deleted_at')
                    ->where('items.track_inventory', true)
                    ->where('items.delivery_mode', 'requires_receiving')
                    ->whereRaw('COALESCE(li.quantity_received, 0) < li.quantity');
            })
            ->addSelect([
                'pending_lines' => DB::table('acct.bill_line_items as li')
                    ->join('inv.items as items', 'items.id', '=', 'li.item_id')
                    ->whereColumn('li.bill_id', 'acct.bills.id')
                    ->where('li.company_id', $company->id)
                    ->whereNull('li.deleted_at')
                    ->whereNull('items.deleted_at')
                    ->where('items.track_inventory', true)
                    ->where('items.delivery_mode', 'requires_receiving')
                    ->whereRaw('COALESCE(li.quantity_received, 0) < li.quantity')
                    ->selectRaw('COUNT(*)'),
            ])
            ->orderByDesc('bill_date')
            ->paginate(20, ['*'], 'pending_page')
            ->withQueryString();

        $receipts = StockReceipt::query()
            ->with(['bill.vendor:id,name', 'createdBy:id,name'])
            ->withCount('lines')
            ->withSum('lines as total_received', 'received_quantity')
            ->withSum('lines as total_variance', 'variance_quantity')
            ->where('company_id', $company->id)
            ->orderByDesc('receipt_date')
            ->paginate(20, ['*'], 'receipt_page')
            ->withQueryString();

        return Inertia::render('inventory/stock/Receipts', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'pendingBills' => $pendingBills,
            'receipts' => $receipts,
            'filters' => [
                'tab' => $request->string('tab')->toString() ?: 'pending',
            ],
        ]);
    }

    public function createAdjustment(): Response
    {
        $company = CompanyContext::getCompany();

        $warehouses = Warehouse::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $items = Item::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'unit_of_measure']);

        return Inertia::render('inventory/stock/Adjustment', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $data = $request->validated();

        $movementType = $data['quantity'] > 0 ? 'adjustment_in' : 'adjustment_out';

        StockMovement::create([
            'company_id' => $company->id,
            'warehouse_id' => $data['warehouse_id'],
            'item_id' => $data['item_id'],
            'movement_date' => $data['movement_date'] ?? now()->toDateString(),
            'movement_type' => $movementType,
            'quantity' => $data['quantity'],
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('stock.index', ['company' => $company->slug])
            ->with('success', 'Stock adjustment recorded successfully.');
    }

    public function createTransfer(): Response
    {
        $company = CompanyContext::getCompany();

        $warehouses = Warehouse::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $items = Item::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'unit_of_measure']);

        return Inertia::render('inventory/stock/Transfer', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    public function storeTransfer(StoreStockTransferRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $data = $request->validated();

        // Check available quantity
        $sourceStock = StockLevel::where('company_id', $company->id)
            ->where('warehouse_id', $data['source_warehouse_id'])
            ->where('item_id', $data['item_id'])
            ->first();

        $available = $sourceStock ? $sourceStock->available_quantity : 0;

        if ($data['quantity'] > $available) {
            return back()->with('error', "Insufficient stock. Available: {$available}");
        }

        DB::transaction(function () use ($company, $data, $request) {
            // Create transfer_out movement
            $outMovement = StockMovement::create([
                'company_id' => $company->id,
                'warehouse_id' => $data['source_warehouse_id'],
                'item_id' => $data['item_id'],
                'movement_date' => $data['movement_date'] ?? now()->toDateString(),
                'movement_type' => 'transfer_out',
                'quantity' => -abs($data['quantity']),
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $request->user()->id,
            ]);

            // Create transfer_in movement
            $inMovement = StockMovement::create([
                'company_id' => $company->id,
                'warehouse_id' => $data['destination_warehouse_id'],
                'item_id' => $data['item_id'],
                'movement_date' => $data['movement_date'] ?? now()->toDateString(),
                'movement_type' => 'transfer_in',
                'quantity' => abs($data['quantity']),
                'related_movement_id' => $outMovement->id,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $request->user()->id,
            ]);

            // Link the out movement to the in movement
            $outMovement->update(['related_movement_id' => $inMovement->id]);
        });

        return redirect()
            ->route('stock.index', ['company' => $company->slug])
            ->with('success', 'Stock transfer completed successfully.');
    }

    public function itemStock(Request $request, string $company, string $item): Response
    {
        $companyModel = CompanyContext::getCompany();

        $itemModel = Item::where('company_id', $companyModel->id)->findOrFail($item);

        $stockLevels = StockLevel::where('item_id', $itemModel->id)
            ->with('warehouse:id,name,code')
            ->get();

        $movements = StockMovement::where('item_id', $itemModel->id)
            ->with(['warehouse:id,name,code', 'createdBy:id,name'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return Inertia::render('inventory/stock/ItemStock', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
            ],
            'item' => $itemModel,
            'stockLevels' => $stockLevels,
            'movements' => $movements,
        ]);
    }
}
