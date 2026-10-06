<?php

namespace App\Modules\FuelStation\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The station's profit & loss statement, built only from the ledger.
 *
 * Every income and cost line on a posted journal (a reversed entry together with its reversal,
 * so the pair nets to nothing) lands in exactly ONE of these buckets, which is why Net profit
 * always equals the ledger's own profit for the range:
 *
 *  - Sales          income on the items' sales accounts
 *  - Cost of sales  the items' cost accounts: what the stock sold cost (month-end write-down included)
 *  - Dip loss/gain  the tank loss and gain accounts: stock the dip found missing (a cost) or extra
 *                   (a gain), on its own line so Sales - Cost of sales = Gross profit reads as the
 *                   trading margin a newcomer can check with a calculator
 *  - Expenses       every expense account (running costs), however the entry was made --
 *                   Money out, a bill or a close -- so one account is always on one line
 *  - Salaries       expense accounts payroll posts to
 *  - Other income   every other income account (rent, interest ...)
 *  - Other costs    every other cost account that is not a running expense (transit loss ...)
 *
 * Profit by day reads the same classification through periodBooks(), so both screens agree.
 */
class ProfitStatementService
{
    private const PL_TYPES = ['revenue', 'other_income', 'expense', 'cogs', 'other_expense'];

    private const INCOME_TYPES = ['revenue', 'other_income'];

