<?php

namespace App\Console\Commands\AccountsReceivable;

use App\Models\AccountsReceivable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateAging extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ar:update-aging';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the days_overdue and aging_category for all open accounts receivable records.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting A/R aging update process...');
        Log::info('Scheduled A/R aging update process started.');

        $updatedCount = 0;
        $startTime = microtime(true);

        // Process in chunks to avoid memory issues with large datasets.
        AccountsReceivable::query()
            ->where('amount_due', '>', 0)
            ->chunkById(200, function ($records) use (&$updatedCount) {
                foreach ($records as $record) {
                    // The calculateAging() method is on the model and will set the
                    // days_overdue and aging_category properties.
                    $record->calculateAging();

                    // We only save if the aging data has actually changed.
                    if ($record->isDirty(['days_overdue', 'aging_category'])) {
                        $record->save();
                        $updatedCount++;
                    }
                }
                $this->output->write('.'); // Progress indicator
            });

        $duration = round(microtime(true) - $startTime, 2);
        $this->info("\nFinished A/R aging update. Updated {$updatedCount} records in {$duration} seconds.");
        Log::info("Scheduled A/R aging update process finished. Updated {$updatedCount} records in {$duration} seconds.");

        return self::SUCCESS;
    }
}
