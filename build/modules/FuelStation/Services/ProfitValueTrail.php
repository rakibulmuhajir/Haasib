<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\TankReading;
use Illuminate\Support\Facades\DB;

/** A read-only explanation graph assembled from the report's actual contributions. */
class ProfitValueTrail
{
    public const HINTS = [
        'gross_profit' => 'Sales − cost of sales. Explore the contributing sales and costs.',
        'revenue' => 'Sum of posted daily-close sales and direct-delivery invoice totals.',
        'cogs' => 'Cost of goods sold, including live cost corrections and stock valuation adjustments.',
        'quantity' => 'Quantity sold through daily closes plus direct deliveries recorded on bills.',
        'purchased_quantity' => 'Quantity on live purchase bill lines naming a tank or store.',
    ];

    private array $nodes = [];

    private array $contributions = [];

    private array $roots = [];

    private array $bookComponents = [];

    public function graph(array $context): array
    {
        return ['nodes' => $this->nodes, 'roots' => $this->roots, 'context' => $context];
    }

    public function root(string $key, string $id): void
    {
        $this->roots[$key] = $id;
    }

    /** The same posted-account inputs used by MonthEndStockValuationService::accountBalance. */
    public function book(string $companyId, string $key, string $accountId, string $dayBefore, string $endDate, array $row, array $statement): void
    {
        $entries = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('t.company_id', $companyId)->where('je.account_id', $accountId)
            ->whereIn('t.status', ['posted', 'locked'])->whereNull('t.deleted_at')
            ->whereDate('t.transaction_date', '<=', $endDate)->orderBy('t.transaction_date')
            ->get(['t.id', 't.transaction_number', 't.transaction_date', 'je.debit_amount', 'je.credit_amount']);
        $opening = [];
        $closing = [];
        foreach ($entries as $entry) {
            $date = substr((string) $entry->transaction_date, 0, 10);
            $id = $this->node('Stock account entry · '.$entry->transaction_number,
                (float) $entry->debit_amount - (float) $entry->credit_amount, 'money',
                'Signed contribution to this product’s stock account in the posted books.', [],
                ['kind' => 'journal', 'id' => $entry->id, 'label' => $entry->transaction_number, 'date' => $date], 'Debit − credit');
            $closing[] = $id;
            if ($date <= $dayBefore) {
                $opening[] = $id;
            }
        }
        $purchases = [];
        foreach ($statement['rows'] as $day) {
            foreach ($day['bills'] ?? [] as $bill) {
                $purchases[] = $this->node('Stock purchases · '.$bill['bill_number'], (float) $bill['amount'], 'money',
                    'The item’s purchase line totals included by the stock statement, including direct deliveries.', [],
                    ['kind' => 'bill', 'id' => $bill['id'], 'label' => $bill['bill_number'], 'date' => $day['date']]);
            }
        }
        $this->bookComponents[$key] = [
            $this->node('Closing stock value', $row['book_closing'], 'money', 'Posted stock-account balance through '.$endDate.'.', $this->reconciledChildren($closing, $row['book_closing']), null, 'Sum of debits − credits, rounded'),
            $this->node('Opening stock value', $row['book_opening'], 'money', 'Posted stock-account balance through '.$dayBefore.', before this report starts.', $this->reconciledChildren($opening, $row['book_opening']), null, 'Sum of debits − credits, rounded'),
            $this->node('Stock purchases', $row['book_purchases'], 'money', 'Purchase amounts from the same stock statement used by the report.', $this->reconciledChildren($purchases, $row['book_purchases']), null, 'Sum of purchase line totals, rounded'),
        ];
    }

