<?php

namespace App\Modules\FuelStation\Services\Calculator;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountStatementService;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Modules\FuelStation\Services\ProfitStatementService;
use App\Modules\FuelStation\Services\StockStatementService;
use Illuminate\Support\Facades\DB;

/**
 * What the evaluators share for one run: the company, and each report run once per range, so a
 * formula that reads Sales and Litres sold for the same month builds that report a single time.
 */
final class CalculatorContext
{
    private array $profitability = [];
    private array $statements = [];
    private array $stock = [];
    private ?array $items = null;
    private ?array $keys = null;

    public function __construct(public readonly string $companyId, public readonly string $slug) {}

    public function profitability(string $from, string $to): array
    {
        return $this->profitability["$from|$to"] ??= app(ProductProfitabilityReportService::class)->run($this->companyId, $from, $to, 'day', 'all');
    }

    public function profitStatement(string $from, string $to): array
    {
        return $this->statements["$from|$to"] ??= app(ProfitStatementService::class)->run($this->companyId, $from, $to);
    }

    public function stock(string $itemId, string $from, string $to): array
    {
        return $this->stock["$itemId|$from|$to"] ??= app(StockStatementService::class)->run($this->companyId, $itemId, $from, $to);
    }

    /** @return array<string,object> items by id: id, name, fuel_category, unit_of_measure */
    public function items(): array
    {
        return $this->items ??= DB::table('inv.items')->where('company_id', $this->companyId)->whereNull('deleted_at')
            ->get(['id', 'name', 'fuel_category', 'unit_of_measure', 'category_id'])->keyBy('id')->all();
    }

    public function item(string $id): ?object
    {
        return $this->items()[$id] ?? null;
    }

    /** @return array<int,object> the items filed under a product category */
    public function categoryItems(string $categoryId): array
    {
        return array_values(array_filter($this->items(), fn ($i) => $i->category_id === $categoryId));
    }

    /** Litres for a fuel (or an item sold by the litre), else a plain quantity. */
    public function quantityUnit(?object $item): string
    {
        if (! $item) {
            return 'qty';
        }

        return $item->fuel_category || in_array(strtolower((string) $item->unit_of_measure), ['l', 'ltr', 'liter', 'litre', 'liters', 'litres'], true) ? 'L' : 'qty';
    }

    /** The profitability report's row key for an item. */
    public function productKey(string $itemId): ?string
    {
        return app(ProductProfitabilityReportService::class)->keyForItem($this->companyId, $itemId);
    }

    /** The profitability report's row keys that are fuels. */
    public function fuelKeys(): array
    {
        if ($this->keys === null) {
            $this->keys = [];
            foreach ($this->items() as $item) {
                if ($item->fuel_category && ($key = $this->productKey($item->id))) {
                    $this->keys[$key] = true;
                }
            }
        }

        return $this->keys;
    }

    /** Litres when every item of the set is measured in litres, else a plain quantity. */
    public function quantityUnitOf(array $items): string
    {
        if ($items === []) {
            return 'qty';
        }
        foreach ($items as $item) {
            if ($this->quantityUnit($item) !== 'L') {
                return 'qty';
            }
        }

        return 'L';
    }

    public function account(string $id): ?Account
    {
        return Account::where('company_id', $this->companyId)->whereNull('deleted_at')->find($id);
    }

    /** What an account moved over the range, in its own direction (closing - opening, as its statement shows). */
    public function accountMovement(Account $account, string $from, string $to): float
    {
        $statement = app(AccountStatementService::class)->statement($account, $from, $to);

        return round((float) $statement['closing_balance'] - (float) $statement['opening_balance'], 2);
    }

    public function settings(): ?StationSettings
    {
        return StationSettings::where('company_id', $this->companyId)->first();
    }

    public function expenseStatementLink(string $accountId, string $from, string $to): string
    {
        return "/{$this->slug}/reports/statements?kind=expense&id={$accountId}&from={$from}&to={$to}";
    }

    public function profitLink(string $from, string $to): string
    {
        return "/{$this->slug}/reports/profit-loss?start={$from}&end={$to}";
    }
}
