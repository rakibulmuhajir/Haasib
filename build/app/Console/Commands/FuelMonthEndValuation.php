<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Modules\FuelStation\Services\MonthEndStockValuationService;
use App\Services\CompanyContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Settles a month's tank fuel at the lower of cost or the purchase rate in force on the 1st of
 * the next month (MonthEndStockValuationService). Safe to run again: nothing changes unless a
 * figure did.
 */
class FuelMonthEndValuation extends Command
{
    protected $signature = 'fuel:month-end-valuation {month : Month, YYYY-MM} {--company= : Company id or slug (every fuel company when omitted)}';

    protected $description = 'Value a month\'s tank fuel at the lower of cost or the new purchase rate';

    public function handle(CompanyContextService $context, MonthEndStockValuationService $valuation): int
    {
        $month = (string) $this->argument('month');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error('Month must look like 2026-09.');

            return self::FAILURE;
        }

        $companies = $context->crossCompany(function () {
            $ref = $this->option('company');
            if ($ref) {
                return Company::where(fn ($q) => $q->where('slug', $ref)->when(preg_match('/^[0-9a-f-]{36}$/i', $ref), fn ($q) => $q->orWhere('id', $ref)))->get();
            }
            $ids = DB::table('inv.warehouses')->where('warehouse_type', 'tank')->whereNull('deleted_at')->distinct()->pluck('company_id');

            return Company::whereIn('id', $ids)->get();
        });

        if ($companies->isEmpty()) {
            $this->error('No matching fuel company.');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($companies as $company) {
            $this->line("{$company->name} ({$company->slug}), {$month}");
            try {
                $context->withContext($company, function () use ($company, $month, $valuation) {
                    foreach ($valuation->sync($company->id, $month) as $r) {
                        $this->line(sprintf('  %s  Q %s L  B %s  C %s  R %s  W %s  -> %s', $r['item'], number_format($r['quantity'], 3), number_format($r['book'], 2), number_format($r['cost'], 4),
                            $r['rate'] === null ? '-' : number_format($r['rate'], 2), number_format($r['writedown'], 2), $r['action']));
                    }
                });
            } catch (\Throwable $e) {
                $failed = true;
                $this->error('  '.$e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