    /**
     * @return array{
     *   from:string, to:string,
     *   lines: array<int,array{key:string,label:string,amount:float,details:array<int,array<string,mixed>>}>,
     *   net_profit: float, ledger_total: float,
     *   not_in_profit: array{stock_bought:float,equipment_bought:float}
     * }
     */
    public function run(string $companyId, string $from, string $to, string $slug = '', ?array $stock = null): array
    {
        $ctx = $this->context($companyId);
        $rows = $this->ledgerLines($companyId, $from, $to, false);
        $b = $this->buckets($ctx, $rows, fn () => 'all')['all'] ?? [];

        $stock ??= $this->stockRuns($companyId, $from, $to);
        $purchases = $this->purchaseAmounts($companyId, $from, $to, $stock);
        $dipLitres = [];
        foreach ($stock as $p) {
            $dipLitres[$p['id']] = (float) ($p['totals']['variance'] ?? 0);
        }
        $groups = $this->productGroups($ctx, $b, $purchases, $companyId, $from, $to, $slug);

        $sum = fn (array $list) => round(array_sum(array_column($list, 'amount')), 2);

        // Sales: each product's (or shared account's) income, then discounts given as a reduction.
        $sales = $this->accountDetails($b['sales'] ?? [], $ctx['sales_items'], $slug, $from, $to);
        $discounts = $this->discountRow($b['discounts'] ?? [], $slug);
        if ($discounts) {
            $sales[] = $discounts;
        }

        // Cost of sales: what the stock sold cost, per product (its cost account), every other cost
        // account on its own row. The dip's difference is NOT in it -- it is the next line.
        $formulaCostAccounts = [];
        $cost = [];
        $dip = [];
        foreach ($groups as $g) {
            if ($g['working']) {
                $formulaCostAccounts += $g['cost_accounts'];
                $cost[] = ['account_id' => null, 'item_id' => $g['item']->id, 'code' => null, 'name' => $g['name'], 'amount' => $g['ledger_cost'], 'working' => null, 'href' => $g['href']];
                // The station's formula (opening + bought - closing) less what the litres sold cost:
                // what the dip found missing (positive) or extra (negative) for this fuel.
                $diff = round($g['cost'] - $g['ledger_cost'], 2);
                if (abs($diff) >= 0.5) {
                    $litres = (float) ($dipLitres[$g['item']->id] ?? 0);
                    $dip[] = ['account_id' => null, 'item_id' => $g['item']->id, 'code' => null,
                        'name' => $g['name'].$this->litresLabel($litres),
                        'litres' => $litres, 'amount' => $diff, 'working' => $g['working'], 'href' => $g['href']];
                }
            }
        }
        $otherCost = array_diff_key($b['cost'] ?? [], $formulaCostAccounts);
        $cost = array_merge($cost, $this->accountDetails($otherCost, $ctx['cost_items'], $slug, $from, $to));
        $tankNet = round(array_sum(array_column($b['tank'] ?? [], 'amount')), 2);
        $costTotal = round(array_sum(array_column($b['cost'] ?? [], 'amount')), 2);
        $residual = round($costTotal - $sum($cost), 2);
        if (abs($residual) >= 1) {
            $cost[] = ['name' => 'Other cost of sales'] + $this->residualRow($residual);
        }
        usort($cost, fn ($x, $y) => abs($y['amount']) <=> abs($x['amount']));
        $dipResidual = round($tankNet - $sum($dip), 2);
        if (abs($dipResidual) >= 1) {
            $dip[] = $this->residualRow($dipResidual);
        }

        $expenses = $this->accountDetails($b['expenses'] ?? [], [], $slug, $from, $to);
        $salaries = $this->accountDetails($b['salaries'] ?? [], [], $slug, $from, $to);
        $otherIncome = $this->accountDetails($b['other_income'] ?? [], [], $slug, $from, $to);
        $otherCosts = $this->accountDetails($b['other_costs'] ?? [], [], $slug, $from, $to);

        $salesTotal = round(array_sum(array_column($b['sales'] ?? [], 'amount')) + array_sum(array_column($b['discounts'] ?? [], 'amount')), 2);
        $gross = round($salesTotal - $costTotal, 2);
        $expensesTotal = round(array_sum(array_column($b['expenses'] ?? [], 'amount')), 2);
        $salariesTotal = round(array_sum(array_column($b['salaries'] ?? [], 'amount')), 2);
        $otherIncomeTotal = round(array_sum(array_column($b['other_income'] ?? [], 'amount')), 2);
        $otherCostsTotal = round(array_sum(array_column($b['other_costs'] ?? [], 'amount')), 2);
        $net = round($gross - $tankNet - $expensesTotal - $salariesTotal + $otherIncomeTotal - $otherCostsTotal, 2);

        // The ledger's own profit for the range, worked out separately: the statement must match it.
        $ledgerTotal = round((float) $rows->sum('net'), 2);
        if (abs($net - $ledgerTotal) > 0.005) {
            Log::warning('Profit statement does not match the ledger', ['company_id' => $companyId, 'from' => $from, 'to' => $to, 'statement' => $net, 'ledger' => $ledgerTotal]);
        }

        // Gross profit per product (its sales less its cost), discounts, and what is left over.
        $grossDetails = [];
        foreach ($groups as $g) {
            $grossDetails[] = ['account_id' => null, 'item_id' => $g['item']?->id, 'code' => null, 'name' => $g['name'], 'amount' => round($g['sales'] - $g['ledger_cost'], 2),
                'sales' => $g['sales'], 'cost' => $g['ledger_cost'], 'working' => null, 'href' => $g['href']];
        }
        usort($grossDetails, fn ($x, $y) => abs($y['sales']) <=> abs($x['sales']));
        if ($discounts) {
            $grossDetails[] = $discounts + ['sales' => null, 'cost' => null, 'working' => null];
        }
        $grossResidual = round($gross - $sum($grossDetails), 2);
        if (abs($grossResidual) >= 1) {
            $grossDetails[] = ['name' => 'Other cost of sales'] + $this->residualRow($grossResidual) + ['sales' => null, 'cost' => null];
        }

        $lines = [
            ['key' => 'sales', 'label' => 'Sales', 'amount' => $salesTotal, 'details' => $sales],
            ['key' => 'cost_of_sales', 'label' => 'Cost of sales', 'amount' => $costTotal, 'details' => $cost],
            ['key' => 'gross_profit', 'label' => 'Gross profit', 'amount' => $gross, 'details' => $grossDetails],
            ['key' => 'dip', 'label' => $tankNet > 0.005 ? 'Dip loss' : ($tankNet < -0.005 ? 'Dip gain' : 'Dip loss / gain'), 'amount' => $tankNet, 'details' => $dip],
            ['key' => 'expenses', 'label' => 'Expenses', 'amount' => $expensesTotal, 'details' => $expenses],
            ['key' => 'salaries', 'label' => 'Salaries & wages', 'amount' => $salariesTotal, 'details' => $salaries],
            ['key' => 'other_income', 'label' => 'Other income', 'amount' => $otherIncomeTotal, 'details' => $otherIncome],
            ['key' => 'other_costs', 'label' => 'Other costs', 'amount' => $otherCostsTotal, 'details' => $otherCosts],
            ['key' => 'net_profit', 'label' => 'Net profit', 'amount' => $net, 'details' => []],
        ];

        return [
            'from' => $from,
            'to' => $to,
            'lines' => $lines,
            'net_profit' => $net,
            'ledger_total' => $ledgerTotal,
            'not_in_profit' => [
                'stock_bought' => round(array_sum($purchases), 2),
                'equipment_bought' => $this->equipmentBought($companyId, $from, $to),
            ],
        ];
    }

