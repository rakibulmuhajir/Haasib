<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Services\AccountingWriteTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Re-costs a posted daily close at its own day's fuel cost (FuelCostService) without re-posting
 * it: one companion journal per close moves the difference between cost of sales, tank
 * gain/loss and fuel stock, and the close's figures (metadata) are brought in line so the
 * station reports agree with the ledger. Edit day removes the correction together with the
 * close (DailyCloseReopenService), and the re-post then carries the right cost by itself.
 *
 * Written for closes posted while cost came from a single frozen avg_cost figure.
 */
class DailyCloseCostCorrectionService
{
    public const TYPE = 'fuel_daily_close_cost_correction';

    public function __construct(private readonly FuelCostService $costs) {}

    /**
     * @return array{close:string,date:string,lines:array<int,array<string,mixed>>,profit_effect:float,posted:?string}
     */
    public function correct(Transaction $close, bool $apply): array
    {
        $companyId = $close->company_id;
        $date = $close->transaction_date->toDateString();
        $metadata = $close->metadata ?? [];
        $items = DB::table('inv.items')->where('company_id', $companyId)->whereNotNull('fuel_category')->whereNull('deleted_at')
            ->get(['id', 'name', 'fuel_category', 'expense_account_id', 'asset_account_id']);
        $closeLines = DB::table('acct.journal_entries')->where('transaction_id', $close->id)->get(['account_id', 'description', 'debit_amount', 'credit_amount']);
        $accountFor = fn (string $description) => $closeLines->first(fn ($l) => str_starts_with((string) $l->description, $description))?->account_id;

        $summary = ['close' => $close->transaction_number, 'date' => $date, 'lines' => [], 'profit_effect' => 0.0, 'posted' => null];
        $entries = [];
        $post = function (?string $debit, ?string $credit, float $amount, string $text) use (&$entries) {
            if (! $debit || ! $credit || abs($amount) < 0.01) {
                return;
            }
            [$debit, $credit, $amount] = $amount > 0 ? [$debit, $credit, $amount] : [$credit, $debit, -$amount];
            $entries[] = ['account_id' => $debit, 'type' => 'debit', 'amount' => round($amount, 2), 'description' => $text];
            $entries[] = ['account_id' => $credit, 'type' => 'credit', 'amount' => round($amount, 2), 'description' => $text];
        };

        foreach ($items as $item) {
            $sale = $metadata['fuel_sales'][$item->fuel_category] ?? null;
            $variances = collect($metadata['tank_variances'] ?? [])->where('item_name', $item->name);
            if ((! $sale || (float) $sale['liters'] <= 0) && $variances->isEmpty()) {
                continue;
            }
            $cost = $this->costs->costForDay($companyId, $item->id, $date);
            if ($cost <= 0) {
                continue; // nothing to cost it at (no opening or delivery cost on record)
            }
            $inventory = $accountFor("Inventory reduction - {$item->name}") ?? $item->asset_account_id;

            $row = ['item' => $item->name, 'cost' => $cost, 'cogs_delta' => 0.0, 'loss_delta' => 0.0, 'gain_delta' => 0.0];
            if ($sale && (float) $sale['liters'] > 0) {
                $liters = (float) $sale['liters'];
                $row['used'] = round((float) $sale['cogs'] / $liters, 4);
                $right = round($liters * $cost, 2);
                $row['cogs_delta'] = round($right - (float) $sale['cogs'], 2);
                $post($accountFor("Cost of goods sold - {$item->name}") ?? $item->expense_account_id, $inventory, $row['cogs_delta'],
                    "Cost correction - {$item->name} {$liters} L at {$cost}");
                $metadata['fuel_sales'][$item->fuel_category]['cogs'] = $right;
            }

            foreach ($metadata['tank_variances'] ?? [] as $i => $variance) {
                if (($variance['item_name'] ?? null) !== $item->name) {
                    continue;
                }
                $right = round((float) $variance['liters'] * $cost, 2);
                $delta = round($right - (float) $variance['amount'], 2);
                if ($variance['type'] === 'loss') {
                    $row['loss_delta'] += $delta;
                    $post($accountFor('Fuel shrinkage loss'), $inventory, $delta, "Cost correction - {$item->name} tank loss at {$cost}");
                } else {
                    $row['gain_delta'] += $delta;
                    $post($inventory, $accountFor('Fuel variance gain'), $delta, "Cost correction - {$item->name} tank gain at {$cost}");
                }
                $metadata['tank_variances'][$i]['amount'] = $right;
            }

            $summary['profit_effect'] += -$row['cogs_delta'] - $row['loss_delta'] + $row['gain_delta'];
            $summary['lines'][] = $row;
        }
        $summary['profit_effect'] = round($summary['profit_effect'], 2);

        if (! $apply || empty($entries)) {
            return $summary;
        }

        $metadata['total_cogs'] = round(collect($metadata['fuel_sales'] ?? [])->sum('cogs'), 2);
        $metadata['total_shrinkage'] = round(collect($metadata['tank_variances'] ?? [])->where('type', 'loss')->sum('amount'), 2);
        $metadata['total_gain'] = round(collect($metadata['tank_variances'] ?? [])->where('type', 'gain')->sum('amount'), 2);

        $posted = null;
        AccountingWriteTransaction::run(function () use ($close, $date, $entries, $metadata, $summary, &$posted) {
            $transaction = app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $close->company_id,
                'transaction_type' => self::TYPE,
                'date' => $date,
                'currency' => $close->currency ?: 'PKR',
                'description' => "Fuel cost correction - {$close->transaction_number}",
                'reference_type' => 'fuel.daily_close_cost_correction',
                'reference_id' => $close->id,
                'metadata' => ['close_id' => $close->id, 'lines' => $summary['lines']],
            ], $entries);
            $metadata['cost_corrections'][] = ['transaction_id' => $transaction->id, 'at' => now()->toISOString(), 'profit_effect' => $summary['profit_effect']];
            DB::table('acct.transactions')->where('id', $close->id)->update(['metadata' => json_encode($metadata)]);
            $posted = $transaction->transaction_number;
        });
        $summary['posted'] = $posted;

        return $summary;
    }
}
