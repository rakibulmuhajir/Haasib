<?php

namespace Modules\Accounting\Domain\Customers\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\Customers\Events\CustomerImportBatchCompleted;

class ProcessCustomerImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public $timeout = 600; // 10 minutes

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $data
    ) {
        $this->onQueue('accounting');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $companyId = $this->data['company_id'];
        $importBatchId = $this->data['import_batch_id'];
        $customers = $this->data['customers'];
        $options = $this->data['options'];
        $metadata = $this->data['metadata'];

        $importedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $errors = [];

        DB::transaction(function () use ($companyId, $importBatchId, $customers, $options, &$importedCount, &$skippedCount, &$errorCount, &$errors) {
            $updateExisting = $options['update_existing'] ?? false;
            $defaultCurrency = $options['default_currency'] ?? 'USD';
            $defaultPaymentTerms = $options['default_payment_terms'] ?? 30;
            $defaultCreditLimit = $options['default_credit_limit'] ?? 0;

            foreach ($customers as $customerData) {
                try {
                    $customerData['company_id'] = $companyId;
                    $customerData['currency_id'] = $customerData['currency_id'] ?? $defaultCurrency;
                    $customerData['payment_terms'] = $customerData['payment_terms'] ?? $defaultPaymentTerms;
                    $customerData['credit_limit'] = $customerData['credit_limit'] ?? $defaultCreditLimit;
                    $customerData['status'] = 'active';
                    $customerData['metadata']['import_batch_id'] = $importBatchId;
                    $customerData['metadata']['import_source_index'] = $customerData['_source_index'] ?? null;

                    // Remove internal fields
                    unset($customerData['_source_index']);

                    if ($updateExisting && $this->isDuplicateCustomer($customerData, $companyId)) {
                        // Update existing customer
                        $existing = $this->findExistingCustomer($customerData, $companyId);
                        DB::table('invoicing.customers')
                            ->where('id', $existing->id)
                            ->update($customerData);
                    } else {
                        // Create new customer
                        $customerData['id'] = \Illuminate\Support\Str::uuid();
                        DB::table('invoicing.customers')->insert($customerData);
                    }

                    $importedCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = [
                        'customer_data' => $customerData,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ];

                    Log::warning('Failed to import customer in batch job', [
                        'company_id' => $companyId,
                        'import_batch_id' => $importBatchId,
                        'customer_data' => $customerData,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        // Emit batch completion event
        event(new CustomerImportBatchCompleted([
            'import_batch_id' => $importBatchId,
            'company_id' => $companyId,
            'total_customers' => count($customers),
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'options' => $options,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ]));

        Log::info('Customer import batch completed', [
            'import_batch_id' => $importBatchId,
            'company_id' => $companyId,
            'total_customers' => count($customers),
            'imported_count' => $importedCount,
            'error_count' => $errorCount,
        ]);
    }

    /**
     * Check if customer is a duplicate.
     */
    private function isDuplicateCustomer(array $customerData, string $companyId): bool
    {
        $query = DB::table('invoicing.customers')->where('company_id', $companyId);

        if (! empty($customerData['email'])) {
            $query->orWhere('email', $customerData['email']);
        }

        if (! empty($customerData['tax_id'])) {
            $query->orWhere('tax_id', $customerData['tax_id']);
        }

        if (! empty($customerData['name']) && ! empty($customerData['phone'])) {
            $query->orWhere(function ($q) use ($customerData) {
                $q->where('name', $customerData['name'])
                    ->where('phone', $customerData['phone']);
            });
        }

        return $query->exists();
    }

    /**
     * Find existing customer for update.
     */
    private function findExistingCustomer(array $customerData, string $companyId)
    {
        $query = DB::table('invoicing.customers')->where('company_id', $companyId);

        if (! empty($customerData['email'])) {
            $existing = $query->where('email', $customerData['email'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! empty($customerData['tax_id'])) {
            $existing = $query->where('tax_id', $customerData['tax_id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! empty($customerData['name']) && ! empty($customerData['phone'])) {
            $existing = $query->where('name', $customerData['name'])
                ->where('phone', $customerData['phone'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Customer import batch job failed', [
            'import_batch_id' => $this->data['import_batch_id'],
            'company_id' => $this->data['company_id'],
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempt' => $this->attempts(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'customer-import',
            'company-'.$this->data['company_id'],
            'batch-'.$this->data['import_batch_id'],
        ];
    }
}