    public function stockVariance(string $key, TankReading $reading, float $variance, float $cost, array $history, bool $estimated): void
    {
        if ($variance == 0) {
            return;
        }
        $source = ['kind' => 'tank_reading', 'id' => $reading->id, 'label' => 'Tank reading', 'date' => $reading->reading_date->toDateString()];
        $physical = $this->node('Recorded dip', (float) $reading->dip_measurement_liters, 'L', 'The physical quantity saved on the original tank reading.', [], $source + ['field' => 'dip_measurement_liters']);
        $expected = $this->node('Recorded expected stock', (float) $reading->system_calculated_liters, 'L', 'The expected quantity saved on the original tank reading.', [], $source + ['field' => 'system_calculated_liters']);
        $original = $this->node('Original variance', (float) $reading->variance_liters, 'L', 'The original saved variance before subsequent corrections.',
            abs((float) $reading->dip_measurement_liters - (float) $reading->system_calculated_liters - (float) $reading->variance_liters) < 0.005 ? [$physical, $expected] : [], $source, 'Recorded dip − recorded expected stock');
        $children = [$original];
        foreach ($history as $correction) {
            $effects = is_array($correction->effects) ? $correction->effects : json_decode($correction->effects, true);
            $correctionSource = ['kind' => 'daily_close', 'id' => $correction->close_transaction_id, 'label' => 'Reading correction · revision '.$correction->revision, 'date' => $source['date']];
            $children[] = $this->node('Physical-stock correction', (float) ($effects['physical_liters_effect'] ?? 0), 'L', 'Saved correction effect on measured stock.', [], $correctionSource);
            $children[] = $this->node('Expected-stock correction', -(float) ($effects['expected_liters_effect'] ?? 0), 'L', 'Negated saved correction effect on expected stock, so the contributions sum to the corrected variance.', [], $correctionSource);
        }
        $signed = $this->node('Corrected variance', $variance, 'L', 'The same original reading and correction effects used by this report.', $children, null, 'Original variance + physical corrections − expected corrections');
        $quantity = $this->node($variance < 0 ? 'Stock shortage' : 'Stock surplus', abs($variance), 'L', 'Magnitude of the corrected variance.', [$signed], null, 'Absolute corrected variance');
        $unitCost = $this->node('Variance valuation cost', $cost, 'money/L', $estimated ? 'Historical correction cost is unavailable; this report falls back to the current item average cost or cost price.' : 'The unit cost saved in the latest reading correction.', [], $estimated ? null : ['kind' => 'daily_close', 'id' => $history[array_key_last($history)]->close_transaction_id, 'label' => 'Latest reading correction', 'date' => $source['date'], 'field' => 'effects.unit_cost'], null, $estimated);
        $value = $this->node('Stock variance valuation', round(abs($variance) * $cost, 2), 'money', 'Valuation of this reading’s shortage or surplus.', [$quantity, $unitCost], null, 'Absolute variance × valuation cost, rounded', $estimated);
        $field = $variance < 0 ? 'stock_loss' : 'stock_gain';
        $this->contributions['product:'.$key][$field.'_quantity'][] = $quantity;
        $this->contributions['product:'.$key][$field.'_value'][] = $value;
    }

    public function rateChange(string $scope, array $row, Transaction $transaction): void
    {
        $source = $this->source($transaction);
        foreach (['old_rate' => 'Earlier price', 'new_rate' => 'Later price', 'old_rate_liters' => 'Litres at earlier price', 'new_rate_liters' => 'Litres at later price', 'fallback_liters' => 'Fallback litres'] as $field => $label) {
            $this->roots[$scope.':'.$field] = $this->node($label, (float) $row[$field], str_ends_with($field, 'rate') ? 'money/L' : 'L', 'Saved in this daily close’s rate-change segments. The original price setter is not recorded here.', [], $source + ['field' => 'rate_change_segments.'.$field]);
        }
        $this->roots[$scope.':estimated_rate_change_effect'] = $this->node('Estimated price effect', $row['estimated_rate_change_effect'], 'money', 'The estimated difference compared with selling the later-price litres at the earlier price. This is not additional sales revenue.', [$this->roots[$scope.':new_rate'], $this->roots[$scope.':old_rate'], $this->roots[$scope.':new_rate_liters']], $source, '(Later price − earlier price) × later-price litres, rounded', true);
    }

    public function node(string $label, float $value, string $unit, string $explanation, array $children = [], ?array $source = null, ?string $formula = null, bool $estimated = false): string
    {
        $id = 'n'.(count($this->nodes) + 1);
        $this->nodes[$id] = compact('id', 'label', 'value', 'unit', 'explanation', 'children', 'source', 'formula', 'estimated');

        return $id;
    }

    public function source(Transaction $transaction, string $kind = 'daily_close'): array
    {
        return [
            'kind' => $kind,
            'id' => $transaction->id,
            'label' => $transaction->transaction_number,
            'date' => $transaction->transaction_date->toDateString(),
            'recorded_at' => $transaction->posted_at?->toISOString() ?? $transaction->created_at?->toISOString(),
            'recorded_by_id' => $transaction->posted_by_user_id ?? $transaction->created_by_user_id,
        ];
    }

