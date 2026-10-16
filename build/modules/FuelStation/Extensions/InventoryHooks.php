<?php

namespace App\Modules\FuelStation\Extensions;

use App\Models\Company;
use App\Modules\FuelStation\Models\DipStick;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Services\DailyCloseLockService;
use App\Modules\FuelStation\Services\FuelProductAccountMapper;
use App\Modules\FuelStation\Services\LubricantCostService;
use App\Modules\Inventory\Extensions\ItemAccountDefaults;
use App\Modules\Inventory\Extensions\ItemPriceHistory;
use App\Modules\Inventory\Extensions\StockReferenceLinks;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Validation\ValidationException;

/** What the fuel station adds to Inventory: price history, default accounts, stock links, tank dip stick. */
class InventoryHooks
{
    public static function register(): void
    {
        ItemPriceHistory::extendGuard('fuel_station', function (Item $item, string $companyId, string $date): void {
            if ($item->fuel_category) {
                throw ValidationException::withMessages(['item_id' => 'Fuel prices are set under Fuel prices.']);
            }
        });

        ItemPriceHistory::extendLockedMonths('fuel_station', fn (string $companyId): array => app(DailyCloseLockService::class)->lockedMonths($companyId));

        ItemPriceHistory::extendCorrectionTypes('fuel_station', [LubricantCostService::TYPE]);

        ItemPriceHistory::extendTimeline('fuel_station', function (Item $item, string $companyId, string $companySlug, string $today): ?array {
            if (! $item->fuel_category) {
                return null;
            }

            $rows = [];
            $rates = RateChange::where('company_id', $companyId)->where('item_id', $item->id)->get();
            $current = $rates->filter(fn ($r) => $r->effective_date->toDateString() <= $today)->sortByDesc('effective_date')->first();
            foreach ($rates as $rate) {
                $rows[] = [
                    'id' => 'rate-'.$rate->id, 'date' => $rate->effective_date->toDateString(), 'kind' => 'fuel_rate',
                    'label' => 'Fuel rate', 'price' => (float) $rate->sale_rate, 'quantity' => null,
                    'detail' => 'Purchase '.number_format((float) $rate->purchase_rate, 2),
                    'source_label' => 'Fuel prices', 'source_url' => "/{$companySlug}/fuel/rates",
                    'in_force' => $current !== null && $current->id === $rate->id, 'editable' => false,
                    'price_id' => null, 'purchase_price' => (float) $rate->purchase_rate, 'notes' => $rate->notes,
                ];
            }

            return ['replaces_sale_prices' => true, 'rows' => $rows];
        });

        ItemAccountDefaults::extend('fuel_station', function (Company $company, array $data, ?string $userId): ?array {
            if (! $company->hasModule('fuel_station')) {
                return null;
            }

            $accounts = app(FuelProductAccountMapper::class)->resolveAccounts($company->id, 'lubricant_packaged', $userId);

            return [
                'asset_account_id' => $accounts['asset']->id,
                'income_account_id' => $accounts['income']->id,
                'expense_account_id' => $accounts['expense']->id,
            ];
        });

        StockReferenceLinks::extendEarly('fuel_station', function (?string $ref, ?string $txnType, StockMovement $m, string $base): ?array {
            if ($ref === 'fuel.daily_close' || $txnType === 'fuel_daily_close') {
                $id = $ref === 'fuel.daily_close' ? ($m->reference_id ?: $m->gl_transaction_id) : $m->gl_transaction_id;

                return ['label' => 'Daily close', 'href' => $id ? "{$base}/fuel/daily-close/{$id}" : null];
            }

            return null;
        });

        StockReferenceLinks::extend('fuel_station', function (?string $ref, ?string $txnType, StockMovement $m, string $base): ?array {
            if ($ref === 'fuel.stock_writedown') {
                return ['label' => 'Month-end value', 'href' => null];
            }
            if ($ref === 'fuel.lubricant_cost' || $txnType === 'fuel_lubricant_cost') {
                return ['label' => 'Lubricant cost', 'href' => null];
            }

            return null;
        });

        StockReferenceLinks::extendStatus('fuel_station', fn (StockMovement $m): ?string => $m->reference_type === 'fuel.daily_close' ? 'Daily Close' : null);

        Warehouse::resolveRelationUsing('dipStick', fn (Warehouse $warehouse) => $warehouse->belongsTo(DipStick::class, 'dip_stick_id'));
    }
}
