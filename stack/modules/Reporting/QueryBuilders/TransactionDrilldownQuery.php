<?php

namespace Modules\Reporting\QueryBuilders;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TransactionDrilldownQuery
{
    private string $companyId;

    private ?string $accountCode = null;

    private ?string $accountType = null;

    private ?string $accountCategory = null;

    private ?string $counterpartyId = null;

    private ?string $dateFrom = null;

    private ?string $dateTo = null;

    private ?int $limit = 100;

    /**
     * Initialize the query builder
     */
    public function __construct(string $companyId)
    {
        $this->companyId = $companyId;
    }

    /**
     * Set account code filter
     */
    public function forAccount(string $accountCode): self
    {
        $this->accountCode = $accountCode;

        return $this;
    }

    /**
     * Set account type filter
     */
    public function forAccountType(string $accountType): self
    {
        $this->accountType = $accountType;

        return $this;
    }

    /**
     * Set account category filter
     */
    public function forAccountCategory(string $accountCategory): self
    {
        $this->accountCategory = $accountCategory;

        return $this;
    }

    /**
     * Set counterparty filter
     */
    public function forCounterparty(string $counterpartyId): self
    {
        $this->counterpartyId = $counterpartyId;

        return $this;
    }

    /**
     * Set date range filter
     */
    public function forDateRange(?string $dateFrom, ?string $dateTo = null): self
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;

