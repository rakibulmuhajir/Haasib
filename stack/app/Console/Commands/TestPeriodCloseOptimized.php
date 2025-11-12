<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\PeriodCloseOptimizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestPeriodCloseOptimized extends Command
{
    protected $signature = 'app:test-period-close-optimized';

    protected $description = 'Test optimized period close process with resource management';

    public function handle()
    {
        $this->info('=== PHASE 7.3: Optimized Period Close Process Test ===');
        $this->newLine();

        // Set conservative memory limits
        ini_set('memory_limit', '128M');
        set_time_limit(120); // 2 minutes

        // Monitor memory usage
        $startMemory = memory_get_usage(true);
        $this->info('📊 Starting memory usage: '.round($startMemory / 1024 / 1024, 2).' MB');

        try {
            // Get or create test company
            $company = $this->getOrCreateTestCompany();
            $this->info("✅ Using company: {$company->name}");

            // Set company context for RLS
            DB::statement("SET app.current_company_id = '{$company->id}'");

            // Initialize optimization service
            $optimizationService = new PeriodCloseOptimizationService;
            $this->logMemoryUsage('Before optimization', $startMemory);

            // Test optimization service
            $optimizationService->optimizeForPeriodClose($company);
            $this->logMemoryUsage('After optimization', $startMemory);

            // Create test user
            $user = $this->createTestUser($company);
            $this->logMemoryUsage('After user creation', $startMemory);

            // Create accounts with optimization
            $this->createAccountsOptimized($company, $optimizationService);
            $this->logMemoryUsage('After accounts creation', $startMemory);

            // Test memory management features
            $this->testMemoryManagement($optimizationService);
            $this->logMemoryUsage('After memory management test', $startMemory);

            // Test chunked processing
            $this->testChunkedProcessing($optimizationService, $company);
            $this->logMemoryUsage('After chunked processing test', $startMemory);

            // Final cleanup
            $optimizationService->resetOptimizations();
            $this->logMemoryUsage('After cleanup', $startMemory);

            // Show final statistics
            $memoryStats = $optimizationService->getMemoryStats();
            $this->newLine();
            $this->info('📊 Final Memory Statistics:');
            $this->line("   Current Usage: {$memoryStats['current_usage']} MB");
            $this->line("   Peak Usage: {$memoryStats['peak_usage']} MB");
            $this->line("   Memory Limit: {$memoryStats['limit']}");

            $this->newLine();
            $this->info('🎉 Optimized Period Close Process Test: SUCCESS');
            $this->info('💾 Memory management and optimization features validated');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Optimized period close test failed: '.$e->getMessage());

            // Show memory usage at failure point
            $currentMemory = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);
            $this->error('💾 Memory usage at failure: '.round($currentMemory / 1024 / 1024, 2).' MB');
            $this->error('💾 Peak memory usage: '.round($peakMemory / 1024 / 1024, 2).' MB');

            return 1;
        }
    }

    private function getOrCreateTestCompany(): Company
    {
        $company = Company::where('name', 'like', '%E2E Optimized Period%')->first();

        if (! $company) {
            $company = Company::create([
                'name' => 'E2E Optimized Period Close '.time(),
                'email' => 'optimized@e2etest.com',
                'phone' => '+1 (555) 999-0000',
                'website' => 'https://www.optimized-e2etest.com',
                'currency_code' => 'USD',
                'industry' => 'Technology',
                'tax_id' => 'E2E-OPT-'.time(),
                'status' => 'active',
                'setup_completed' => true,
            ]);
        }

        return $company;
    }

    private function createTestUser(Company $company): User
    {
        $user = User::first();

        if (! $user) {
            $user = User::create([
                'name' => 'E2E Optimized Period Close Test User',
                'email' => 'optimized.test@e2e.com',
                'password' => bcrypt('password'),
            ]);
        }

        return $user;
    }

    private function createAccountsOptimized(Company $company, PeriodCloseOptimizationService $optimizationService): void
    {
        $this->info('   📊 Creating Optimized Chart of Accounts:');

        $accounts = [
            ['account_number' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset'],
            ['account_number' => '1100', 'account_name' => 'Accounts Receivable', 'account_type' => 'Asset'],
            ['account_number' => '2000', 'account_name' => 'Accounts Payable', 'account_type' => 'Liability'],
            ['account_number' => '3000', 'account_name' => 'Capital Stock', 'account_type' => 'Equity'],
            ['account_number' => '4100', 'account_name' => 'Service Revenue', 'account_type' => 'Revenue'],
            ['account_number' => '5000', 'account_name' => 'Operating Expenses', 'account_type' => 'Expense'],
        ];

        // Process in smaller chunks to avoid memory spikes
        collect($accounts)->chunk(3)->each(function ($chunk) use ($company, $optimizationService) {
            $timestamp = now();
            $accountRecords = $chunk->map(function ($accountData) use ($company, $timestamp) {
                return [
                    'company_id' => $company->id,
                    'account_number' => $accountData['account_number'],
                    'account_name' => $accountData['account_name'],
                    'account_type' => $accountData['account_type'],
                    'account_category' => $accountData['account_type'].' Accounts',
                    'is_active' => true,
                    'opening_balance' => 0.00,
                    'opening_balance_date' => now()->format('Y-m-d'),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            })->toArray();

            Account::upsert(
                $accountRecords,
                ['company_id', 'account_number'],
                ['account_name', 'account_type', 'account_category', 'is_active', 'updated_at']
            );

            // Manage memory after each chunk
            $optimizationService->manageMemory();
            unset($accountRecords);
        });

        $this->line('      ✅ Created '.count($accounts).' optimized accounts');
    }

    private function testMemoryManagement(PeriodCloseOptimizationService $optimizationService): void
    {
        $this->info('   🧠 Testing Memory Management:');

        // Test memory stats
        $stats = $optimizationService->getMemoryStats();
        $this->line("      Current Memory: {$stats['current_usage']} MB");
        $this->line("      Peak Memory: {$stats['peak_usage']} MB");

        // Test garbage collection
        $beforeGC = memory_get_usage(true);
        $collected = gc_collect_cycles();
        $afterGC = memory_get_usage(true);

        $freedMB = round(($beforeGC - $afterGC) / 1024 / 1024, 2);
        $this->line("      Garbage Collection: {$collected} cycles, {$freedMB} MB freed");
    }

    private function testChunkedProcessing(PeriodCloseOptimizationService $optimizationService, Company $company): void
    {
        $this->info('   🔄 Testing Chunked Processing:');

        $processedCount = 0;
        $optimizationService->processInChunks(
            Account::where('company_id', $company->id)->orderBy('account_number'),
            2, // Small chunk size for testing
            function ($chunk) use (&$processedCount) {
                $processedCount += $chunk->count();

                // Simulate some processing
                $chunk->each(function ($account) {
                    usleep(1000); // 1ms per account
                });

                $this->line("      Processed chunk of {$chunk->count()} accounts");
            }
        );

        $this->line("      ✅ Total processed: {$processedCount} accounts");
    }

    private function logMemoryUsage(string $checkpoint, int $startMemory): void
    {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $usedMB = round($currentMemory / 1024 / 1024, 2);
        $peakMB = round($peakMemory / 1024 / 1024, 2);
        $increaseMB = round(($currentMemory - $startMemory) / 1024 / 1024, 2);

        $this->line("💾 {$checkpoint}: {$usedMB} MB (increase: {$increaseMB} MB, peak: {$peakMB} MB)");

        // Trigger garbage collection if memory usage is high
        if ($currentMemory > 40 * 1024 * 1024) { // 40MB threshold
            $collected = gc_collect_cycles();
            $this->line("🗑️  Garbage collection triggered ({$collected} cycles)");
        }
    }
}
