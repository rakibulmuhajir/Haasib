<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Services\PaymentAllocationReversalService;
use Illuminate\Console\Command;

class PaymentAllocationReverse extends Command
{
    protected $signature = 'payment:allocation:reverse 
                            {allocation : Allocation ID to reverse (comma-separated for multiple)}
                            {--reason= : Reason for reversal (required)}
                            {--force : Force reversal without confirmation}
                            {--json : Output results in JSON format}
                            {--dry-run : Preview reversal without executing}';

    protected $description = 'Reverse payment allocations with detailed impact analysis';

    public function handle(PaymentAllocationReversalService $reversalService): int
    {
        $this->info('🔄 Payment Allocation Reversal');
        $this->info('=================================');

        try {
            // Validate input
            $this->validateInput();

            // Get company from context
            $company = $this->getCompanyFromContext();
            $user = $this->getUserFromContext();

            // Parse allocation IDs
            $allocationIds = $this->parseAllocationIds();

            // Find allocations
            $allocations = $this->findAllocations($allocationIds, $company);

            if ($allocations->isEmpty()) {
                $this->error('❌ No valid allocations found for reversal');

                return self::FAILURE;
            }

            // Display allocations to be reversed
            $this->displayAllocationsForReversal($allocations);

            // Perform impact analysis
            $this->info('\n📊 Impact Analysis:');
            $allocationIds = $allocations->pluck('id')->toArray();
            $impact = $reversalService->getReversalImpact($allocationIds);
            $this->displayImpactAnalysis($impact);

            if ($this->option('dry-run')) {
                $this->line('\n📋 DRY RUN MODE - No reversals will be executed');

                return self::SUCCESS;
            }

            // Confirm reversal
            if (! $this->option('force') && ! $this->confirm('Proceed with reversal?', false)) {
                $this->info('Reversal cancelled.');

                return self::SUCCESS;
            }

            // Execute reversal
            $this->info('\n🔄 Reversing allocations...');
            $startTime = microtime(true);

            $results = $reversalService->reverseMultipleAllocations(
                $allocationIds,
                $this->option('reason'),
                $user
            );

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Display results
            $this->displayReversalResults($results, $duration);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error('❌ Reversal Error: '.$e->getMessage());
            $this->line('Stack trace:', 'error');
            $this->line($e->getTraceAsString(), 'error');

            return self::FAILURE;
        }
    }

    private function validateInput(): void
    {
        $reason = $this->option('reason');
        if (empty($reason)) {
            throw new \InvalidArgumentException('--reason parameter is required for reversal');
        }

        if (strlen($reason) > 500) {
            throw new \InvalidArgumentException('Reason must be 500 characters or less');
        }
    }

    private function parseAllocationIds(): array
    {
        $allocationArgument = $this->argument('allocation');

        return array_map('trim', explode(',', $allocationArgument));
    }

    private function findAllocations(array $allocationIds, Company $company)
    {
        return PaymentAllocation::query()
            ->whereHas('payment', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->whereIn('id', $allocationIds)
            ->whereNull('reversed_at') // Only active allocations can be reversed
            ->with([
                'payment',
                'invoice',
                'invoice.customer',
            ])
            ->get();
    }

    private function displayAllocationsForReversal($allocations): void
    {
        $this->info("\n📋 Allocations to be reversed:");

        $headers = ['ID', 'Payment', 'Invoice', 'Customer', 'Amount', 'Date', 'Strategy'];
        $rows = $allocations->map(function ($allocation) {
            return [
                substr($allocation->id, 0, 8),
                $allocation->payment->payment_number,
                $allocation->invoice->invoice_number,
                $allocation->invoice->customer->name,
                number_format($allocation->allocated_amount, 2),
                $allocation->allocation_date->format('Y-m-d'),
                $allocation->allocation_strategy ?? 'manual',
            ];
        })->toArray();

        $this->table($headers, $rows);

        $totalAmount = $allocations->sum('allocated_amount');
        $this->info('Total amount to be reversed: '.number_format($totalAmount, 2));
    }

    private function displayImpactAnalysis(array $impact): void
    {
        $this->info("Payments affected: {$impact['payments_affected']}");
        $this->info("Invoices affected: {$impact['invoices_affected']}");

        if (! empty($impact['invoice_impacts'])) {
            $this->info("\n📄 Invoice Impact:");
            foreach ($impact['invoice_impacts'] as $invoiceImpact) {
                $this->line("  • Invoice {$invoiceImpact['invoice_number']}: ");
                $this->line('    - Current balance: '.number_format($invoiceImpact['current_balance'], 2));
                $this->line('    - New balance: '.number_format($invoiceImpact['new_balance'], 2));
                $this->line("    - Payment status change: {$invoiceImpact['old_status']} → {$invoiceImpact['new_status']}");
            }
        }

        if (! empty($impact['payment_impacts'])) {
            $this->info("\n💰 Payment Impact:");
            foreach ($impact['payment_impacts'] as $paymentImpact) {
                $this->line("  • Payment {$paymentImpact['payment_number']}: ");
                $this->line('    - Current allocated: '.number_format($paymentImpact['current_allocated'], 2));
                $this->line('    - New allocated: '.number_format($paymentImpact['new_allocated'], 2));
                $this->line('    - Remaining amount change: '.number_format($paymentImpact['remaining_change'], 2));
            }
        }

        if (! empty($impact['warnings'])) {
            $this->info("\n⚠️  Warnings:");
            foreach ($impact['warnings'] as $warning) {
                $this->line("  • {$warning}");
            }
        }
    }

    private function displayReversalResults(array $results, float $duration): void
    {
        $this->info("\n✅ Reversal completed!");
        $this->info("⏱️  Completed in: {$duration}ms");

        $this->info("Total processed: {$results['total_processed']}");
        $this->info("Allocations reversed: {$results['reversed_count']}");
        $this->info("Errors encountered: {$results['error_count']}");

        if (! empty($results['results'])) {
            $this->info("\n📋 Reversal Details:");
            foreach ($results['results'] as $result) {
                $status = match($result['status']) {
                    'reversed' => '✅',
                    'already_reversed' => '⚠️',
                    'error' => '❌',
                    default => '❓'
                };
                
                $this->line("  {$status} Allocation ".substr($result['allocation_id'], 0, 8).": {$result['message']}");
            }
        }
        
        if ($results['error_count'] > 0) {
            $this->info("\n⚠️  Some reversals failed. Check the details above.");
        } else {
            $this->info("\n🎉 All reversals completed successfully!");
        }

        if ($this->option('json')) {
            $this->line("\n".json_encode($results, JSON_PRETTY_PRINT));
        }
    }

    private function getCompanyFromContext(): Company
    {
        // This would be implemented based on your context system
        $company = Company::first();
        if (! $company) {
            throw new \RuntimeException('No company found. Please specify --company=<id>');
        }

        return $company;
    }

    private function getUserFromContext(): User
    {
        // This would be implemented based on your auth system
        $user = User::first();
        if (! $user) {
            throw new \RuntimeException('No authenticated user found');
        }

        return $user;
    }
}