        return $this;
    }

    /**
     * Set limit
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Get transaction query builder
     */
    public function getQuery(): Builder
    {
        $query = DB::table('rpt.v_transaction_drilldown')
            ->leftJoin('acct.accounts as a', 'rpt.v_transaction_drilldown.account_id', '=', 'a.id')
            ->leftJoin('acct.counterparties as c', 'rpt.v_transaction_drilldown.counterparty_id', '=', 'c.id')
            ->leftJoin('ledger.journal_entries as je', 'rpt.v_transaction_drilldown.journal_entry_id', '=', 'je.id')
            ->where('rpt.v_transaction_drilldown.company_id', $this->companyId)
            ->where('rpt.v_transaction_drilldown.has_access', true);

        // Apply filters
        if ($this->accountCode) {
            $query->where('a.account_code', $this->accountCode);
        }

        if ($this->accountType) {
            $query->where('a.account_type', $this->accountType);
        }

        if ($this->accountCategory) {
            $query->where('a.account_category', $this->accountCategory);
        }

        if ($this->counterpartyId) {
            $query->where('rpt.v_transaction_drilldown.counterparty_id', $this->counterpartyId);
        }

        if ($this->dateFrom) {
            $query->where('rpt.v_transaction_drilldown.entry_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->where('rpt.v_transaction_drilldown.entry_date', '<=', $this->dateTo);
        }

        return $query;
    }

    /**
     * Execute the query and return results
     */
    public function execute(): array
    {
        return $this->getQuery()
            ->select([
                'rpt.v_transaction_drilldown.journal_line_id',
                'rpt.v_transaction_drilldown.journal_entry_id',
                'je.entry_number',
                'je.entry_date',
                'je.description as entry_description',
                'rpt.v_transaction_drilldown.account_id',
                'a.account_code',
                'a.account_name',
                'a.account_type',
                'a.account_category',
                'rpt.v_transaction_drilldown.counterparty_id',
                'c.counterparty_name',
                'c.counterparty_type',
                'rpt.v_transaction_drilldown.amount',
                'rpt.v_transaction_drilldown.description as line_description',
                'rpt.v_transaction_drilldown.document_reference_id',
                'dr.reference_number',
                'dr.reference_type',
                'cu.name as currency_name',
                'cu.symbol as currency_symbol',
                'rpt.v_transaction_drilldown.has_access',
            ])
            ->leftJoin('public.currencies as cu', 'rpt.v_transaction_drilldown.currency_code', '=', 'cu.code')
            ->orderBy('rpt.v_transaction_drilldown.entry_date', 'desc')
            ->orderBy('je.entry_number', 'desc')
            ->orderBy('rpt.v_transaction_drilldown.journal_line_id')
            ->limit($this->limit)
            ->get()
            ->toArray();
    }

    /**
     * Get summary statistics for transactions
     */
    public function getSummary(): array
    {
        $query = $this->getQuery();

        return [
            'total_transactions' => $query->count(),
            'total_amount' => $query->sum('rpt.v_transaction_drilldown.amount'),
            'total_debits' => $query->where('rpt.v_transaction_drilldown.amount', '>', 0)->sum('rpt.v_transaction_drilldown.amount'),
            'total_credits' => abs($query->where('rpt.v_transaction_drilldown.amount', '<', 0)->sum('rpt.v_transaction_drilldown.amount')),
            'amount_by_type' => $this->getAmountByAccountType(),
            'amount_by_category' => $this->getAmountByAccountCategory(),
            'amount_by_counterparty' => $this->getAmountByCounterparty(),
            'transactions_by_month' => $this->getTransactionsByMonth(),
        ];
    }

    /**
     * Get amount breakdown by account type
     */
    private function getAmountByAccountType(): array
    {
        return $this->getQuery()
            ->selectRaw('
                a.account_type,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debits,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_credits,
                SUM(amount) as net_amount
            ')
            ->groupBy('a.account_type')
            ->orderBy('net_amount', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get amount breakdown by account category
     */
    private function getAmountByAccountCategory(): array
    {
        return $this->getQuery()
            ->selectRaw('
                a.account_category,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debits,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_credits,
                SUM(amount) as net_amount
            ')
            ->groupBy('a.account_category')
            ->orderBy('net_amount', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get amount breakdown by counterparty
     */
    private function getAmountByCounterparty(): array
    {
        return $this->getQuery()
            ->selectRaw('
                COALESCE(c.counterparty_name, \'No Counterparty\') as counterparty_name,
                COUNT(*) as transaction_count,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debits,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_credits,
                SUM(amount) as net_amount
            ')
            ->groupBy('c.counterparty_name')
            ->orderBy('net_amount', 'desc')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * Get transactions by month
     */
    private function getTransactionsByMonth(): array
    {
        return $this->getQuery()
            ->selectRaw('
                    DATE_TRUNC(\'month\', entry_date) as month,
                    COUNT(*) as transaction_count,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debits,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_credits,
                    SUM(amount) as net_amount
                ')
            ->groupBy(DB::raw('DATE_TRUNC(\'month\', entry_date)'))
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get()
            ->toArray();
    }

    /**
     * Get paginated transactions
     */
    public function paginate(int $perPage = 50, int $page = 1): array
    {
        $query = $this->getQuery();
        $total = $query->count();

        $results = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->select([
                'rpt.v_transaction_drilldown.journal_line_id',
                'rpt.v_transaction_drilldown.journal_entry_id',
                'je.entry_number',
                'je.entry_date',
                'je.description as entry_description',
                'a.account_code',
                'a.account_name',
                'a.account_type',
                'a.account_category',
                'c.counterparty_name',
                'rpt.v_transaction_drilldown.amount',
                'rpt.v_transaction_drilldown.description as line_description',
                'cu.symbol as currency_symbol',
            ])
            ->leftJoin('public.currencies as cu', 'rpt.v_transaction_drilldown.currency_code', '=', 'cu.code')
            ->get()
            ->toArray();

        return [
            'data' => $results,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Get transactions for a specific account with full details
     */
    public function getAccountTransactions(string $accountCode, array $options = []): array
    {
        $limit = $options['limit'] ?? 100;
        $includeBalances = $options['include_balances'] ?? false;
        $groupBy = $options['group_by'] ?? null;

        $this->forAccount($accountCode);

        $transactions = $this->limit($limit)->execute();

        $result = [
            'transactions' => $transactions,
            'summary' => $this->getSummary(),
        ];

        if ($includeBalances) {
            $result['running_balances'] = $this->getRunningBalances($accountCode);
        }

        if ($groupBy) {
            $result['grouped_data'] = $this->getGroupedData($groupBy);
        }

        return $result;
    }

    /**
     * Get running balances for an account
     */
    private function getRunningBalances(string $accountCode): array
    {
        $query = $this->forAccount($accountCode)->getQuery();

        $cumulativeBalance = 0;
        $balances = [];

        $transactions = $query
            ->orderBy('rpt.v_transaction_drilldown.entry_date', 'asc')
            ->orderBy('rpt.v_transaction_drilldown.journal_line_id')
            ->select(['rpt.v_transaction_drilldown.entry_date', 'rpt.v_transaction_drilldown.amount'])
            ->get()
            ->toArray();

        foreach ($transactions as $transaction) {
            $cumulativeBalance += $transaction['amount'];
            $balances[] = [
                'date' => $transaction['entry_date'],
                'amount' => $transaction['amount'],
                'running_balance' => $cumulativeBalance,
            ];
        }

        return $balances;
    }

    /**
     * Get grouped data based on specified field
     */
    private function getGroupedData(string $groupBy): array
    {
        $query = $this->getQuery();

        $groupField = match ($groupBy) {
            'month' => 'DATE_TRUNC(\'month\', rpt.v_transaction_drilldown.entry_date)',
            'counterparty' => 'c.counterparty_name',
            'account' => 'a.account_code',
            'type' => 'a.account_type',
            default => 'rpt.v_transaction_drilldown.entry_date',
        };

        return $query
            ->selectRaw("
                {$groupField} as group_field,
                COUNT(*) as transaction_count,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_debits,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_credits,
                SUM(amount) as net_amount,
                MIN(entry_date) as first_transaction,
                MAX(entry_date) as last_transaction
            ")
            ->groupBy(DB::raw($groupField))
            ->orderBy('group_field', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Search transactions by description
     */
    public function search(string $searchTerm): array
    {
        return $this->getQuery()
            ->where(function ($query) use ($searchTerm) {
                $query->where('rpt.v_transaction_drilldown.description', 'ilike', "%{$searchTerm}%")
                    ->orWhere('rpt.v_transaction_drilldown.line_description', 'ilike', "%{$searchTerm}%")
                    ->orWhere('je.description', 'ilike', "%{$searchTerm}%")
                    ->orWhere('a.account_name', 'ilike', "%{$searchTerm}%")
                    ->orWhere('c.counterparty_name', 'ilike', "%{$searchTerm}%");
            })
            ->limit($this->limit)
            ->get()
            ->toArray();
    }

    /**
     * Get transactions with document references
     */
    public function withDocumentReferences(): array
    {
        return $this->getQuery()
            ->whereNotNull('rpt.v_transaction_drilldown.document_reference_id')
            ->limit($this->limit)
            ->get()
            ->toArray();
    }

    /**
     * Get transactions for export
     */
    public function forExport(array $options = []): array
    {
        $limit = $options['limit'] ?? 10000; // Higher limit for exports
        $format = $options['format'] ?? 'array';
        $fields = $options['fields'] ?? null;

        $this->limit($limit);

        $query = $this->getQuery();

        // Select specific fields if requested
        if ($fields) {
            $query->select($fields);
        }

        $results = $query->get()->toArray();

        if ($format === 'csv') {
            return $this->formatForCsv($results);
        }

        return $results;
    }

    /**
     * Format results for CSV export
     */
    private function formatForCsv(array $results): array
    {
        $csvData = [];
        $headers = [
            'Date',
            'Entry Number',
            'Account Code',
            'Account Name',
            'Account Type',
            'Counterparty',
            'Description',
            'Amount',
            'Currency',
        ];

        foreach ($results as $row) {
            $csvData[] = [
                $row['entry_date'] ?? '',
                $row['entry_number'] ?? '',
                $row['account_code'] ?? '',
                $row['account_name'] ?? '',
                $row['account_type'] ?? '',
                $row['counterparty_name'] ?? '',
                $row['description'] ?? '',
                $row['amount'] ?? 0,
                $row['currency_symbol'] ?? '',
            ];
        }

        return array_merge([$headers], $csvData);
    }

    /**
     * Validate query parameters
     */
    public function validateParameters(): array
    {
        $errors = [];

        if ($this->dateFrom && $this->dateTo && Carbon::parse($this->dateFrom)->greaterThan(Carbon::parse($this->dateTo))) {
            $errors[] = 'Date from must be before or equal to date to';
        }

        if ($this->limit < 1 || $this->limit > 10000) {
            $errors[] = 'Limit must be between 1 and 10000';
        }

        return $errors;
    }

    /**
     * Get cache key for the query
     */
    public function getCacheKey(): string
    {
        $keyData = [
            'company_id' => $this->companyId,
            'account_code' => $this->accountCode,
            'account_type' => $this->accountType,
            'account_category' => $this->accountCategory,
            'counterparty_id' => $this->counterpartyId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'limit' => $this->limit,
        ];

        return 'transaction_drilldown:'.md5(serialize($keyData));
    }
}
