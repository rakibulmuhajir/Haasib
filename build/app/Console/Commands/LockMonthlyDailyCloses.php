<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Modules\Accounting\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class LockMonthlyDailyCloses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fuel:lock-month-closes
        {--month= : The month to lock (1-12). Defaults to previous month}
        {--year= : The year. Defaults to current year or previous year if locking December in January}
        {--company= : Lock for a specific company ID only}
        {--dry-run : Show what would be locked without actually locking}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lock all daily close entries for a given month to prevent amendments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();

        // Determine month and year
        $month = $this->option('month') ? (int) $this->option('month') : $now->subMonth()->month;
        $year = $this->option('year') ? (int) $this->option('year') : null;

        if ($year === null) {
            // If we're in January and locking "previous month", that means December of last year
            if ($now->month === 1 && !$this->option('month')) {
                $year = $now->year - 1;
            } else {
                $year = $now->year;
            }
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $monthName = $startDate->format('F Y');

        $isDryRun = $this->option('dry-run');
        $companyId = $this->option('company');

        $this->info("Processing daily closes for {$monthName}");
        if ($isDryRun) {
            $this->warn('DRY RUN - No changes will be made');
        }
        $this->newLine();

        // Get companies to process
        $companiesQuery = Company::query();
        if ($companyId) {
            $companiesQuery->where('id', $companyId);
        }

        // Only process companies with fuel_station industry
        $companiesQuery->where('industry', 'fuel_station');

        $companies = $companiesQuery->get();

        if ($companies->isEmpty()) {
            $this->warn('No fuel station companies found to process.');
            return 0;
        }

        $totalLocked = 0;
        $this->withProgressBar($companies, function ($company) use ($startDate, $endDate, $isDryRun, &$totalLocked) {
            $query = Transaction::where('company_id', $company->id)
                ->where('transaction_type', 'fuel_daily_close')
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->where('is_locked', false)
                ->whereNull('reversed_by_id')
                ->whereNull('deleted_at');

            $count = $query->count();

            if ($count > 0) {
                if (!$isDryRun) {
                    $query->update([
                        'is_locked' => true,
                        'locked_at' => now(),
                        'lock_reason' => 'month_end',
                    ]);
                }
                $totalLocked += $count;
            }
        });

        $this->newLine(2);

        if ($isDryRun) {
            $this->info("Would lock {$totalLocked} daily close entries for {$monthName}");
        } else {
            $this->info("Locked {$totalLocked} daily close entries for {$monthName}");
        }

        return 0;
    }
}
