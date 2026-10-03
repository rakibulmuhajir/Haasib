<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Modules\FuelStation\Services\LubricantCostService;
use App\Services\CompanyContextService;
use Illuminate\Console\Command;

/**
 * Books the cost of a month's other sales (lubricants, shop items) that closes posted without
 * one: one correction transaction for the month plus stock movements. Safe to run again: the
 * previous live correction for the month is reversed and replaced. See LubricantCostService.
 */
class FuelLubricantCost extends Command
{
    protected $signature = 'fuel:lubricant-cost {month : Month, YYYY-MM} {--company= : Company id or slug} {--prices= : JSON object of item name, sku or id to unit purchase price} {--dry-run : Show what would be posted without writing}';

    protected $description = 'Post the cost of a month\'s lubricant and other sales that were booked without one';

    public function handle(CompanyContextService $context, LubricantCostService $service): int
    {
        $month = (string) $this->argument('month');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error('Month must look like 2026-09.');

            return self::FAILURE;
        }

        $prices = [];
        if ($this->option('prices') !== null && $this->option('prices') !== '') {
            $prices = json_decode((string) $this->option('prices'), true);
            if (! is_array($prices)) {
                $this->error('--prices must be a JSON object like {"Mobil 4L": 2400}.');

                return self::FAILURE;
            }
        }

        $ref = $this->option('company');
        if (! $ref) {
            $this->error('Name the company with --company (id or slug).');

            return self::FAILURE;
        }
        $company = $context->crossCompany(fn () => Company::where(fn ($q) => $q->where('slug', $ref)
            ->when(preg_match('/^[0-9a-f-]{36}$/i', $ref), fn ($q) => $q->orWhere('id', $ref)))->first());
        if (! $company) {
            $this->error('No matching company.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $this->line("{$company->name} ({$company->slug}), {$month}".($dry ? ' (dry run)' : ''));

        try {
            $result = $context->withContext($company, fn () => $service->correct($company->id, $month, $prices, ! $dry));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['missing']) {
            $this->error('No purchase price for: '.implode(', ', $result['missing']).'. Add them to --prices.');

            return self::FAILURE;
        }
        if (! $result['lines']) {
            $this->line('Nothing sold without a cost.'.($result['reversed'] ? " Reversed {$result['reversed']}." : ''));

            return self::SUCCESS;
        }

        $totals = ['cost' => 0.0, 'amount' => 0.0];
        foreach ($result['lines'] as $line) {
            $totals['cost'] += $line['cost'];
            $totals['amount'] += $line['amount'];
            $this->line(sprintf('  %s  qty %s  unit cost %s  cost %s  sales %s  profit %s', $line['name'],
                rtrim(rtrim(number_format($line['quantity'], 3, '.', ''), '0'), '.'), number_format($line['unit_cost'], 2, '.', ''),
                number_format($line['cost'], 2), number_format($line['amount'], 2), number_format($line['profit'], 2)));
        }
        $this->line(sprintf('  Total  cost %s  sales %s  profit %s', number_format($totals['cost'], 2), number_format($totals['amount'], 2), number_format($totals['amount'] - $totals['cost'], 2)));

        if ($dry) {
            $this->warn('Dry run. Nothing was posted.');
        } else {
            $this->info(($result['reversed'] ? "Reversed {$result['reversed']}. " : '')."Posted {$result['posted']}.");
        }

        return self::SUCCESS;
    }
}