    /**
     * The same classification per period, for Profit by day. Lists carry account_id, code, name,
     * type and amount: sales, cost and dip as positive figures (a dip gain is negative); 'other'
     * folds salaries, other income and other costs together, signed as income (a cost is negative).
     *
     * @param  callable(Carbon):string  $periodKey
     * @return array<string,array{sales:array,cost:array,dip:array,expenses:array,other:array}>
     */
    public function periodBooks(string $companyId, string $from, string $to, callable $periodKey): array
    {
        $ctx = $this->context($companyId);
        $rows = $this->ledgerLines($companyId, $from, $to, true);
        $buckets = $this->buckets($ctx, $rows, fn ($row) => $periodKey(Carbon::parse($row->d)));

        $out = [];
        foreach ($buckets as $key => $b) {
            $other = [];
            foreach ([['other_income', 1], ['salaries', -1], ['other_costs', -1]] as [$bucket, $sign]) {
                foreach ($b[$bucket] ?? [] as $id => $entry) {
                    $other[$id] ??= array_merge($entry, ['amount' => 0.0]);
                    $other[$id]['amount'] += $sign * $entry['amount'];
                }
            }
            $sort = function (array $list) {
                $list = array_values(array_filter($list, fn ($a) => abs($a['amount']) >= 0.005));
                usort($list, fn ($a, $b) => abs($b['amount']) <=> abs($a['amount']));

                return array_map(fn ($a) => array_diff_key($a, ['tank_kind' => 1]), $list);
            };
            $out[$key] = [
                'sales' => $sort(($b['sales'] ?? []) + ($b['discounts'] ?? [])),
                'cost' => $sort($b['cost'] ?? []),
                'dip' => $sort($b['tank'] ?? []),
                'expenses' => $sort($b['expenses'] ?? []),
                'other' => $sort($other),
            ];
        }

        return $out;
    }

    // ---- classification ------------------------------------------------------------------------

    /** @return array{sales:array<string,bool>,cost:array<string,bool>,tank:array<string,bool>,sales_items:array,cost_items:array,items:Collection} */
    private function context(string $companyId): array
    {
        $items = DB::table('inv.items')->where('company_id', $companyId)->whereNull('deleted_at')
            ->get(['id', 'name', 'income_account_id', 'expense_account_id', 'asset_account_id']);

        $salesItems = [];
        $costItems = [];
        foreach ($items as $item) {
            if ($item->income_account_id) {
                $salesItems[$item->income_account_id][] = $item;
            }
            if ($item->expense_account_id) {
                $costItems[$item->expense_account_id][] = $item;
            }
        }

        return [
            'sales' => array_fill_keys(array_keys($salesItems), true),
            'cost' => array_fill_keys(array_keys($costItems), true),
            'tank' => array_fill_keys(app(DailyCloseService::class)->tankVarianceAccountIds($companyId), true),
            'sales_items' => $salesItems,
            'cost_items' => $costItems,
            'items' => $items,
            // Expense accounts payroll posts to: Salaries & wages, wherever else they are used.
            'payroll' => array_fill_keys(DB::table('acct.journal_entries as je')
                ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
                ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
                ->where('t.company_id', $companyId)->where('a.type', 'expense')
                ->where('t.transaction_type', 'like', 'payroll%')
                ->distinct()->pluck('je.account_id')->all(), true),
        ];
    }