    public function contribute(string $product, string $period, array $fields): void
    {
        foreach ($fields as $field => $id) {
            $this->contributions['product:'.$product][$field][] = $id;
            $this->contributions['period:'.$period][$field][] = $id;
        }
    }

    public function closeSale(array $row, Transaction $close, ?string $itemId, string $period, array $corrections): void
    {
        $source = $this->source($close);
        $metadata = $close->metadata ?? [];
        $quantityChildren = [];
        $revenueChildren = [];
        if ($row['source'] === 'fuel') {
            foreach ($metadata['posting_snapshot']['nozzles'] ?? [] as $nozzle) {
                if (! $itemId || ($nozzle['item_id'] ?? null) !== $itemId) {
                    continue;
                }
                $litres = (float) ($nozzle['liters_dispensed'] ?? 0);
                $meterChildren = [];
                foreach (['closing_electronic' => 'Closing meter', 'opening_electronic' => 'Opening meter', 'returned_liters' => 'Returned litres'] as $field => $label) {
                    if (isset($nozzle[$field])) {
                        $meterChildren[] = $this->node($label, (float) $nozzle[$field], 'L', 'Recorded in this daily close’s nozzle snapshot.', [], $source + ['field' => 'posting_snapshot.nozzles.'.$field]);
                    }
                }
                $quantityChildren[] = $this->node('Nozzle litres sold', $litres, 'L', 'The litres saved for this nozzle when the close posted.', $meterChildren, $source,
                    isset($nozzle['closing_electronic'], $nozzle['opening_electronic']) ? 'Closing meter − opening meter − returned litres' : null);

                // Segments are evidence, not a second pricing engine. Only use them as a
                // breakdown when they reconcile to the recorded nozzle revenue.
                $segments = $nozzle['rate_segments'] ?? [];
                $segmentChildren = [];
                $recordedRevenue = (float) ($nozzle['revenue'] ?? 0);
                if ($segments && abs(array_sum(array_column($segments, 'amount')) - $recordedRevenue) < 0.005) {
                    foreach ($segments as $segment) {
                        $q = (float) ($segment['liters'] ?? 0);
                        $rate = (float) ($segment['rate'] ?? 0);
                        $qId = $this->node('Litres at this price', $q, 'L', 'Saved in the posted rate segment.', [], $source);
                        $rateId = $this->node('Price used', $rate, 'money/L', 'The historical price saved in this close, not today’s price. The snapshot does not identify who originally set it.', [], $source + ['field' => 'posting_snapshot.nozzles.rate_segments.rate']);
                        $segmentChildren[] = $this->node('Sales at this price', (float) ($segment['amount'] ?? 0), 'money', 'The amount saved for this price segment.', [$qId, $rateId], $source, 'Litres × price');
                    }
                }
                $revenueChildren[] = $this->node('Nozzle sales', $recordedRevenue, 'money', $segmentChildren ? 'Sum of the saved price segments.' : 'Recorded sales amount. A complete historical price split is unavailable.', $segmentChildren, $source);
            }
            // Do not present incomplete or legacy snapshots as a complete breakdown.
            $quantityChildren = $this->reconciledChildren($quantityChildren, $row['quantity']);
            $revenueChildren = $this->reconciledChildren($revenueChildren, $row['revenue']);
        } elseif (isset($row['recorded_price'])) {
            $price = $this->node('Price used', $row['recorded_price'], 'money/'.$row['unit'], 'The unit price saved on this other-sale line when the daily close posted. Its original price-setting history is not recorded here.', [], $source + ['field' => 'other_sales_details.unit_price']);
            $quantity = $this->node('Quantity entered', $row['quantity'], $row['unit'], 'Saved on this other-sale line in the daily close.', [], $source + ['field' => 'other_sales_details.quantity']);
            $revenueChildren = [$quantity, $price];
        }

        $quantityId = $this->node($row['name'].' · quantity sold', $row['quantity'], $row['unit'], 'Recorded in '.$close->transaction_number.'.', $quantityChildren, $source + ['field' => $row['source'] === 'fuel' ? 'fuel_sales.liters' : 'other_sales_details.quantity']);
        $revenueId = $this->node($row['name'].' · sales', $row['revenue'], 'money', 'Recorded sales from this daily close. Credit sales are already included; receiving their payment does not add another sale.', $revenueChildren, $source,
            $row['source'] !== 'fuel' && isset($row['recorded_price']) && abs(round($row['quantity'] * $row['recorded_price'], 2) - $row['revenue']) < 0.005 ? 'Quantity × price' : null);

        $costChildren = [];
        if ($row['source'] === 'fuel') {
            $deltas = [];
            foreach ($corrections as $correction) {
                foreach ($correction['lines'] ?? [] as $line) {
                    if (($line['fuel_category'] ?? null) === $row['key']) {
                        $deltas[] = $this->node('Posted cost correction', (float) ($line['cogs_delta'] ?? 0), 'money', 'This adjustment is included once, including when it has been folded into the close.', [], [
                            'kind' => 'journal', 'id' => $correction['transaction_id'], 'label' => 'Cost correction', 'date' => $source['date'],
                        ]);
                    }
                }
            }
            if ($deltas) {
                $base = $row['cogs'] - array_sum(array_map(fn ($id) => $this->nodes[$id]['value'], $deltas));
                $costChildren = [$this->node('Cost before corrections', $base, 'money', 'Recorded cost before the listed correction journals.', [], $source), ...$deltas];
            }
        } else {
            $unitCost = $row['quantity'] > 0 ? $row['cogs'] / $row['quantity'] : 0;
            $costChildren = [$quantityId, $this->node('Unit cost used', $unitCost, 'money/'.$row['unit'], $row['cost_basis'], [], $row['estimated_cogs'] ? null : $source, null, $row['estimated_cogs'])];
        }
        $costId = $this->node($row['name'].' · cost of sales', $row['cogs'], 'money', $row['source'] === 'fuel' ? 'Posted fuel cost, including any live cost corrections. This is the cost of the fuel sold, not every purchase in the period.' : $row['cost_basis'], $costChildren, $source, $costChildren ? ($row['source'] === 'fuel' ? 'Original cost + corrections' : 'Quantity × unit cost, rounded') : null, $row['estimated_cogs']);
        $this->contribute($row['key'], $period, ['quantity' => $quantityId, 'revenue' => $revenueId, 'cogs' => $costId]);
    }

