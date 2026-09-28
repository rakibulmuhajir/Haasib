<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseCostCorrectionService;
use App\Modules\FuelStation\Services\FuelCostService;
use App\Services\CompanyContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-costs posted fuel daily closes at each day's own weighted-average fuel cost. Dry run
 * unless --apply. Safe to run again: a close already at the right cost gets no journal.
 */
class RecostFuelCloses extends Command
{
    protected $signature = 'fuel:recost-closes {company : Company slug} {--from= : First business date (Y-m-d)} {--apply : Post the corrections}';

    protected $description = 'Re-cost posted fuel daily closes at their own day\'s fuel cost';

    public function handle(CompanyContextService $context, DailyCloseCostCorrectionService $corrections, FuelCostService $costs): int
    {
        $company = $context->crossCompany(fn () => Company::where('slug', $this->argument('company'))->first());
        if (! $company) {
            $this->error('No company with that slug.');

            return self::FAILURE;
        }
        $context->setContext($company);
        $apply = (bool) $this->option('apply');

        $closes = Transaction::where('company_id', $company->id)
            ->where('transaction_type', 'fuel_daily_close')
            ->where('status', 'posted')
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id')
            ->when($this->option('from'), fn ($q, $from) => $q->whereDate('transaction_date', '>=', $from))
            ->orderBy('transaction_date')
            ->get();

        $total = 0.0;
        foreach ($closes as $close) {
            $result = $corrections->correct($close, $apply);
            // Corrections posted before closes carried them in their own figures.
            $folded = $apply ? $corrections->fold($close->fresh()) : 0;
            $total += $result['profit_effect'];
            $parts = collect($result['lines'])->map(fn ($l) => sprintf('%s %s->%s', $l['item'], $l['used'] ?? '-', $l['cost']))->implode(' | ');
            $this->line(sprintf('%s %s  profit %+.0f  %s%s', $result['date'], $result['close'], $result['profit_effect'], $parts,
                ($result['posted'] ? "  posted {$result['posted']}" : '').($folded ? "  summary updated ({$folded})" : '')));
        }
        $this->info(sprintf('Total profit effect: %+.0f', $total));

        // Keep the item's stored cost at today's figure: stock valuation and the rate screens read it.
        $items = DB::table('inv.items')->where('company_id', $company->id)->whereNotNull('fuel_category')->whereNull('deleted_at')->get(['id', 'name', 'avg_cost']);
        foreach ($items as $item) {
            $now = $costs->costForDay($company->id, $item->id, now()->toDateString());
            if ($now <= 0) {
                continue;
            }
            $this->line(sprintf('%s cost now %.4f (stored %.4f)', $item->name, $now, $item->avg_cost));
            if ($apply) {
                DB::table('inv.items')->where('id', $item->id)->update(['avg_cost' => $now, 'cost_price' => $now]);
            }
        }

        if (! $apply) {
            $this->warn('Dry run. Nothing was posted. Add --apply to post.');
        }

        return self::SUCCESS;
    }
}
