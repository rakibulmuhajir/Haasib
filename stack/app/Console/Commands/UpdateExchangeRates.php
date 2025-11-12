<?php

namespace App\Console\Commands;

use App\Services\CurrencyExchangeRateService;
use Illuminate\Console\Command;

class UpdateExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange:update-rates {--base=USD : Base currency for exchange rates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exchange rates from real-time providers';

    /**
     * Execute the console command.
     */
    public function handle(CurrencyExchangeRateService $exchangeRateService): int
    {
        $this->info('🔄 Updating exchange rates...');
        $this->line('Base currency: '.$this->option('base'));

        $results = $exchangeRateService->updateExchangeRates($this->option('base'));

        $this->newLine();
        $this->info('📊 Update Results:');
        $this->line("✅ Successfully updated: {$results['updated']} rates");

        if ($results['failed'] > 0) {
            $this->line("❌ Failed to update: {$results['failed']} rates");

            foreach ($results['errors'] as $error) {
                $this->line("   - {$error}");
            }
        }

        if ($results['updated'] > 0) {
            $this->newLine();
            $this->info('💡 Tip: Schedule this command to run daily with:');
            $this->line('   $ php artisan schedule:list');
            $this->line('   # Add to app/Console/Kernel.php:');
            $this->line('   $schedule->command(\'exchange:update-rates\')->dailyAt(\'01:00\');');
        }

        return $results['failed'] > 0 ? 1 : 0;
    }
}
