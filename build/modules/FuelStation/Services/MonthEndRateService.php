<?php

namespace App\Modules\FuelStation\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The one place that says what rate a month's closing stock is valued at.
 *
 * Three methods (station settings, month_end_stock_valuation):
 *  - inventory_cost: recorded cost, written down to the next month's purchase rate when that is lower.
 *  - next_month_purchase_rate: the purchase rate in force on the 1st of the next month, up or down.
 *  - last_delivery_price: the rate of the last delivery bill before the 1st of the next month, up or down.
 *
 * rateFor() is what the month-end write-down, the monthly fuel profit and the stock statement agree on.
 */
class MonthEndRateService
{
    public const METHODS = ['inventory_cost', 'next_month_purchase_rate', 'last_delivery_price'];

    public function method(string $companyId): string
    {
        return DB::table('fuel.station_settings')->where('company_id', $companyId)
            ->value('month_end_stock_valuation') ?: 'inventory_cost';
    }

    /** Rate month $month's (YYYY-MM) closing stock is valued at. Null = no rate known. */
    public function rateFor(string $companyId, string $itemId, string $month): ?float
    {
        $nextFirst = Carbon::parse($month.'-01')->endOfMonth()->addDay()->toDateString();

        return $this->method($companyId) === 'last_delivery_price'
            ? $this->lastDeliveryRate($companyId, $itemId, $nextFirst)
            : $this->nextMonthPurchaseRate($companyId, $itemId, $nextFirst);
    }

    /** The purchase rate in force on $nextFirst: the latest rate change effective on or before it. */
    public function nextMonthPurchaseRate(string $companyId, string $itemId, string $nextFirst): ?float
    {
        $latest = DB::table('fuel.rate_changes')
            ->where('company_id', $companyId)->where('item_id', $itemId)
            ->whereDate('effective_date', '<=', $nextFirst)
            ->orderByDesc('effective_date')->first(['purchase_rate']);

        return $latest ? (float) $latest->purchase_rate : null;
    }

    /**
     * What a litre of stock cost on the last delivery before $before: the last purchase bill
     * before that date (weighted over that bill date), else the opening-stock entry's own cost.
     * Null when neither carries a cost. $tank limits it to bill lines delivered into a tank.
     */
    public function lastDeliveryRate(string $companyId, string $itemId, string $before, bool $tank = true): ?float
    {
        $bills = fn () => DB::table('acct.bill_line_items as l')
            ->join('acct.bills as b', 'b.id', '=', 'l.bill_id')
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')->whereNull('l.deleted_at')
            ->whereNotIn('b.status', ['draft', 'void', 'cancelled'])
            ->where('l.item_id', $itemId)
            ->when($tank, fn ($q) => $q->whereNotNull('l.warehouse_id'))
            ->where('b.bill_date', '<', $before);
        $lastDate = $bills()->max('b.bill_date');
        if ($lastDate) {
            $row = $bills()->whereDate('b.bill_date', $lastDate)->selectRaw('SUM(l.total) as amount, SUM(l.quantity) as qty')->first();
            if ((float) $row->qty > 0) {
                return round((float) $row->amount / (float) $row->qty, 4);
            }
        }

        $opening = DB::table('inv.stock_movements')
            ->where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('movement_type', 'opening')
            ->where('movement_date', '<', $before)
            ->where('unit_cost', '>', 0)
            ->selectRaw('SUM(total_cost) as amount, SUM(quantity) as qty')
            ->first();

        return $opening && (float) $opening->qty > 0 ? round((float) $opening->amount / (float) $opening->qty, 4) : null;
    }
}