    public function directSale(string $key, string $period, array $statementRow, float $quantity, float $revenue, float $cost): void
    {
        $date = $statementRow['date'];
        $quantities = [];
        $costs = [];
        foreach ($statementRow['bills'] ?? [] as $bill) {
            if (($bill['direct'] ?? 0) <= 0 || ($bill['quantity'] ?? 0) <= 0) {
                continue;
            }
            $source = ['kind' => 'bill', 'id' => $bill['id'], 'label' => $bill['bill_number'], 'date' => $date, 'recorded_by_id' => $bill['recorded_by_id'] ?? null, 'recorded_at' => $bill['recorded_at'] ?? null];
            $direct = $this->node('Quantity sold off tanker', (float) $bill['direct'], 'L', 'The direct quantity recorded on this purchase bill.', [], $source + ['field' => 'bill_line_items.direct_quantity']);
            $billQuantity = $this->node('Purchased quantity', (float) $bill['quantity'], 'L', 'The quantity on the contributing bill lines.', [], $source);
            $amount = $this->node('Purchase amount', (float) $bill['amount'], 'money', 'Total of the contributing bill lines.', [], $source);
            $quantities[] = $direct;
            $costs[] = $this->node('Cost of direct delivery', (float) $bill['amount'] * (float) $bill['direct'] / (float) $bill['quantity'], 'money', 'Only the share of this bill sold directly is charged to these sales.', [$amount, $direct, $billQuantity], $source, 'Purchase amount × direct quantity ÷ purchased quantity');
        }
        $sales = [];
        foreach ($statementRow['direct_invoices'] ?? [] as $invoice) {
            $source = ['kind' => 'invoice', 'id' => $invoice['id'], 'label' => $invoice['invoice_number'], 'date' => $date, 'recorded_by_id' => $invoice['recorded_by_id'] ?? null, 'recorded_at' => $invoice['recorded_at'] ?? null];
            $children = [$this->node('Invoiced quantity', (float) $invoice['quantity'], 'L', 'Saved on this direct-delivery invoice line.', [], $source)];
            if (isset($invoice['unit_price'])) {
                $children[] = $this->node('Price used', (float) $invoice['unit_price'], 'money/L', 'The unit price saved on the invoice line, not the current product price.', [], $source + ['field' => 'invoice_line_items.unit_price']);
            }
            $sales[] = $this->node('Direct-delivery invoice sales', (float) $invoice['amount'], 'money', 'The line total used by the stock statement, including any line adjustments. Legacy unlinked lines use the stock statement’s date/quantity matching.', $children, $source);
        }
        $this->contribute($key, $period, [
            'quantity' => $this->node('Direct-delivery quantity · '.$date, $quantity, 'L', 'Fuel sold straight off the tanker, taken from the bills rather than pump meters.', $this->reconciledChildren($quantities, $quantity)),
            'revenue' => $this->node('Direct-delivery sales · '.$date, $revenue, 'money', 'Direct-delivery invoice line totals used by the stock statement.', $this->reconciledChildren($sales, $revenue)),
            'cogs' => $this->node('Direct-delivery cost · '.$date, $cost, 'money', 'Sum of the bills’ direct-delivery shares, rounded to two decimals.', $costs, null, 'Sum of allocated bill costs, rounded'),
        ]);
    }

