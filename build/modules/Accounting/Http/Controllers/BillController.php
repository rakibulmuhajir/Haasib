<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreBillRequest;
use App\Modules\Accounting\Http\Requests\ReceiveGoodsRequest;
use App\Modules\Accounting\Http\Requests\VoidBillRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $query = \App\Modules\Accounting\Models\Bill::with('vendor')
            ->where('company_id', $company->id)
            ->orderByDesc('bill_date');

        $receivableLineItems = fn () => DB::table('acct.bill_line_items as li')
            ->join('inv.items as items', 'items.id', '=', 'li.item_id')
            ->whereColumn('li.bill_id', 'acct.bills.id')
            ->where('li.company_id', $company->id)
            ->whereNull('li.deleted_at')
            ->whereNull('items.deleted_at')
            ->where('items.track_inventory', true)
            ->where('items.delivery_mode', 'requires_receiving')
            ->whereRaw('COALESCE(li.quantity_received, 0) < li.quantity');

        $query->addSelect([
            'receivable_items_count' => $receivableLineItems()->selectRaw('COUNT(*)'),
            'linked_items_count' => DB::table('acct.bill_line_items as li')
                ->whereColumn('li.bill_id', 'acct.bills.id')
                ->where('li.company_id', $company->id)
                ->whereNull('li.deleted_at')
                ->whereNotNull('li.item_id')
                ->selectRaw('COUNT(*)'),
        ]);

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->string('vendor_id'));
        }
        if ($request->filled('item_id')) {
            $itemId = $request->string('item_id')->toString();
            $query->whereExists(function ($sub) use ($company, $itemId) {
                $sub->selectRaw('1')
                    ->from('acct.bill_line_items as li')
                    ->whereColumn('li.bill_id', 'acct.bills.id')
                    ->where('li.company_id', $company->id)
                    ->whereNull('li.deleted_at')
                    ->where('li.item_id', $itemId);
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->boolean('needs_receiving')) {
            $query->where('status', 'paid')
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
                });
        }
        if ($request->filled('search')) {
            $term = $request->string('search');
            $query->where(function ($q) use ($term) {
                $q->where('bill_number', 'ilike', "%{$term}%")
                    ->orWhere('vendor_invoice_number', 'ilike', "%{$term}%");
            });
        }
        if ($request->filled('from_date')) {
            $query->where('bill_date', '>=', $request->string('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->where('bill_date', '<=', $request->string('to_date'));
        }

        $bills = $query->paginate(25)->withQueryString();
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('accounting/bills/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'bills' => $bills,
            'filters' => $request->only(['vendor_id', 'status', 'search', 'from_date', 'to_date', 'item_id', 'needs_receiving']),
            'vendors' => $vendors,
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $selectedVendorId = $request->string('vendor')->toString();
        if ($selectedVendorId === '' && $request->filled('vendor_id')) {
            $selectedVendorId = $request->string('vendor_id')->toString();
        }

        $selectedVendor = null;
        if ($selectedVendorId !== '') {
            $selectedVendor = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
                ->where('id', $selectedVendorId)
                ->first(['id', 'name', 'payment_terms', 'base_currency']);
            if (! $selectedVendor) {
                $selectedVendorId = '';
            }
        }

        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name', 'payment_terms', 'base_currency']);

        $expenseAccounts = Account::where('company_id', $company->id)
            ->whereIn('type', ['expense', 'cogs', 'asset'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $apAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // Inventory data (if module enabled)
        $inventoryEnabled = $company->isModuleEnabled('inventory');
        $items = [];
        $warehouses = [];

        if ($inventoryEnabled && class_exists(\App\Modules\Inventory\Models\Item::class)) {
            $items = \App\Modules\Inventory\Models\Item::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'cost_price', 'unit_of_measure', 'track_inventory']);

            $warehouses = \App\Modules\Inventory\Models\Warehouse::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_primary']);
        }

        // Owner mode → simplified quick create (but use full form if inventory enabled with items)
        $hasInventoryItems = $inventoryEnabled && count($items) > 0;
        if ($this->prefersOwnerMode($request) && !$hasInventoryItems) {
            $isFuelStation = ($company->industry_code ?? null) === 'fuel_station'
                || ($company->industry ?? null) === 'fuel_station'
                || $company->isModuleEnabled('fuel_station');

            $defaultExpenseAccountId = $isFuelStation
                ? (Account::where('company_id', $company->id)
                    ->where('code', '1200')
                    ->where('is_active', true)
                    ->value('id')
                    ?? Account::where('company_id', $company->id)
                        ->where('subtype', 'inventory')
                        ->where('is_active', true)
                        ->orderBy('code')
                        ->value('id'))
                : null;

            return Inertia::render('accounting/bills/QuickCreate', [
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'base_currency' => $company->base_currency,
                    'default_payment_terms' => $company->default_payment_terms ?? null,
                ],
                'recentVendors' => [],
                'selectedVendorId' => $selectedVendorId ?: null,
                'selectedVendor' => $selectedVendor,
                'expenseAccounts' => $expenseAccounts,
                'defaultExpenseAccountId' => $defaultExpenseAccountId,
                'defaultTaxCode' => null,
                'defaultTerms' => $company->default_payment_terms ?? null,
            ]);
        }

        $isFuelStation = ($company->industry_code ?? null) === 'fuel_station'
            || ($company->industry ?? null) === 'fuel_station'
            || $company->isModuleEnabled('fuel_station');

        $defaultExpenseAccountId = $isFuelStation
            ? (Account::where('company_id', $company->id)
                ->where('code', '1200')
                ->where('is_active', true)
                ->value('id')
                ?? Account::where('company_id', $company->id)
                    ->where('subtype', 'inventory')
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->value('id'))
            : null;

        return Inertia::render('accounting/bills/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'vendors' => $vendors,
            'selectedVendorId' => $selectedVendorId ?: null,
            'expenseAccounts' => $expenseAccounts,
            'apAccounts' => $apAccounts,
            'defaultExpenseAccountId' => $defaultExpenseAccountId,
            'inventoryEnabled' => $inventoryEnabled,
            'items' => $items,
            'warehouses' => $warehouses,
        ]);
    }

    protected function prefersOwnerMode(Request $request): bool
    {
        return $request->cookie('haasib_user_mode', 'owner') !== 'accountant';
    }

    public function store(StoreBillRequest $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        $commandBus = app(CommandBus::class);

        $payload = $request->validated();
        if ($request->boolean('pay_immediately')) {
            $payload['status'] = 'received';
        }

        $result = $commandBus->dispatch('bill.create', [
            ...$payload,
            'company_id' => $company->id,
        ], $request->user());

        if ($request->boolean('pay_immediately')) {
            $billId = $result['data']['id'] ?? null;
            if (!$billId) {
                return back()->with('success', $result['message'] ?? 'Bill created');
            }

            $bill = Bill::where('company_id', $company->id)
                ->with(['vendor'])
                ->findOrFail($billId);

            $paymentAccountId = $company->bank_account_id;
            if (!$paymentAccountId) {
                $paymentAccountId = Account::where('company_id', $company->id)
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->value('id');
            }

            if (!$paymentAccountId) {
                return back()->withErrors(['payment_account_id' => 'No bank/cash account found. Add one in onboarding or Chart of Accounts.']);
            }

            $commandBus->dispatch('bill_payment.create', [
                'vendor_id' => $bill->vendor_id,
                'payment_date' => $bill->bill_date,
                'amount' => (float) $bill->total_amount,
                'currency' => $bill->currency,
                'base_currency' => $bill->base_currency,
                'exchange_rate' => $bill->exchange_rate,
                'payment_method' => 'cash',
                'payment_account_id' => $paymentAccountId,
                'ap_account_id' => $bill->vendor?->ap_account_id,
                'allocations' => [
                    [
                        'bill_id' => $bill->id,
                        'amount_allocated' => (float) $bill->total_amount,
                    ],
                ],
            ], $request->user());

            $billId = $result['data']['id'] ?? null;
            return redirect()->route('bills.show', [$company->slug, $billId])
                ->with('success', 'Bill saved and paid');
        }

        $billId = $result['data']['id'] ?? null;
        return redirect()->route('bills.show', [$company->slug, $billId])
            ->with('success', $result['message'] ?? 'Bill created');
    }

    public function show(string $company, string $bill): Response
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        $record = \App\Modules\Accounting\Models\Bill::with(['vendor:id,name,logo_url', 'lineItems.item'])
            ->where('company_id', $companyModel->id)
            ->findOrFail($bill);

        $journalTransactionId = Transaction::where('company_id', $companyModel->id)
            ->where('reference_type', 'acct.bills')
            ->where('reference_id', $record->id)
            ->orderByDesc('posting_date')
            ->value('id');

        return Inertia::render('accounting/bills/Show', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
                'logo_url' => $companyModel->logo_url,
            ],
            'bill' => $record,
            'journalTransactionId' => $journalTransactionId,
            'inventoryEnabled' => $companyModel->isModuleEnabled('inventory'),
        ]);
    }

    public function edit(string $company, string $bill): Response
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        $record = \App\Modules\Accounting\Models\Bill::with('lineItems')
            ->where('company_id', $companyModel->id)
            ->findOrFail($bill);
        $vendors = \App\Modules\Accounting\Models\Vendor::where('company_id', $companyModel->id)
            ->orderBy('name')
            ->get(['id', 'name', 'payment_terms', 'base_currency']);

        $expenseAccounts = Account::where('company_id', $companyModel->id)
            ->whereIn('type', ['expense', 'cogs', 'asset'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $apAccounts = Account::where('company_id', $companyModel->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        // Inventory data (if module enabled)
        $inventoryEnabled = $companyModel->isModuleEnabled('inventory');
        $items = [];
        $warehouses = [];

        if ($inventoryEnabled && class_exists(\App\Modules\Inventory\Models\Item::class)) {
            $items = \App\Modules\Inventory\Models\Item::where('company_id', $companyModel->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'cost_price', 'unit_of_measure', 'track_inventory']);

            $warehouses = \App\Modules\Inventory\Models\Warehouse::where('company_id', $companyModel->id)
                ->where('is_active', true)
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_primary']);
        }

        return Inertia::render('accounting/bills/Edit', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
            ],
            'bill' => $record,
            'vendors' => $vendors,
            'expenseAccounts' => $expenseAccounts,
            'apAccounts' => $apAccounts,
            'inventoryEnabled' => $inventoryEnabled,
            'items' => $items,
            'warehouses' => $warehouses,
        ]);
    }

    public function update(StoreBillRequest $request, string $company, string $bill): RedirectResponse
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        $commandBus = app(CommandBus::class);

        $payload = $request->validated();

        try {
            $result = $commandBus->dispatch('bill.update', [
                'id' => $bill,
                ...$payload,
                'company_id' => $companyModel->id,
            ], $request->user());

            return redirect()->route('bills.show', [$companyModel->slug, $bill])
                ->with('success', $result['message'] ?? 'Bill updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Request $request, string $company, string $bill): RedirectResponse
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        try {
            app(CommandBus::class)->dispatch('bill.delete', ['id' => $bill, 'company_id' => $companyModel->id], $request->user());

            return back()->with('success', 'Bill deleted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function receive(Request $request, string $company, string $bill): RedirectResponse
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        try {
            app(CommandBus::class)->dispatch('bill.receive', ['id' => $bill], $request->user());

            return back()->with('success', 'Bill marked as received');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function void(VoidBillRequest $request, string $company, string $bill): RedirectResponse
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();

        try {
            app(CommandBus::class)->dispatch('bill.void', [
                'id' => $bill,
                ...$request->validated(),
                'company_id' => $companyModel->id,
            ], $request->user());

            return back()->with('success', 'Bill voided');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Receive goods for a bill (physical inventory receipt).
     * Separate from receiving the bill document.
     */
    public function receiveGoods(ReceiveGoodsRequest $request, string $company, string $bill): RedirectResponse
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        $validated = $request->validated();

        try {
            $result = app(CommandBus::class)->dispatch('bill.receive_goods', [
                'id' => $bill,
                ...$validated,
            ], $request->user());

            return back()->with('success', $result['message'] ?? 'Goods received');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
