<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Support\ServiceContext;
use Modules\Ledger\Domain\PeriodClose\Exceptions\PeriodCloseException;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Services\LedgerService;

/**
 * Action to create a period close adjustment.
 *
 * This action creates a journal entry with type 'period_adjustment' that is
 * specifically linked to a period close workflow. The adjustment is
 * validated, tracked with proper audit logging, and associated with the
 * period close for reporting purposes.
 */
class CreatePeriodCloseAdjustmentAction
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    /**
     * Execute the period close adjustment creation.
     *
     * @param  PeriodClose  $periodClose  The period close to create adjustment for
     * @param  array  $data  The adjustment data
     * @param  ServiceContext  $context  The service context
     * @return \App\Models\JournalEntry The created journal entry
     *
     * @throws PeriodCloseException If adjustment cannot be created
     * @throws \InvalidArgumentException If data is invalid
     */
    public function execute(
        PeriodClose $periodClose,
        array $data,
        ServiceContext $context
    ): \App\Models\JournalEntry {
        // Validate period close status allows adjustments
        $this->validatePeriodCloseStatus($periodClose);

        // Validate accounting period status
        $this->validateAccountingPeriodStatus($periodClose->accountingPeriod);

        // Validate adjustment data
        $this->validateAdjustmentData($data);

        // Validate journal entry lines
        $this->validateJournalLines($data['lines'], $periodClose->company);

        // Create the adjustment
        return $this->createAdjustment($periodClose, $data, $context);
    }

    /**
     * Validate that the period close status allows adjustments.
     */
    private function validatePeriodCloseStatus(PeriodClose $periodClose): void
    {
        $allowedStatuses = ['in_review', 'awaiting_approval', 'locked'];

        if (! in_array($periodClose->status, $allowedStatuses)) {
            throw new PeriodCloseException(
                "Cannot create adjustment for period close in status: {$periodClose->status}. ".
                'Allowed statuses: '.implode(', ', $allowedStatuses)
            );
        }
    }

    /**
     * Validate that the accounting period is not closed.
     */
    private function validateAccountingPeriodStatus(\App\Models\AccountingPeriod $period): void
    {
        if ($period->status === 'closed') {
            throw new PeriodCloseException(
                'Cannot create adjustment for closed accounting period'
            );
        }

        if ($period->status === 'future') {
            throw new PeriodCloseException(
                'Cannot create adjustment for future accounting period'
            );
        }
    }

    /**
     * Validate the adjustment data structure.
     */
    private function validateAdjustmentData(array $data): void
    {
        $requiredFields = ['reference', 'description', 'lines'];

        foreach ($requiredFields as $field) {
            if (! isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Adjustment data missing required field: {$field}");
            }
        }

        if (! is_array($data['lines']) || empty($data['lines'])) {
            throw new \InvalidArgumentException('Adjustment must have at least one journal line');
        }

        // Validate reference format
        if (strlen($data['reference']) > 50) {
            throw new \InvalidArgumentException('Adjustment reference cannot exceed 50 characters');
        }

        // Validate description length
        if (strlen($data['description']) > 500) {
            throw new \InvalidArgumentException('Adjustment description cannot exceed 500 characters');
        }

        // Validate entry date
        if (isset($data['entry_date'])) {
            try {
                new \DateTime($data['entry_date']);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Invalid entry_date format: '.$e->getMessage());
            }
        }
    }

    /**
     * Validate journal entry lines.
     */
    private function validateJournalLines(array $lines, \App\Models\Company $company): void
    {
        $totalDebits = 0;
        $totalCredits = 0;
        $accountIds = [];

        foreach ($lines as $index => $line) {
            // Validate required fields
            $requiredLineFields = ['account_id', 'debit', 'credit', 'description'];
            foreach ($requiredLineFields as $field) {
                if (! isset($line[$field])) {
                    throw new \InvalidArgumentException(
                        "Line {$index}: Missing required field: {$field}"
                    );
                }
            }

            // Validate amounts
            if (! is_numeric($line['debit']) || $line['debit'] < 0) {
                throw new \InvalidArgumentException(
                    "Line {$index}: Debit amount must be a non-negative number"
                );
            }

            if (! is_numeric($line['credit']) || $line['credit'] < 0) {
                throw new \InvalidArgumentException(
                    "Line {$index}: Credit amount must be a non-negative number"
                );
            }

            // Validate that a line has either debit OR credit, not both
            if ($line['debit'] > 0 && $line['credit'] > 0) {
                throw new \InvalidArgumentException(
                    "Line {$index}: A line cannot have both debit and credit amounts"
                );
            }

            // Validate that a line has either debit OR credit, not neither
            if ($line['debit'] == 0 && $line['credit'] == 0) {
                throw new \InvalidArgumentException(
                    "Line {$index}: A line must have either a debit or credit amount"
                );
            }

            $totalDebits += $line['debit'];
            $totalCredits += $line['credit'];
            $accountIds[] = $line['account_id'];

            // Validate description length
            if (strlen($line['description']) > 255) {
                throw new \InvalidArgumentException(
                    "Line {$index}: Description cannot exceed 255 characters"
                );
            }
        }

        // Validate that the journal entry balances
        $difference = abs($totalDebits - $totalCredits);
        if ($difference > 0.01) { // Allow for small floating point differences
            throw new \InvalidArgumentException(
                "Journal entry must balance. Debits: {$totalDebits}, Credits: {$totalCredits}, Difference: {$difference}"
            );
        }

        // Validate that all accounts exist and belong to the company
        if (! empty($accountIds)) {
            $validAccounts = \App\Models\ChartOfAccount::where('company_id', $company->id)
                ->whereIn('id', $accountIds)
                ->pluck('id')
                ->toArray();

            $invalidAccounts = array_diff($accountIds, $validAccounts);
            if (! empty($invalidAccounts)) {
                throw new \InvalidArgumentException(
                    'Invalid or unauthorized account IDs: '.implode(', ', $invalidAccounts)
                );
            }
        }
    }

    /**
     * Create the adjustment using LedgerService.
     */
    private function createAdjustment(
        PeriodClose $periodClose,
        array $data,
        ServiceContext $context
    ): \App\Models\JournalEntry {
        try {
            return $this->ledgerService->createPeriodCloseAdjustment(
                $periodClose->company,
                $data['lines'],
                $data['description'],
                $data['reference'],
                $data['entry_date'] ?? null,
                $context,
                $periodClose->id
            );
        } catch (\Throwable $e) {
            // Convert service exceptions to domain exceptions
            if ($e instanceof \InvalidArgumentException) {
                throw new PeriodCloseException('Adjustment validation failed: '.$e->getMessage());
            }

            throw new PeriodCloseException(
                'Failed to create period close adjustment: '.$e->getMessage(),
                previous: $e
            );
        }
    }
}