    public function purchase(string $key, string $period, object $line, string $unit): void
    {
        $source = ['kind' => 'bill', 'id' => $line->bill_id, 'label' => $line->bill_number, 'date' => (string) $line->bill_date];
        $id = $this->node('Purchased quantity', (float) $line->quantity, $unit, 'A live purchase bill line naming a tank or store. Money-only supplier share bills are excluded.', [], $source + ['field' => 'bill_line_items.quantity']);
        $this->contribute($key, $period, ['purchased_quantity' => $id]);
    }

    public function writedown(string $key, string $period, Transaction $transaction, float $amount): void
    {
        $id = $this->node('Month-end stock valuation', $amount, 'money', 'The posted valuation adjustment for this fuel. A positive amount increases cost; a negative amount reduces it.', [], $this->source($transaction, 'journal') + ['field' => 'metadata.amount']);
        $this->contribute($key, $period, ['cogs' => $id]);
    }

    private function reconciledChildren(array $children, float $value): array
    {
        return $children && abs(array_sum(array_map(fn ($id) => $this->nodes[$id]['value'], $children)) - $value) < 0.005 ? $children : [];
    }

    public function finish(array $report): array
    {
        foreach (['productRows' => 'product', 'periodRows' => 'period'] as $collection => $kind) {
            foreach ($report[$collection] as $row) {
                $scope = $kind.':'.$row['key'];
                $label = $row['name'] ?? $row['label'];
                $this->aggregate($scope, $label, $row, $this->contributions[$scope] ?? [], $row['unit'] ?? 'quantity');
            }
        }
        $totalChildren = [];
        foreach (['quantity', 'purchased_quantity', 'revenue', 'cogs', 'gross_profit', 'book_profit', 'profit', 'stock_loss_quantity', 'stock_gain_quantity', 'stock_loss_value', 'stock_gain_value'] as $field) {
            foreach ($report['productRows'] as $row) {
                if (isset($this->roots['product:'.$row['key'].':'.$field])) {
                    $totalChildren[$field][] = $this->roots['product:'.$row['key'].':'.$field];
                }
            }
        }
        $totals = $report['totals'];
        $totals['estimated_cogs'] = collect($report['productRows'])->contains(fn ($row) => $row['estimated_cogs']);
        $this->aggregate('total', 'Selected products', $totals, $totalChildren, 'quantity');

        return ['nodes' => $this->nodes, 'roots' => $this->roots, 'context' => $report['filters']];
    }

