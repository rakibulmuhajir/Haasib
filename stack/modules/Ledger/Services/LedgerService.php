<?php

namespace Modules\Ledger\Services;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Support\ServiceContext;
use App\Traits\AuditLogging;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LedgerService - Handles general ledger and accounting business logic
 *
 * This service follows the Haasib Constitution principles, particularly:
 * - RBAC Integrity: Respects seeded role/permission catalog
 * - Tenancy & RLS Safety: Enforces company scoping
 * - Audit, Idempotency & Observability: Logs all ledger operations
 * - Module Governance: Part of the Ledger module
 *
 * @link https://github.com/Haasib/haasib/blob/main/.specify/memory/constitution.md
 */
class LedgerService
{
    use AuditLogging;

    /**
     * Create a journal entry
     *
     * @param  Company  $company  The company context
     * @param  array  $lines  Array of journal entry lines [account_id, debit, credit, description]
     * @param  string  $description  Description of the journal entry
     * @param  string|null  $reference  Reference for the journal entry
     * @param  string|null  $entryDate  Date of the entry (defaults to current date)
     * @param  ServiceContext  $context  The service context
     * @param  string|null  $entryType  Type of journal entry
     * @return JournalEntry The created journal entry
     *
     * @throws \InvalidArgumentException If validation fails
     * @throws \Throwable If the journal entry creation fails
     */
    public function createJournalEntry(
        Company $company,
        array $lines,
        string $description,
        ?string $reference,
        ?string $entryDate,
        ServiceContext $context,
        ?string $entryType = 'general'
    ): JournalEntry {
        $idempotencyKey = $context->getIdempotencyKey();

        try {
            $result = DB::transaction(function () use (
                $company,
                $lines,
                $description,
                $reference,
                $entryDate,
                $idempotencyKey,
                $entryType
            ) {
                // Validate that debits and credits balance
                $totalDebits = 0;
                $totalCredits = 0;

                foreach ($lines as $line) {
                    if (! isset($line['account_id']) || ! isset($line['debit']) || ! isset($line['credit'])) {
                        throw new \InvalidArgumentException('Each line must have account_id, debit, and credit');
                    }

                    if ($line['debit'] < 0 || $line['credit'] < 0) {
                        throw new \InvalidArgumentException('Debit and credit amounts must be non-negative');
                    }

                    if ($line['debit'] > 0 && $line['credit'] > 0) {
                        throw new \InvalidArgumentException('A line cannot have both debit and credit amounts');
                    }

                    $totalDebits += $line['debit'];
                    $totalCredits += $line['credit'];
                }

                if (abs($totalDebits - $totalCredits) > 0.01) { // Small tolerance for floating point errors
                    throw new \InvalidArgumentException(
                        "Journal entry must balance: debits ({$totalDebits}) do not equal credits ({$totalCredits})"
                    );
                }

                // Validate that all accounts belong to the company
                $accountIds = array_column($lines, 'account_id');
                $validAccounts = ChartOfAccount::where('company_id', $company->id)
                    ->whereIn('id', $accountIds)
                    ->pluck('id')
                    ->toArray();

                if (count($validAccounts) !== count($accountIds)) {
                    $invalidAccounts = array_diff($accountIds, $validAccounts);
                    throw new \InvalidArgumentException(
                        'One or more accounts do not belong to this company: '.implode(', ', $invalidAccounts)
                    );
                }

                // Create journal entry
                $journalEntry = new JournalEntry([
                    'company_id' => $company->id,
                    'description' => $description,
                    'reference' => $reference,
                    'entry_date' => $entryDate ?? now()->toDateString(),
                    'entry_type' => $entryType,
                    'total_debits' => $totalDebits,
                    'total_credits' => $totalCredits,
                    'status' => 'posted', // Default to posted
                    'idempotency_key' => $idempotencyKey,
                ]);

                if (! $journalEntry->save()) {
                    throw new \RuntimeException('Failed to save journal entry: validation failed');
                }

                // Create journal entry lines
                foreach ($lines as $line) {
                    $journalLine = new JournalEntryLine([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $line['account_id'],
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'description' => $line['description'] ?? $description,
                    ]);

                    if (! $journalLine->save()) {
                        throw new \RuntimeException('Failed to save journal entry line: validation failed');
                    }
                }

                return $journalEntry->fresh(['lines', 'company']);
            });
        } catch (\Throwable $e) {
            Log::error('Transaction failed in createJournalEntry', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'company_id' => $company->id,
                'description' => $description,
            ]);
            throw $e;
        }