    private function classify(array $ctx, object $line): string
    {
        if (isset($ctx['tank'][$line->account_id])) {
            return 'tank';
        }
        if (isset($ctx['sales'][$line->account_id])) {
            return 'sales';
        }
        if (isset($ctx['cost'][$line->account_id])) {
            return 'cost';
        }
        // By account, not by how the entry was made: POS charges from a bill and from Money out
        // are the same cost and belong on the same line.
        if ($line->type === 'expense') {
            return isset($ctx['payroll'][$line->account_id]) ? 'salaries' : 'expenses';
        }

        if (in_array($line->type, self::INCOME_TYPES, true)) {
            // A revenue account that normally carries a debit (Sales Discounts) takes sales away.
            $contra = $line->normal_balance === 'debit' || preg_match('/discount|contra/i', (string) $line->subtype);

            return $contra ? 'discounts' : 'other_income';
        }

        return 'other_costs';
    }

    /** Every P&L line on a live posted journal, per account and entry type (and per day when asked). */
    private function ledgerLines(string $companyId, string $from, string $to, bool $byDate): Collection
    {
        $query = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->whereIn('t.status', ['posted', 'locked'])
            ->whereNull('t.deleted_at')
            ->whereBetween('t.transaction_date', [$from, $to])
            ->whereIn('a.type', self::PL_TYPES);

        $group = ['a.id', 'a.code', 'a.name', 'a.type', 'a.normal_balance', 'a.subtype', 't.transaction_type'];
        $select = 'a.id AS account_id, a.code, a.name, a.type, a.normal_balance, a.subtype, t.transaction_type, SUM(je.credit_amount) - SUM(je.debit_amount) AS net';
        if ($byDate) {
            $group[] = DB::raw('t.transaction_date::date');
            $select = 't.transaction_date::date AS d, '.$select;
        }

        return $query->groupBy(...$group)->selectRaw($select)->get();
    }

    /**
     * @return array<string,array<string,array<string,array<string,mixed>>>> period => bucket => account => entry.
     *   Entries read in their own direction: income buckets as income, every cost bucket as a
     *   positive cost (a gain on a cost account is negative).
     */
    private function buckets(array $ctx, Collection $rows, callable $periodOf): array
    {
        $out = [];
        foreach ($rows as $line) {
            $bucket = $this->classify($ctx, $line);
            $key = $periodOf($line);
            $amount = in_array($bucket, ['sales', 'other_income', 'discounts'], true) ? (float) $line->net : -(float) $line->net;
            $entry = &$out[$key][$bucket][$line->account_id];
            $entry ??= [
                'account_id' => $line->account_id, 'code' => $line->code, 'name' => $line->name, 'type' => $line->type, 'amount' => 0.0,
                'tank_kind' => in_array($line->type, self::INCOME_TYPES, true) ? 'gain' : 'loss',
            ];
            $entry['amount'] += $amount;
            unset($entry);
        }

        return $out;
    }

    // ---- details --------------------------------------------------------------------------------

    /**
     * One line per account, or per product when the account belongs to a single product.
     *
     * @param  array<string,array<string,mixed>>  $entries
     * @param  array<string,array<int,object>>  $itemsByAccount
     * @return array<int,array<string,mixed>>
     */
    private function accountDetails(array $entries, array $itemsByAccount, string $slug, string $from, string $to): array
    {
        $out = [];
        foreach ($entries as $id => $e) {
            if (abs($e['amount']) < 0.005) {
                continue;
            }
            $items = $itemsByAccount[$id] ?? [];
            $single = count($items) === 1 ? $items[0] : null;
            $out[] = [
                'account_id' => $e['account_id'],
                'item_id' => $single?->id,
                'code' => $e['code'],
                'name' => $single ? $single->name : $e['name'],
                'amount' => round($e['amount'], 2),
                'href' => $slug === '' ? null : ($single
                    ? $this->stockLink($slug, $single->id, $from, $to)
                    : $this->accountLink($slug, $e['account_id'], $e['type'], $from, $to)),
            ];
        }
        usort($out, fn ($a, $b) => abs($b['amount']) <=> abs($a['amount']));

        return $out;
    }