    private function aggregate(string $scope, string $label, array $row, array $children, string $unit): void
    {
        foreach (['revenue' => 'Sales', 'cogs' => 'Cost of sales', 'quantity' => 'Quantity sold', 'purchased_quantity' => 'Quantity purchased', 'stock_loss_quantity' => 'Stock shortage', 'stock_gain_quantity' => 'Stock surplus', 'stock_loss_value' => 'Stock loss value', 'stock_gain_value' => 'Stock gain value'] as $field => $title) {
            if (! array_key_exists($field, $row)) {
                continue;
            }
            $estimated = collect($children[$field] ?? [])->contains(fn ($id) => $this->nodes[$id]['estimated']);
            $explanation = empty($children[$field]) && abs((float) $row[$field]) < 0.005
                ? 'No contributing records within these report filters.'
                : 'Sum of the contributing records within the report’s saved dates, product and category filters.';
            $this->roots[$scope.':'.$field] = $this->node($title.' · '.$label, (float) $row[$field], str_contains($field, 'quantity') ? $unit : 'money', $explanation, $children[$field] ?? [], null, 'Sum of contributing values', $estimated);
        }
        $this->roots[$scope.':gross_profit'] = $this->node('Gross profit · '.$label, (float) $row['gross_profit'], 'money', 'Sales less the cost of goods sold, including direct deliveries and posted stock valuation adjustments.', [$this->roots[$scope.':revenue'], $this->roots[$scope.':cogs']], null, 'Sales − cost of sales', $this->nodes[$this->roots[$scope.':cogs']]['estimated']);
        if (isset($row['book_profit'])) {
            $components = str_starts_with($scope, 'product:') ? ($this->bookComponents[substr($scope, 8)] ?? []) : [];
            $this->roots[$scope.':book_profit'] = $this->node('Profit from the books · '.$label, (float) $row['book_profit'], 'money',
                $components ? 'Uses this product’s separate stock account and the same purchases as its stock statement.' : 'Sum of book profit for products with a separate stock account. Products without book profit are excluded from this figure.',
                $components ? [$this->roots[$scope.':revenue'], ...$components] : ($children['book_profit'] ?? []), null,
                $components ? 'Sales + closing stock − opening stock − purchases' : 'Sum of available book profits');
        }
        $basis = $this->roots[$scope.':book_profit'] ?? $this->roots[$scope.':gross_profit'];
        if ($scope === 'total') {
            $basis = $this->node('Profit used for margins', (float) $row['profit'], 'money', 'Each product contributes its book profit when available; otherwise its gross profit.', $children['profit'] ?? [], null, 'Sum of each product’s applicable profit');
        }
        $this->roots[$scope.':profit'] = $basis;
        foreach (['margin_per_unit' => ['Profit per unit', 'money/'.$unit, 'quantity', 'Applicable profit ÷ quantity sold'], 'gross_margin_percent' => ['Profit margin', '%', 'revenue', 'Applicable profit ÷ sales × 100']] as $field => [$title, $displayUnit, $denominator, $formula]) {
            $this->roots[$scope.':'.$field] = $this->node($title.' · '.$label, (float) $row[$field], $displayUnit, $row[$denominator] > 0 ? 'Uses the same profit basis as this report: book profit when available, gross profit otherwise.' : 'Shown as zero because the denominator is zero; no division is performed.', [$basis, $this->roots[$scope.':'.$denominator]], null, $formula, $this->nodes[$basis]['estimated']);
        }
        foreach (['avg_cost' => ['Average cost per unit', 'cogs'], 'avg_rate' => ['Average sales price', 'revenue']] as $field => [$title, $numerator]) {
            if (isset($row[$field])) {
                $this->roots[$scope.':'.$field] = $this->node($title.' · '.$label, (float) $row[$field], 'money/'.$unit, 'Calculated from the report totals, rather than today’s item price. Zero when nothing was sold.', [$this->roots[$scope.':'.$numerator], $this->roots[$scope.':quantity']], null, $title.' = '.$numerator.' ÷ quantity sold', $this->nodes[$this->roots[$scope.':'.$numerator]]['estimated']);
            }
        }
        if (isset($this->roots[$scope.':stock_loss_value'], $this->roots[$scope.':stock_gain_value'])) {
            $loss = $this->roots[$scope.':stock_loss_value'];
            $gain = $this->roots[$scope.':stock_gain_value'];
            $this->roots[$scope.':stock_variance_value'] = $this->node('Net stock variance value · '.$label, (float) $row['stock_gain_value'] - (float) $row['stock_loss_value'], 'money', 'Gains less losses within these report filters.', [$gain, $loss], null, 'Stock gain value − stock loss value', $this->nodes[$gain]['estimated'] || $this->nodes[$loss]['estimated']);
        }
    }
}
