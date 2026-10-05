<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Partner;
use App\Services\CompanyContextService;
use App\Services\PartnerProfitShareService;
use Illuminate\Console\Command;

class SharePartnerProfit extends Command
{
    protected $signature = 'partners:share-profit
        {month : The month to share, YYYY-MM}
        {--company= : One company (id) only}
        {--dry-run : Show each partner\'s share without posting}';

    protected $description = 'Share a month\'s net profit between the active partners (reversed and re-posted if run again)';

    public function handle(CompanyContextService $context, PartnerProfitShareService $service): int
    {
        $month = (string) $this->argument('month');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error('Month must look like 2026-09.');

            return self::FAILURE;
        }

        $companies = $context->crossCompany(function () {
            $ids = Partner::query()->where('is_active', true)->distinct()->pluck('company_id');
            $query = Company::query()->whereIn('id', $ids);
            if ($this->option('company')) {
                $query->where('id', $this->option('company'));
            }

            return $query->get();
        });
        if ($companies->isEmpty()) {
            $this->warn('No company with active partners.');

            return self::SUCCESS;
        }

        $failed = false;
        foreach ($companies as $company) {
            $context->withContext($company, function () use ($company, $service, $month, &$failed) {
                $this->info("{$company->name} - {$month}");
                try {
                    $result = $this->option('dry-run') ? $service->preview($company->id, $month) : $service->share($company->id, $month);
                } catch (\Throwable $e) {
                    $failed = true;
                    $this->error('  '.($e instanceof \Illuminate\Validation\ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage()));

                    return;
                }
                $this->line('  Net profit '.number_format($result['net_profit'], 2));
                foreach ($result['partners'] as $row) {
                    $this->line(sprintf('  %-24s %6.2f%%  %s', $row['name'], $row['percent'], number_format($row['amount'], 2)));
                }
                if (abs($result['unallocated']) >= 0.005) {
                    $this->line('  Unallocated '.number_format($result['unallocated'], 2));
                }
                $this->line($this->option('dry-run') ? '  (dry run, nothing posted)' : ($result['unchanged'] ? '  Already shared, unchanged.' : '  Posted.'));
            });
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