        if (! $result) {
            throw new \RuntimeException('DB transaction returned null');
        }

        $this->logAudit('ledger.journal_entry_created', [
            'company_id' => $company->id,
            'journal_entry_id' => $result->id,
            'description' => $description,
            'reference' => $reference,
            'total_lines' => count($result->lines),
            'total_debits' => $result->total_debits,
            'total_credits' => $result->total_credits,
        ], $context);

        return $result;
    }

    /**
     * Get journal entries for a company with pagination
     *
     * @param  Company  $company  The company context
     * @param  ServiceContext  $context  The service context
     * @param  int  $perPage  Number of results per page
     * @param  string|null  $startDate  Optional start date filter
     * @param  string|null  $endDate  Optional end date filter
     * @param  string|null  $entryType  Optional entry type filter
     * @return LengthAwarePaginator The journal entries
     */
    public function getJournalEntriesForCompany(
        Company $company,
        ServiceContext $context,
        int $perPage = 20,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $entryType = null
    ): LengthAwarePaginator {
        $query = JournalEntry::where('company_id', $company->id);

        if ($startDate) {
            $query->where('entry_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('entry_date', '<=', $endDate);
        }

        if ($entryType) {
            $query->where('entry_type', $entryType);
        }

        $entries = $query->with(['lines.account'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $this->logAudit('ledger.journal_entries_viewed', [
            'company_id' => $company->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'entry_type' => $entryType,
            'total_count' => $entries->total(),
        ], $context);

        return $entries;
    }

    /**
     * Get account balance
     *
     * @param  ChartOfAccount  $account  The account to get balance for
     * @param  ServiceContext  $context  The service context
     * @param  string|null  $asOfDate  Date to calculate balance as of (defaults to now)
     * @return array Balance information [balance, debit_total, credit_total]
     */
    public function getAccountBalance(ChartOfAccount $account, ServiceContext $context, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        // Calculate the balance by summing all journal entry lines for this account
        $lines = JournalEntryLine::where('account_id', $account->id)
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.entry_date', '<=', $asOfDate)
            ->where('journal_entries.company_id', $account->company_id)
            ->select(
                DB::raw('SUM(journal_entry_lines.debit) as total_debits'),
                DB::raw('SUM(journal_entry_lines.credit) as total_credits')
            )
            ->first();

        $debitTotal = (float) ($lines->total_debits ?? 0);
        $creditTotal = (float) ($lines->total_credits ?? 0);

        // Calculate balance based on account type
        $balance = $this->calculateAccountBalance($account->account_type, $debitTotal, $creditTotal);

        $result = [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'account_number' => $account->account_number,
            'account_type' => $account->account_type,
            'balance' => $balance,
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'as_of_date' => $asOfDate,
        ];

        $this->logAudit('ledger.account_balance_viewed', [
            'account_id' => $account->id,
            'account_name' => $account->name,
            'as_of_date' => $asOfDate,
        ], $context);

        return $result;
    }

    /**
     * Calculate account balance based on account type
     *
     * @param  string  $accountType  The account type
     * @param  float  $debitTotal  Total debits
     * @param  float  $creditTotal  Total credits
     * @return float The calculated balance
     */
    private function calculateAccountBalance(string $accountType, float $debitTotal, float $creditTotal): float
    {
        // Different account types have different normal balances
        // Assets and Expenses have debit normal balances
        // Liabilities, Equity, and Revenue have credit normal balances
        switch (strtolower($accountType)) {
            case 'asset':
            case 'expense':
                return $debitTotal - $creditTotal;
            case 'liability':
            case 'equity':
            case 'revenue':
                return $creditTotal - $debitTotal;
            default:
                // For other types (like contra accounts), return net balance
                return $debitTotal - $creditTotal;
        }
    }

    /**
     * Get trial balance for a company
     *
     * @param  Company  $company  The company context
     * @param  ServiceContext  $context  The service context
     * @param  string|null  $asOfDate  Date to calculate trial balance as of (defaults to now)
     * @return array Trial balance information
     */
    public function getTrialBalance(Company $company, ServiceContext $context, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        // Get all accounts for the company
        $accounts = ChartOfAccount::where('company_id', $company->id)->get();

        $trialBalance = [];
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($accounts as $account) {
            $accountBalance = $this->getAccountBalance($account, $context, $asOfDate);

            $trialBalance[] = [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'account_number' => $account->account_number,
                'account_type' => $account->account_type,
                'balance' => $accountBalance['balance'],
                'is_debit' => $accountBalance['balance'] >= 0,
            ];

            if ($accountBalance['balance'] > 0) {
                if (in_array(strtolower($account->account_type), ['asset', 'expense'])) {
                    $totalDebits += $accountBalance['balance'];
                } else {
                    $totalCredits += $accountBalance['balance'];
                }
            } elseif ($accountBalance['balance'] < 0) {
                if (in_array(strtolower($account->account_type), ['asset', 'expense'])) {
                    $totalCredits += abs($accountBalance['balance']);
                } else {
                    $totalDebits += abs($accountBalance['balance']);
                }
            }
        }

        $result = [
            'company_id' => $company->id,
            'as_of_date' => $asOfDate,
            'accounts' => $trialBalance,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'difference' => abs($totalDebits - $totalCredits),
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.01, // Tolerance for floating point errors
        ];

        $this->logAudit('ledger.trial_balance_viewed', [
            'company_id' => $company->id,
            'as_of_date' => $asOfDate,
            'account_count' => count($trialBalance),
        ], $context);

        return $result;
    }

    /**
     * Post journal entry to ledger
     *
     * @param  JournalEntry  $journalEntry  The journal entry to post
     * @param  ServiceContext  $context  The service context
     * @return JournalEntry The updated journal entry
     *
     * @throws \InvalidArgumentException If journal entry is already posted
     */
    public function postJournalEntry(JournalEntry $journalEntry, ServiceContext $context): JournalEntry
    {
        if ($journalEntry->status === 'posted') {
            throw new \InvalidArgumentException('Journal entry is already posted');
        }

        $journalEntry->status = 'posted';
        $journalEntry->posted_at = now();
        $journalEntry->posted_by_user_id = $context->getUser()->id;
        $result = $journalEntry->save();

        $this->logAudit('ledger.journal_entry_posted', [
            'journal_entry_id' => $journalEntry->id,
            'company_id' => $journalEntry->company_id,
            'reference' => $journalEntry->reference,
        ], $context);

        return $journalEntry->fresh();
    }

    /**
     * Reverse a journal entry
     *
     * @param  JournalEntry  $journalEntry  The journal entry to reverse
     * @param  string  $reason  Reason for reversal
     * @param  ServiceContext  $context  The service context
     * @return JournalEntry The reversed journal entry
     *
     * @throws \InvalidArgumentException If journal entry cannot be reversed
     */
    public function reverseJournalEntry(JournalEntry $journalEntry, string $reason, ServiceContext $context): JournalEntry
    {
        if ($journalEntry->status === 'reversed') {
            throw new \InvalidArgumentException('Journal entry is already reversed');
        }

        if ($journalEntry->status === 'pending') {
            throw new \InvalidArgumentException('Cannot reverse a pending journal entry, delete it instead');
        }

        $result = DB::transaction(function () use ($journalEntry, $reason) {
            // Create a reverse entry with opposite debits and credits
            $reverseLines = [];
            foreach ($journalEntry->lines as $line) {
                $reverseLines[] = [
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,  // Reverse: if original was credit, this is debit
                    'credit' => $line->debit,  // Reverse: if original was debit, this is credit
                    'description' => "Reversal of entry {$journalEntry->id}: {$line->description}",
                ];
            }

            // Create the reversing journal entry
            $reverseEntry = $this->createJournalEntry(
                $journalEntry->company,
                $reverseLines,
                "Reversal of entry {$journalEntry->id}: {$journalEntry->description}",
                "REV-{$journalEntry->reference}",
                now()->toDateString(),
                $context,
                'reversal'
            );

            // Mark the original entry as reversed
            $journalEntry->status = 'reversed';
            $journalEntry->reversed_at = now();
            $journalEntry->reversed_reason = $reason;
            $journalEntry->reversal_entry_id = $reverseEntry->id;
            $journalEntry->save();

            return $journalEntry->fresh();
        });

        $this->logAudit('ledger.journal_entry_reversed', [
            'journal_entry_id' => $journalEntry->id,
            'company_id' => $journalEntry->company_id,
            'reason' => $reason,
        ], $context);

        return $result;
    }

    /**
     * Get chart of accounts for a company
     *
     * @param  Company  $company  The company context
     * @param  ServiceContext  $context  The service context
     * @param  bool  $activeOnly  Whether to return only active accounts
     * @return \Illuminate\Database\Eloquent\Collection The chart of accounts
     */
    public function getChartOfAccounts(Company $company, ServiceContext $context, bool $activeOnly = true)
    {
        $query = ChartOfAccount::where('company_id', $company->id);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $accounts = $query->orderBy('account_type')
            ->orderBy('account_number')
            ->get();

        $this->logAudit('ledger.chart_of_accounts_viewed', [
            'company_id' => $company->id,
            'active_only' => $activeOnly,
            'account_count' => $accounts->count(),
        ], $context);

        return $accounts;
    }

    /**
     * Create a new account in the chart of accounts
     *
     * @param  Company  $company  The company context
     * @param  array  $accountData  Account data [name, account_number, account_type, description, etc.]
     * @param  ServiceContext  $context  The service context
     * @return ChartOfAccount The created account
     */
    public function createChartOfAccount(Company $company, array $accountData, ServiceContext $context): ChartOfAccount
    {
        // Validate required fields
        if (empty($accountData['name']) || empty($accountData['account_number']) || empty($accountData['account_type'])) {
            throw new \InvalidArgumentException('Account name, number, and type are required');
        }

        // Check if account number already exists for this company
        if (ChartOfAccount::where('company_id', $company->id)
            ->where('account_number', $accountData['account_number'])
            ->exists()) {
            throw new \InvalidArgumentException('Account number already exists for this company');
        }

        $account = new ChartOfAccount([
            'company_id' => $company->id,
            'name' => $accountData['name'],
            'account_number' => $accountData['account_number'],
            'account_type' => $accountData['account_type'],
            'description' => $accountData['description'] ?? null,
            'parent_account_id' => $accountData['parent_account_id'] ?? null,
            'is_active' => $accountData['is_active'] ?? true,
        ]);

        if (! $account->save()) {
            throw new \RuntimeException('Failed to save chart of account: validation failed');
        }

        $this->logAudit('ledger.chart_of_account_created', [
            'account_id' => $account->id,
            'company_id' => $company->id,
            'account_number' => $account->account_number,
            'account_name' => $account->name,
        ], $context);

        return $account->fresh();
    }

    /**
     * Get ledger statistics
     *
     * @param  Company  $company  The company context
     * @param  ServiceContext  $context  The service context
     * @param  string|null  $startDate  Optional start date filter
     * @param  string|null  $endDate  Optional end date filter
     * @return array Statistics about the ledger
     */
    public function getLedgerStatistics(Company $company, ServiceContext $context, ?string $startDate = null, ?string $endDate = null): array
    {
        $entryQuery = JournalEntry::where('company_id', $company->id);

        if ($startDate) {
            $entryQuery->where('entry_date', '>=', $startDate);
        }

        if ($endDate) {
            $entryQuery->where('entry_date', '<=', $endDate);
        }

        $entries = $entryQuery->get();
        $entryCount = $entries->count();

        $stats = [
            'company_id' => $company->id,
            'total_entries' => $entryCount,
            'total_debits' => $entries->sum('total_debits'),
            'total_credits' => $entries->sum('total_credits'),
            'period_start' => $startDate,
            'period_end' => $endDate,
            'entry_types' => $entries->groupBy('entry_type')->map->count(),
        ];

        // Get account statistics
        $stats['total_accounts'] = ChartOfAccount::where('company_id', $company->id)->count();
        $stats['active_accounts'] = ChartOfAccount::where('company_id', $company->id)
            ->where('is_active', true)
            ->count();

        $this->logAudit('ledger.statistics_viewed', [
            'company_id' => $company->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], $context);

        return $stats;
    }
}
