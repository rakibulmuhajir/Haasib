<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Services\CurrencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateExistingCompanyCurrencies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'company:migrate-currencies 
                          {--dry-run : Show what would be migrated without making changes}
                          {--force : Force migration even if currencies already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing companies to use the new multi-currency system';

    public function __construct(
        private CurrencyService $currencyService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting company currency migration...');
        
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }
        
        // Get all companies
        $companies = Company::whereNotNull('base_currency')->get();
        
        if ($companies->isEmpty()) {
            $this->info('📭 No companies found with base_currency set.');
            return 0;
        }
        
        $this->info("📊 Found {$companies->count()} companies with base currencies");
        
        $migrated = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($companies as $company) {
            $this->line("🏢 Processing: {$company->name} (Currency: {$company->base_currency})");
            
            try {
                // Check if company already has currency setup
                $existingCurrency = CompanyCurrency::where('company_id', $company->id)
                    ->where('is_base_currency', true)
                    ->first();
                
                if ($existingCurrency && !$force) {
                    $this->line("   ⏭️  Skipped - Base currency already exists: {$existingCurrency->currency_code}");
                    $skipped++;
                    continue;
                }
                
                if ($force && $existingCurrency) {
                    $this->line("   🔄 Force mode - Removing existing currency setup");
                    if (!$dryRun) {
                        CompanyCurrency::where('company_id', $company->id)->delete();
                    }
                }
                
                if (!$dryRun) {
                    // Set up the base currency
                    $currency = $this->currencyService->setupBaseCurrency(
                        $company->id,
                        $company->base_currency
                    );
                    
                    $this->line("   ✅ Successfully set up base currency: {$currency->currency_code}");
                } else {
                    $this->line("   ✅ Would set up base currency: {$company->base_currency}");
                }
                
                $migrated++;
                
            } catch (\Exception $e) {
                $this->error("   ❌ Failed: {$e->getMessage()}");
                $errors++;
            }
        }
        
        $this->newLine();
        $this->info('📈 Migration Summary:');
        $this->info("   ✅ Migrated: {$migrated}");
        $this->info("   ⏭️  Skipped: {$skipped}");
        $this->info("   ❌ Errors: {$errors}");
        
        if ($dryRun && $migrated > 0) {
            $this->newLine();
            $this->info('🔧 To apply these changes, run:');
            $this->info('   php artisan company:migrate-currencies');
        }
        
        return $errors > 0 ? 1 : 0;
    }
}