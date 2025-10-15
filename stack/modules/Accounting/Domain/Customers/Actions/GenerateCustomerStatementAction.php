<?php

namespace Modules\Accounting\Domain\Customers\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\Customers\Events\StatementGenerated;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerStatement;
use Modules\Accounting\Domain\Customers\Services\CustomerAgingService;
use Modules\Accounting\Domain\Customers\Services\CustomerStatementService;

class GenerateCustomerStatementAction
{
    public function __construct(
        private CustomerStatementService $statementService,
        private CustomerAgingService $agingService
    ) {}

    /**
     * Generate a customer statement for a specific period.
     */
    public function execute(
        Customer $customer,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $options = []
    ): CustomerStatement {
        // Validate input
        $this->validateInput($customer, $periodStart, $periodEnd, $options);

        // Check if statement already exists
        if ($this->statementService->statementExists($customer, $periodStart, $periodEnd)) {
            throw new \InvalidArgumentException(
                'Statement already exists for this period. Use a different period or update the existing statement.'
            );
        }

        return DB::transaction(function () use ($customer, $periodStart, $periodEnd, $options) {
            // Generate statement data
            $statementData = $this->statementService->generateStatementData($customer, $periodStart, $periodEnd);

            // Generate document
            $format = $options['format'] ?? 'pdf';
            $documentPath = match ($format) {
                'pdf' => $this->statementService->generatePDFDocument($statementData),
                'csv' => $this->statementService->generateCSVDocument($statementData),
                default => throw new \InvalidArgumentException("Unsupported format: {$format}")
            };

            // Create aging snapshot
            $agingSnapshot = $this->agingService->createSnapshot(
                $customer,
                $periodEnd,
                'on_demand',
                $options['generated_by_user_id'] ?? null
            );

            // Generate checksum
            $checksum = $this->statementService->generateChecksum($statementData);

            // Create statement record
            $statement = CustomerStatement::create([
                'customer_id' => $customer->id,
                'company_id' => $customer->company_id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'generated_at' => now(),
                'generated_by_user_id' => $options['generated_by_user_id'] ?? null,
                'opening_balance' => $statementData['opening_balance'],
                'total_invoiced' => $statementData['total_invoiced'],
                'total_paid' => $statementData['total_paid'],
                'total_credit_notes' => $statementData['total_credit_notes'],
                'closing_balance' => $statementData['closing_balance'],
                'aging_bucket_summary' => $statementData['aging_buckets'],
                'document_path' => $documentPath,
                'checksum' => $checksum,
            ]);

            // Fire event
            Event::dispatch(new StatementGenerated($statement, $customer, $options));

            Log::info('Customer statement generated', [
                'statement_id' => $statement->id,
                'customer_id' => $customer->id,
                'company_id' => $customer->company_id,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
                'format' => $format,
                'generated_by' => $options['generated_by_user_id'] ?? 'system',
            ]);

            return $statement;
        });
    }

    /**
     * Validate input parameters.
     */
    private function validateInput(Customer $customer, Carbon $periodStart, Carbon $periodEnd, array $options): void
    {
        // Validate customer belongs to a company
        if (empty($customer->company_id)) {
            throw new \InvalidArgumentException('Customer must belong to a company');
        }

        // Validate period
        $periodErrors = $this->statementService->validatePeriod($periodStart, $periodEnd);
        if (! empty($periodErrors)) {
            throw new \InvalidArgumentException(implode(', ', $periodErrors));
        }

        // Validate format
        $format = $options['format'] ?? 'pdf';
        if (! in_array($format, ['pdf', 'csv'])) {
            throw new \InvalidArgumentException("Invalid format: {$format}. Supported formats: pdf, csv");
        }

        // Validate user ID if provided
        if (isset($options['generated_by_user_id'])) {
            if (! is_string($options['generated_by_user_id']) && ! is_null($options['generated_by_user_id'])) {
                throw new \InvalidArgumentException('generated_by_user_id must be a string UUID or null');
            }
        }

        // Validate additional options
        if (isset($options['approval_reference']) && strlen($options['approval_reference']) > 100) {
            throw new \InvalidArgumentException('approval_reference cannot exceed 100 characters');
        }

        if (isset($options['reason']) && strlen($options['reason']) > 500) {
            throw new \InvalidArgumentException('reason cannot exceed 500 characters');
        }
    }

    /**
     * Batch generate statements for multiple customers.
     */
    public function batchGenerate(
        array $customerIds,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $options = []
    ): array {
        $results = [
            'generated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($customerIds as $customerId) {
            try {
                $customer = Customer::where('id', $customerId)
                    ->where('company_id', $options['company_id'] ?? null)
                    ->firstOrFail();

                // Check if statement already exists
                if ($this->statementService->statementExists($customer, $periodStart, $periodEnd)) {
                    $results['skipped']++;

                    continue;
                }

                $this->execute($customer, $periodStart, $periodEnd, $options);
                $results['generated']++;

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to generate statement in batch', [
                    'customer_id' => $customerId,
                    'period_start' => $periodStart->format('Y-m-d'),
                    'period_end' => $periodEnd->format('Y-m-d'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Regenerate an existing statement.
     */
    public function regenerate(
        CustomerStatement $existingStatement,
        array $options = []
    ): CustomerStatement {
        $customer = $existingStatement->customer;
        $periodStart = $existingStatement->period_start;
        $periodEnd = $existingStatement->period_end;

        return DB::transaction(function () use ($existingStatement, $customer, $periodStart, $periodEnd, $options) {
            // Generate new statement data
            $statementData = $this->statementService->generateStatementData($customer, $periodStart, $periodEnd);

            // Generate new document
            $format = $options['format'] ?? 'pdf';
            $documentPath = match ($format) {
                'pdf' => $this->statementService->generatePDFDocument($statementData),
                'csv' => $this->statementService->generateCSVDocument($statementData),
                default => throw new \InvalidArgumentException("Unsupported format: {$format}")
            };

            // Generate new checksum
            $checksum = $this->statementService->generateChecksum($statementData);

            // Update existing statement
            $existingStatement->update([
                'generated_at' => now(),
                'generated_by_user_id' => $options['generated_by_user_id'] ?? $existingStatement->generated_by_user_id,
                'opening_balance' => $statementData['opening_balance'],
                'total_invoiced' => $statementData['total_invoiced'],
                'total_paid' => $statementData['total_paid'],
                'total_credit_notes' => $statementData['total_credit_notes'],
                'closing_balance' => $statementData['closing_balance'],
                'aging_bucket_summary' => $statementData['aging_buckets'],
                'document_path' => $documentPath,
                'checksum' => $checksum,
            ]);

            // Create updated aging snapshot
            $this->agingService->createSnapshot(
                $customer,
                $periodEnd,
                'on_demand',
                $options['generated_by_user_id'] ?? null
            );

            Log::info('Customer statement regenerated', [
                'statement_id' => $existingStatement->id,
                'customer_id' => $customer->id,
                'company_id' => $customer->company_id,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
                'format' => $format,
                'generated_by' => $options['generated_by_user_id'] ?? 'system',
            ]);

            return $existingStatement->fresh();
        });
    }
}