    /** Discounts given (contra-revenue accounts) as one negative row under Sales. */
    private function discountRow(array $entries, string $slug): ?array
    {
        $total = round(array_sum(array_column($entries, 'amount')), 2);
        if (abs($total) < 0.005) {
            return null;
        }
        $first = reset($entries);

        return [
            'account_id' => $first['account_id'], 'item_id' => null, 'code' => $first['code'], 'name' => 'Discounts given', 'amount' => $total,
            'href' => $slug === '' ? null : "/{$slug}/accounts/{$first['account_id']}",
        ];
    }

    /** What the product rows leave out: tank losses and gains that belong to no product's stock account. */
    private function residualRow(float $amount): array
    {
        return ['account_id' => null, 'item_id' => null, 'code' => null, 'name' => 'Tank losses and gains not in a product', 'amount' => $amount, 'href' => null, 'working' => null];
    }

    /**
     * Products as the statement shows them: items sharing a sales or cost account are one group
     * (a shared account cannot be split). A single tank fuel whose stock account is its own
     * takes the station's formula for cost -- opening stock + bought - closing stock, which
     * already holds that fuel's tank losses, gains and write-downs -- and carries the working.
     * Every other group keeps its cost accounts' ledger figures.
     *
     * @return array<int,array{item:?object,name:string,sales:float,cost:float,working:?array,href:?string,cost_accounts:array<string,bool>}>
     */
    private function productGroups(array $ctx, array $b, array $purchases, string $companyId, string $from, string $to, string $slug): array
    {
        $parent = [];
        $find = function (string $x) use (&$parent, &$find) {
            $parent[$x] ??= $x;

            return $parent[$x] === $x ? $x : ($parent[$x] = $find($parent[$x]));
        };
        foreach ($ctx['items'] as $item) {
            $nodes = array_values(array_filter([
                $item->income_account_id ? 's:'.$item->income_account_id : null,
                $item->expense_account_id ? 'c:'.$item->expense_account_id : null,
            ]));
            if (! $nodes) {
                continue;
            }
            $root = $find($item->id);
            foreach ($nodes as $n) {
                $parent[$find($n)] = $root;
            }
        }

        $groups = [];
        foreach ($ctx['items'] as $item) {
            if (! $item->income_account_id && ! $item->expense_account_id) {
                continue;
            }
            $g = &$groups[$find($item->id)];
            $g ??= ['items' => [], 'sales' => [], 'cost' => []];
            $g['items'][] = $item;
            if ($item->income_account_id) {
                $g['sales'][$item->income_account_id] = true;
            }
            if ($item->expense_account_id) {
                $g['cost'][$item->expense_account_id] = true;
            }
            unset($g);
        }

        $tankItemIds = DB::table('inv.warehouses')
            ->where('company_id', $companyId)->where('warehouse_type', 'tank')
            ->whereNotNull('linked_item_id')->whereNull('deleted_at')
            ->distinct()->pluck('linked_item_id')->all();
        $valuation = app(MonthEndStockValuationService::class);
        $dayBefore = Carbon::parse($from)->subDay()->toDateString();

        $out = [];
        foreach ($groups as $g) {
            $sales = array_sum(array_map(fn ($id) => $b['sales'][$id]['amount'] ?? 0.0, array_keys($g['sales'])));
            $cost = array_sum(array_map(fn ($id) => $b['cost'][$id]['amount'] ?? 0.0, array_keys($g['cost'])));

            $single = count($g['items']) === 1 ? $g['items'][0] : null;
            $ledgerCost = $cost;
            $working = null;
            if ($single && $single->asset_account_id && in_array($single->id, $tankItemIds, true)) {
                $shared = $ctx['items']->contains(fn ($i) => $i->id !== $single->id && $i->asset_account_id === $single->asset_account_id);
                if (! $shared) {
                    $opening = $valuation->accountBalance($companyId, $single->asset_account_id, $dayBefore);
                    $closing = $valuation->accountBalance($companyId, $single->asset_account_id, $to);
                    $bought = round((float) ($purchases[$single->id] ?? 0), 2);
                    $working = [
                        'opening' => round($opening, 2),
                        'bought' => $bought,
                        'closing' => round($closing, 2),
                        'used' => round($opening + $bought - $closing, 2),
                    ];
                    $cost = $working['used'];
                }
            }

            if (abs($sales) < 0.005 && abs($cost) < 0.005) {
                continue;
            }

            $names = [];
            foreach (array_keys($g['sales'] ?: $g['cost']) as $id) {
                $name = $b['sales'][$id]['name'] ?? $b['cost'][$id]['name'] ?? null;
                if ($name) {
                    $names[] = $name;
                }
            }

            $out[] = [
                'item' => $single,
                'name' => $single ? $single->name : implode(' / ', $names),
                'sales' => round($sales, 2),
                'cost' => round($cost, 2),
                // What the litres sold cost (the cost account); cost minus this is the dip's difference.
                'ledger_cost' => round($ledgerCost, 2),
                'working' => $working,
                'href' => $single && $slug !== '' ? $this->stockLink($slug, $single->id, $from, $to) : null,
                'cost_accounts' => $g['cost'],
            ];
        }

        return $out;
    }

