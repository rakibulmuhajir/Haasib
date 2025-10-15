<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\Customers\Jobs\UpdateCustomerAgingJob;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Services\CustomerAgingService;

class CustomerAgingUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'customer:aging:update 
                            {--company-id= : The ID of the company to update aging for}
                            {--customer-id= : The ID of a specific customer to update}
                            {--date= : Specific date to update aging for (YYYY-MM-DD format, default: today)}
                            {--via=scheduled : How the aging was generated (scheduled|on_demand)}
                            {--queue : Dispatch the job to the queue instead of running immediately}
                            {--batch-size=50 : Number of customers to process in each batch when using --queue}
                            {--force : Force update even if snapshot already exists for the date}
                            {--preview : Show what would be updated without making changes}
                            {--json : Output results in JSON format}';

    /**
     * The console command description.
     */
    protected $description = 'Update customer aging snapshots and analysis';

    /**
     * Execute the console command.
     */
    public function handle(CustomerAgingService $agingService): int
    {
        $this->line('Customer Aging Update');
        $this->newLine();

        try {
            // Parse and validate options
            $options = $this->parseOptions();

            if ($options['preview']) {
                return $this->showPreview($options, $agingService);
            }

            // Execute the aging update
            if ($options['queue']) {
                return $this->dispatchJob($options);
            } else {
                return $this->executeImmediately($options, $agingService);
            }

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());

            if ($this->option('json')) {
                $this->line(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString(),
                ]));
            }

            return 1;
        }
    }

    /**
     * Parse and validate command options.
     */
    private function parseOptions(): array
    {
        $options = [
            'company_id' => $this->option('company-id'),
            'customer_id' => $this->option('customer-id'),
            'date' => $this->option('date') ? now()->parse($this->option('date'))->startOfDay() : now()->startOfDay(),
            'via' => $this->option('via'),
            'queue' => $this->option('queue'),
            'batch_size' => (int) $this->option('batch-size'),
            'force' => $this->option('force'),
            'preview' => $this->option('preview'),
            'json' => $this->option('json'),
        ];

        // Validate date format
        if ($this->option('date') && ! $options['date']) {
            throw new \InvalidArgumentException('Invalid date format. Use YYYY-MM-DD format.');
        }

        // Validate via option
        if (! in_array($options['via'], ['scheduled', 'on_demand'])) {
            throw new \InvalidArgumentException("Invalid via option. Must be 'scheduled' or 'on_demand'.");
        }

        // Validate batch size
        if ($options['batch_size'] < 1 || $options['batch_size'] > 1000) {
            throw new \InvalidArgumentException('Batch size must be between 1 and 1000.');
        }

        // Validate that either company_id or customer_id is provided, but not both with invalid combinations
        if ($options['company_id'] && $options['customer_id']) {
            throw new \InvalidArgumentException('Cannot specify both company-id and customer-id. Choose one or neither for all companies.');
        }

        return $options;
    }

    /**
     * Show preview of what would be updated.
     */
    private function showPreview(array $options, CustomerAgingService $agingService): int
    {
        $this->info('Preview: Customer Aging Update');
        $this->newLine();

        if ($options['customer_id']) {
            $customer = Customer::findOrFail($options['customer_id']);
            $this->line("Customer: {$customer->name} (ID: {$customer->id})");

            // Check if snapshot already exists
            $existing = $agingService->getAgingHistory($customer, 1, $options['date']);
            if ($existing->isNotEmpty()) {
                $this->warn('⚠️  Snapshot already exists for this date. Use --force to overwrite.');
            }

        } elseif ($options['company_id']) {
            $company = Company::findOrFail($options['company_id']);
            $customerCount = Customer::where('company_id', $company->id)->count();
            $this->line("Company: {$company->name} (ID: {$company->id})");
            $this->line("Customers to process: {$customerCount}");

        } else {
            $companyCount = Company::where('is_active', true)->count();
            $customerCount = Customer::count();
            $this->line("All active companies: {$companyCount}");
            $this->line("Total customers to process: {$customerCount}");
        }

        $this->newLine();
        $this->line("Date: {$options['date']->format('Y-m-d')}");
        $this->line("Generated via: {$options['via']}");
        $this->line('Queue: '.($options['queue'] ? 'Yes' : 'No'));

        if ($options['queue']) {
            $this->line("Batch size: {$options['batch_size']}");
        }

        return 0;
    }

    /**
     * Dispatch the aging update job.
     */
    private function dispatchJob(array $options): int
    {
        $this->info('Dispatching aging update job...');

        $job = new UpdateCustomerAgingJob(
            $options['company_id'],
            $options['customer_id'],
            $options['date']->format('Y-m-d'),
            $options['via']
        );

        dispatch($job);

        $this->info('✅ Job dispatched successfully');

        if ($this->option('json')) {
            $this->line(json_encode([
                'success' => true,
                'message' => 'Job dispatched successfully',
                'job_data' => [
                    'company_id' => $options['company_id'],
                    'customer_id' => $options['customer_id'],
                    'date' => $options['date']->format('Y-m-d'),
                    'via' => $options['via'],
                    'queue' => $options['queue'],
                    'batch_size' => $options['batch_size'],
                ],
                'timestamp' => now()->toISOString(),
            ]));
        }

        return 0;
    }

    /**
     * Execute the aging update immediately.
     */
    private function executeImmediately(array $options, CustomerAgingService $agingService): int
    {
        $this->info('Updating aging snapshots...');
        $this->newLine();

        $progressBar = $this->output->createProgressBar(1);
        $progressBar->start();

        try {
            if ($options['customer_id']) {
                $results = $this->updateSingleCustomer($options, $agingService, $progressBar);
            } elseif ($options['company_id']) {
                $results = $this->updateCompanyCustomers($options, $agingService, $progressBar);
            } else {
                $results = $this->updateAllCustomers($options, $agingService, $progressBar);
            }

            $progressBar->finish();
            $this->newLine();

            $this->displayResults($results, $options);

            return $results['errors'] > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine();
            throw $e;
        }
    }

    /**
     * Update aging for a single customer.
     */
    private function updateSingleCustomer(array $options, CustomerAgingService $agingService, $progressBar): array
    {
        $customer = Customer::findOrFail($options['customer_id']);

        $this->line("Updating aging for customer: {$customer->name}");

        $snapshot = $agingService->createSnapshot(
            $customer,
            $options['date'],
            $options['via']
        );

        return [
            'processed' => 1,
            'created' => $snapshot->wasRecentlyCreated ? 1 : 0,
            'skipped' => $snapshot->wasRecentlyCreated ? 0 : 1,
            'errors' => 0,
            'error_details' => [],
        ];
    }

    /**
     * Update aging for all customers in a company.
     */
    private function updateCompanyCustomers(array $options, CustomerAgingService $agingService, $progressBar): array
    {
        $company = Company::findOrFail($options['company_id']);
        $this->line("Updating aging for company: {$company->name}");

        $results = $agingService->batchCreateSnapshots(
            $company->id,
            $options['date'],
            $options['via']
        );

        return $results;
    }

    /**
     * Update aging for all customers across all companies.
     */
    private function updateAllCustomers(array $options, CustomerAgingService $agingService, $progressBar): array
    {
        $this->line('Updating aging for all customers across all companies');

        $companies = Company::where('is_active', true)->get();
        $totalResults = [
            'processed' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => [],
        ];

        foreach ($companies as $company) {
            $this->line("Processing company: {$company->name}");

            try {
                $results = $agingService->batchCreateSnapshots(
                    $company->id,
                    $options['date'],
                    $options['via']
                );

                $totalResults['processed'] += $results['created'] + $results['skipped'];
                $totalResults['created'] += $results['created'] ?? 0;
                $totalResults['skipped'] += $results['skipped'] ?? 0;

                if (! empty($results['errors'])) {
                    $totalResults['errors'] += count($results['errors']);
                    $totalResults['error_details'] = array_merge($totalResults['error_details'], $results['errors']);
                }

            } catch (\Exception $e) {
                $totalResults['errors']++;
                $totalResults['error_details'][] = [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'error' => $e->getMessage(),
                ];

                $this->error("Error processing company {$company->name}: ".$e->getMessage());
            }
        }

        return $totalResults;
    }

    /**
     * Display the results of the aging update.
     */
    private function displayResults(array $results, array $options): void
    {
        $this->newLine();
        $this->info('Aging Update Results:');

        $this->line("✅ Processed: {$results['processed']}");
        $this->line("✅ Created: {$results['created']}");
        $this->line("➖ Skipped: {$results['skipped']}");

        if ($results['errors'] > 0) {
            $this->error("❌ Errors: {$results['errors']}");

            if (! $this->option('json') && ! empty($results['error_details'])) {
                $this->newLine();
                $this->error('Error Details:');
                foreach ($results['error_details'] as $error) {
                    if (isset($error['customer_id'])) {
                        $this->line("  Customer {$error['customer_id']}: {$error['error']}");
                    } elseif (isset($error['company_id'])) {
                        $this->line("  Company {$error['company_id']}: {$error['error']}");
                    } else {
                        $this->line("  {$error['error']}");
                    }
                }
            }
        }

        if ($this->option('json')) {
            $this->newLine();
            $this->line(json_encode([
                'success' => $results['errors'] === 0,
                'results' => $results,
                'options' => [
                    'date' => $options['date']->format('Y-m-d'),
                    'via' => $options['via'],
                    'queue' => $options['queue'],
                ],
                'timestamp' => now()->toISOString(),
            ]));
        }

        // Log completion
        Log::info('Customer aging update completed', [
            'results' => $results,
            'options' => $options,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
