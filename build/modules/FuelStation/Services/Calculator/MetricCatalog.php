<?php

namespace App\Modules\FuelStation\Services\Calculator;

use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Services\Calculator\Metrics\AccountMetric;
use App\Modules\FuelStation\Services\Calculator\Metrics\CardChargesMetric;
use App\Modules\FuelStation\Services\Calculator\Metrics\CardSwipesMetric;
use App\Modules\FuelStation\Services\Calculator\Metrics\CashShortOverMetric;
use App\Modules\FuelStation\Services\Calculator\Metrics\CustomerMetric;
use App\Modules\FuelStation\Services\Calculator\Metrics\DiscountsMetric;
use App\Modules\FuelStation\Services\Calculator\Metrics\ProfitabilityMetric;
use App\Modules\FuelStation\Services\Calculator\Metrics\RateMetric;
use App\Modules\FuelStation\Services\Calculator\Metrics\StatementLineMetric;
use App\Modules\FuelStation\Services\Calculator\Metrics\StockMetric;
use App\Modules\FuelStation\Services\Calculator\Metrics\TankGainLossMetric;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Every value the Calculator can read: a metric, the collection it is read for (a product, an
 * account, a customer ...) and when. Each metric is one evaluator that asks an existing report
 * service for its figure.
 */
class MetricCatalog
{
    private const UUID = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    private const NEEDS_ID = ['product', 'fuel', 'account', 'customer', 'channel', 'category', 'customer_category'];

    /** @var array<string,MetricEvaluator> */
    private array $metrics = [];

    public function __construct()
    {
        foreach ([
            new ProfitabilityMetric('sales', 'Sales', 'Rs', 'revenue'),
            new ProfitabilityMetric('litres_sold', 'Litres sold', 'L', 'quantity'),
            new ProfitabilityMetric('cost_of_sales', 'Cost of sales', 'Rs', 'cogs'),
            new ProfitabilityMetric('gross_profit', 'Gross profit', 'Rs', 'gross_profit'),
            new ProfitabilityMetric('profit_books', 'Profit by the books', 'Rs', 'book_profit'),
            new DiscountsMetric,
            new StatementLineMetric('net_profit', 'Net profit', 'net_profit'),
            new StatementLineMetric('expenses', 'Expenses', 'expenses'),
            new StatementLineMetric('salaries', 'Salaries', 'salaries'),
            new StatementLineMetric('other_income', 'Other income', 'other_income'),
            new AccountMetric('expense_account', 'Expense account', false),
            new AccountMetric('income_account', 'Income account', true),
            new CashShortOverMetric,
            new StockMetric('purchases_amount', 'Purchases', 'Rs', 'purchase_amount'),
            new StockMetric('purchases_qty', 'Quantity bought', 'L', 'received'),
            new StockMetric('stock_qty', 'Stock quantity', 'L', 'closing', true),
            new StockMetric('stock_value', 'Stock value', 'Rs', 'closing_value', true),
            new TankGainLossMetric,
            new RateMetric('sale_rate', 'Sale rate', true),
            new RateMetric('purchase_rate', 'Purchase rate', false),
            new CustomerMetric('customer_bought', 'Customer bought', 'bought'),
            new CustomerMetric('customer_paid', 'Customer paid', 'paid'),
            new CustomerMetric('customer_owed', 'Customer owes', 'closing'),
            new CardSwipesMetric,
            new CardChargesMetric,
        ] as $metric) {
            $this->metrics[$metric->key] = $metric;
        }
    }

    public function get(string $key): ?MetricEvaluator
    {
        return $this->metrics[$key] ?? null;
    }

    /** @return array<int,array<string,mixed>> */
    public function describe(): array
    {
        return array_values(array_map(fn (MetricEvaluator $m) => $m->describe(), $this->metrics));
    }

    /** The reason a value node is not usable, or null when its metric, collection and when are right. */
    public function validateValue(array $node): ?string
    {
        $metric = is_string($node['metric'] ?? null) ? $this->get($node['metric']) : null;
        if (! $metric) {
            return 'Pick what to read.';
        }

        $collection = $node['collection'] ?? ['type' => 'none'];
        if (! is_array($collection) || ! is_string($collection['type'] ?? null)) {
            return 'Pick what it is for.';
        }
        $type = $collection['type'];
        $accepted = in_array($type, $metric->collections, true) || ($type === 'fuel' && in_array('product', $metric->collections, true));
        if (! $accepted) {
            return "{$metric->label} cannot be read for that.";
        }
        if (in_array($type, self::NEEDS_ID, true)) {
            $id = $collection['id'] ?? null;
            $ok = is_string($id) && ($type === 'channel' ? (bool) preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $id) : (bool) preg_match(self::UUID, $id));
            if (! $ok) {
                return "Pick which one for {$metric->label}.";
            }
        }

        $when = $node['when'] ?? null;

        return is_array($when) ? WhenResolver::validate($when, $metric->takesDay) : 'Pick a period.';
    }

    /**
     * @param  array<string,mixed>  $node  a validated value node
     * @return array{label:string,value:?float,unit:?string,source_href:?string,note:?string,from:string,to:string}
     */
    public function evaluateValue(CalculatorContext $c, array $node, ?CarbonInterface $today = null): array
    {
        $metric = $this->get((string) $node['metric']);
        $collection = $node['collection'] ?? ['type' => 'none'];
        $when = (array) $node['when'];

        if ($metric->takesDay) {
            [$from, $whenLabel] = WhenResolver::day($when, $today);
            $to = $from;
        } else {
            [$from, $to, $whenLabel] = WhenResolver::range($when, $today);
        }

        $label = implode(' · ', array_filter([$metric->label, $this->collectionLabel($c, $collection), $whenLabel]));

        try {
            $result = $this->missing($c, $collection)
                ?? $metric->evaluate($c, $collection, $from, $to);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $result = new MetricResult(null, $metric->unitFor($c, $collection), null, 'Not found.');
        }

        return [
            'label' => $label,
            'value' => $result->value === null ? null : round($result->value, 4),
            'unit' => $result->unit,
            'source_href' => $result->href,
            'note' => $result->note,
            'from' => $from,
            'to' => $to,
        ];
    }

    /** A customer or channel that is gone (or never was) has no figure; say so rather than guess. */
    private function missing(CalculatorContext $c, array $collection): ?MetricResult
    {
        if (($collection['type'] ?? '') === 'customer'
            && ! Customer::where('company_id', $c->companyId)->whereKey((string) ($collection['id'] ?? ''))->exists()) {
            return new MetricResult(null, 'Rs', null, 'Customer not found.');
        }

        return null;
    }

    private function collectionLabel(CalculatorContext $c, array $collection): ?string
    {
        $id = (string) ($collection['id'] ?? '');

        return match ($collection['type'] ?? 'none') {
            'product', 'fuel' => $c->item($id)->name ?? 'Unknown product',
            'all_fuels' => 'All fuels',
            'all_products' => 'All products',
            'account' => $c->account($id)?->name ?? 'Unknown account',
            'customer' => Customer::where('company_id', $c->companyId)->whereKey($id)->value('name') ?? 'Unknown customer',
            'category' => DB::table('inv.item_categories')->where('company_id', $c->companyId)->where('id', $id)->value('name') ?? 'Unknown category',
            'customer_category' => DB::table('acct.customer_categories')->where('company_id', $c->companyId)->where('id', $id)->value('name') ?? 'Unknown category',
            'channel' => collect($c->settings()?->payment_channels ?? [])->firstWhere('code', $id)['label'] ?? $id,
            default => null,
        };
    }
}
