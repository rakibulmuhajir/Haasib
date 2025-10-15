<?php

namespace Modules\Accounting\Domain\Customers\Jobs;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\Customers\Actions\RefreshCustomerAgingSnapshotAction;
use Modules\Accounting\Domain\Customers\Models\Customer;

class UpdateCustomerAgingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public $timeout = 300; // 5 minutes

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [30, 60, 120]; // 30s, 1min, 2min

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $companyId = null,
        public ?string $customerId = null,
        public ?string $snapshotDate = null,
        public string $generatedVia = 'scheduled'
    ) {
        $this->onQueue('accounting');
    }

    /**
     * Execute the job.
     */
    public function handle(RefreshCustomerAgingSnapshotAction $agingAction): void
    {
        try {
            $snapshotDate = $this->snapshotDate ? now()->parse($this->snapshotDate)->startOfDay() : now()->startOfDay();

            if ($this->customerId) {
                // Update aging for specific customer
                $this->updateCustomerAging($agingAction, $this->customerId, $snapshotDate);
            } elseif ($this->companyId) {
                // Update aging for all customers in a company
                $this->updateCompanyAging($agingAction, $this->companyId, $snapshotDate);
            } else {
                // Update aging for all customers across all companies
                $this->updateAllAging($agingAction, $snapshotDate);
            }

            Log::info('Customer aging update job completed', [
                'company_id' => $this->companyId,
                'customer_id' => $this->customerId,
                'snapshot_date' => $snapshotDate->format('Y-m-d'),
                'generated_via' => $this->generatedVia,
                'job_id' => $this->job->getJobId(),
            ]);

        } catch (\Exception $e) {
            Log::error('Customer aging update job failed', [
                'company_id' => $this->companyId,
                'customer_id' => $this->customerId,
                'snapshot_date' => $this->snapshotDate,
                'generated_via' => $this->generatedVia,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw the exception to trigger job retry mechanism
            throw $e;
        }
    }

    /**
     * Update aging for a specific customer.
     */
    private function updateCustomerAging(
        RefreshCustomerAgingSnapshotAction $agingAction,
        string $customerId,
        \Carbon\Carbon $snapshotDate
    ): void {
        $customer = Customer::findOrFail($customerId);

        $agingAction->execute(
            $customer,
            $snapshotDate,
            $this->generatedVia
        );
    }

    /**
     * Update aging for all customers in a company.
     */
    private function updateCompanyAging(
        RefreshCustomerAgingSnapshotAction $agingAction,
        string $companyId,
        \Carbon\Carbon $snapshotDate
    ): void {
        $company = Company::findOrFail($companyId);

        $results = $agingAction->refreshAllForCompany(
            $companyId,
            $snapshotDate,
            $this->generatedVia
        );

        Log::info('Company aging update completed', [
            'company_id' => $companyId,
            'snapshot_date' => $snapshotDate->format('Y-m-d'),
            'results' => $results,
            'generated_via' => $this->generatedVia,
        ]);
    }

    /**
     * Update aging for all customers across all companies.
     */
    private function updateAllAging(
        RefreshCustomerAgingSnapshotAction $agingAction,
        \Carbon\Carbon $snapshotDate
    ): void {
        $companies = Company::where('is_active', true)->get();
        $totalResults = [
            'companies_processed' => 0,
            'total_refreshed' => 0,
            'total_skipped' => 0,
            'total_errors' => 0,
            'errors' => [],
        ];

        foreach ($companies as $company) {
            try {
                $results = $agingAction->refreshAllForCompany(
                    $company->id,
                    $snapshotDate,
                    $this->generatedVia
                );

                $totalResults['companies_processed']++;
                $totalResults['total_refreshed'] += $results['created'] ?? 0;
                $totalResults['total_skipped'] += $results['skipped'] ?? 0;

                if (! empty($results['errors'])) {
                    $totalResults['total_errors'] += count($results['errors']);
                    $totalResults['errors'] = array_merge($totalResults['errors'], $results['errors']);
                }

            } catch (\Exception $e) {
                $totalResults['total_errors']++;
                $totalResults['errors'][] = [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to update aging for company', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                    'snapshot_date' => $snapshotDate->format('Y-m-d'),
                ]);
            }
        }

        Log::info('Global aging update completed', [
            'snapshot_date' => $snapshotDate->format('Y-m-d'),
            'results' => $totalResults,
            'generated_via' => $this->generatedVia,
        ]);
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return 'customer-aging-update-'.
               ($this->companyId ?? 'all').'-'.
               ($this->customerId ?? 'all').'-'.
               ($this->snapshotDate ?? now()->format('Y-m-d')).'-'.
               $this->generatedVia;
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Customer aging update job failed permanently', [
            'company_id' => $this->companyId,
            'customer_id' => $this->customerId,
            'snapshot_date' => $this->snapshotDate,
            'generated_via' => $this->generatedVia,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'job_id' => $this->job->getJobId(),
        ]);

        // Optionally send notification to administrators about the failure
        // This could integrate with your notification system
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        $tags = ['customer-aging', $this->generatedVia];

        if ($this->companyId) {
            $tags[] = 'company-'.$this->companyId;
        }

        if ($this->customerId) {
            $tags[] = 'customer-'.$this->customerId;
        }

        if ($this->snapshotDate) {
            $tags[] = 'date-'.$this->snapshotDate;
        }

        return $tags;
    }
}