    // ---- outside the statement ------------------------------------------------------------------

    /**
     * What was bought per product (the stock statement's purchase amount): stock becomes a cost
     * only when it is sold, so it is kept out of profit. Reuses the home page's statement runs
     * when they are passed in.
     *
     * @return array<string,float> item id => amount
     */
    private function purchaseAmounts(string $companyId, string $from, string $to, ?array $stock): array
    {
        $stock ??= $this->stockRuns($companyId, $from, $to);

        $out = [];
        foreach ($stock as $p) {
            $out[$p['id']] = (float) ($p['totals']['purchase_amount'] ?? 0);
        }

        return $out;
    }

    /** Each product's stock statement totals for the range (bought, sold, dip variance ...). */
    private function stockRuns(string $companyId, string $from, string $to): array
    {
        $statement = app(StockStatementService::class);
        $stock = [];
        foreach ($statement->run($companyId, '', $from, $to)['products'] as $p) {
            $stock[] = ['id' => $p['id'], 'totals' => $statement->run($companyId, $p['id'], $from, $to)['totals']];
        }

        return $stock;
    }

    /** " -44 L" / " +29 L" after a fuel's name on the dip line; nothing when the litres are unknown. */
    private function litresLabel(float $litres): string
    {
        if (abs($litres) < 0.005) {
            return '';
        }

        return ' '.($litres > 0 ? '+' : '-').rtrim(rtrim(number_format(abs($litres), 2, '.', ','), '0'), '.').' L';
    }

    /**
     * Fixed assets entered under Money out > Expenses (the expense report marks them as assets):
     * asset accounts other than cash and bank, net of any reversal.
     */
    private function equipmentBought(string $companyId, string $from, string $to): float
    {
        return round((float) DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->whereIn('t.status', ['posted', 'locked'])
            ->whereNull('t.deleted_at')
            ->whereBetween('t.transaction_date', [$from, $to])
            ->where('t.transaction_type', 'expense')
            ->where('a.type', 'asset')
            ->whereNotIn('a.subtype', ['cash', 'bank'])
            ->selectRaw('COALESCE(SUM(je.debit_amount) - SUM(je.credit_amount), 0) AS net')
            ->value('net'), 2);
    }

    // ---- links ----------------------------------------------------------------------------------

    private function stockLink(string $slug, string $itemId, string $from, string $to): string
    {
        return "/{$slug}/fuel/reports/stock-statement?item={$itemId}&start_date={$from}&end_date={$to}";
    }

    private function accountLink(string $slug, string $accountId, string $type, string $from, string $to): string
    {
        return $type === 'expense'
            ? "/{$slug}/reports/statements?kind=expense&id={$accountId}&from={$from}&to={$to}"
            : "/{$slug}/accounts/{$accountId}";
    }
}
