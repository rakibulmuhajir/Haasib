<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StoreFuelDeliveryRequest;
use App\Modules\FuelStation\Services\DailyCloseEntryService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fuel deliveries: every bill line that went into a tank, one row per delivery.
 *
 * A delivery is entered where it is received -- Daily Close > Purchases (one bill per supplier,
 * the tank, litres sold straight off the tanker, paid now) -- so this page only lists them, each
 * linked to its bill and to the close of its date. "New delivery" opens the next close at its
 * Purchases.
 */
class FuelReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        $date = fn ($value, Carbon $fallback) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) ? (string) $value : $fallback->toDateString();
        $from = $date($request->query('start_date'), now()->startOfMonth());
        $to = $date($request->query('end_date'), now());
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $lines = DB::table('acct.bill_line_items as l')
            ->join('acct.bills as b', 'b.id', '=', 'l.bill_id')
            ->join('inv.warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->leftJoin('inv.items as i', 'i.id', '=', 'l.item_id')
            ->leftJoin('acct.vendors as v', 'v.id', '=', 'b.vendor_id')
            ->where('b.company_id', $company->id)
            ->where('w.warehouse_type', 'tank')
            ->whereNull('b.deleted_at')->whereNull('l.deleted_at')
            ->whereNotIn('b.status', ['draft', 'void', 'cancelled'])
            ->whereBetween('b.bill_date', [$from, $to])
            ->orderByDesc('b.bill_date')->orderByDesc('b.bill_number')
            ->get([
                'b.id as bill_id', 'b.bill_number', 'b.bill_date', 'b.status', 'b.balance', 'b.total_amount',
                'v.name as supplier', 'i.name as fuel', 'w.name as tank',
                'l.quantity', 'l.direct_quantity', 'l.quantity_received', 'l.unit_price', 'l.total',
            ]);

        // The close of each delivery's date, where it was received (or will be).
        $closes = DB::table('acct.transactions')
            ->where('company_id', $company->id)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')->whereNull('reversed_by_id')
            ->whereBetween('transaction_date', [$from, $to])
            ->pluck('id', 'transaction_date')
            ->mapWithKeys(fn ($id, $day) => [substr((string) $day, 0, 10) => $id]);

        $rows = $lines->map(function ($l) use ($closes) {
            $day = substr((string) $l->bill_date, 0, 10);
            $direct = (float) $l->direct_quantity;
            $intoTank = (float) $l->quantity - $direct;

            return [
                'bill_id' => $l->bill_id,
                'bill_number' => $l->bill_number,
                'date' => $day,
                'supplier' => $l->supplier,
                'fuel' => $l->fuel,
                'tank' => $l->tank,
                'litres' => (float) $l->quantity,
                'into_tank' => $intoTank,
                'direct' => $direct,
                'received' => (float) $l->quantity_received >= $intoTank - 0.001,
                'rate' => (float) $l->unit_price,
                'amount' => (float) $l->total,
                'bill_status' => $l->status,
                'bill_balance' => (float) $l->balance,
                'close_id' => $closes[$day] ?? null,
            ];
        })->values();

        $nextClose = DB::table('acct.transactions')
            ->where('company_id', $company->id)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')->whereNull('reversed_by_id')
            ->max('transaction_date');

        return Inertia::render('FuelStation/Deliveries/Index', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'filters' => ['start_date' => $from, 'end_date' => $to],
            'rows' => $rows,
            'totals' => [
                'litres' => $rows->sum('litres'),
                'into_tank' => $rows->sum('into_tank'),
                'direct' => $rows->sum('direct'),
                'amount' => $rows->sum('amount'),
                'owed' => $rows->unique('bill_id')->sum('bill_balance'),
            ],
            'nextCloseDate' => $nextClose ? Carbon::parse($nextClose)->addDay()->toDateString() : now()->toDateString(),
            // For "Add delivery": who from, what into which tank, paid from where.
            'suppliers' => DB::table('acct.vendors')->where('company_id', $company->id)->whereNull('deleted_at')
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => DB::table('inv.warehouses as w')->join('inv.items as i', 'i.id', '=', 'w.linked_item_id')
                ->where('w.company_id', $company->id)->where('w.warehouse_type', 'tank')->where('w.is_active', true)
                ->orderBy('i.name')->orderBy('w.name')
                ->get(['i.id as item_id', 'i.name as item_name', 'w.id as tank_id', 'w.name as tank_name']),
            'paymentAccounts' => DB::table('acct.accounts')->where('company_id', $company->id)
                ->whereIn('subtype', ['cash', 'bank'])->where('is_active', true)->whereNull('deleted_at')
                ->orderByRaw("case when subtype = 'cash' then 0 else 1 end")->orderBy('code')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('fuel.receipts.index', ['company' => app(CurrentCompany::class)->get()->slug]);
    }

    /**
     * A delivery entered on its own: the same bill the close makes for a purchase, but its litres
     * are not received yet -- that day's close shows them as delivered and receives them when it
     * is posted. Paid now from a cash account shows in that day's close as cash out too.
     */
    public function store(StoreFuelDeliveryRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        try {
            $result = DB::transaction(fn () => app(DailyCloseEntryService::class)->purchase($company->id, $data['date'], [
                'supplier_id' => $data['supplier_id'],
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'paid_now' => (bool) ($data['paid_now'] ?? false),
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'lines' => [[
                    'item_id' => $data['item_id'],
                    'tank_id' => $data['tank_id'] ?? null,
                    'quantity' => $data['quantity'],
                    'direct_quantity' => $data['direct_quantity'] ?? null,
                    'unit_cost' => $data['unit_cost'] ?? null,
                    'line_total' => $data['line_total'] ?? null,
                ]],
            ], $request->user(), receive: false));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        $number = DB::table('acct.bills')->where('id', $result['bill_id'])->value('bill_number');

        return back()->with('success', "{$number} added. Received into the tank when the ".Carbon::parse($data['date'])->format('j M').' close is posted.');
    }

    /** A delivery is its bill. */
    public function show(Request $request, string $company, string $receipt): RedirectResponse
    {
        return redirect("/{$company}/bills/{$receipt}");
    }
}
