<?php

namespace Modules\Accounting\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AccountingPerformanceService
{
    /**
     * Cache key prefix for accounting data
     */
    const CACHE_PREFIX = 'accounting';

    /**
     * Get trial balance with caching.
     */
    public function getCachedTrialBalance(string $companyId, array $options = []): array
    {
        $cacheKey = $this->generateCacheKey('trial_balance', $companyId, $options);

        return Cache::remember($cacheKey, 600, function () use ($companyId, $options) {
            return $this->generateTrialBalance($companyId, $options);
        });
    }

    /**
     * Get batch statistics with caching.
     */
    public function getCachedBatchStatistics(string $companyId): array
    {
        $cacheKey = self::CACHE_PREFIX.'_batch_stats_'.$companyId;

        return Cache::remember($cacheKey, 300, function () use ($companyId) {
            return $this->generateBatchStatistics($companyId);
        });
    }

    /**
     * Invalidate accounting cache for a company.
     */
    public function invalidateCompanyCache(string $companyId): void
    {
        $pattern = self::CACHE_PREFIX.'_*_'.$companyId.'_*';

        // Get all cache keys matching the pattern
        $keys = Cache::getRedis()->keys($pattern);

        if (! empty($keys)) {
            Cache::getRedis()->del($keys);
        }
    }

    /**
     * Optimize journal entry queries with index hints.
     */
    public function optimizeJournalEntryQuery(string $companyId, array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = \App\Models\JournalEntry::query()
            ->where('company_id', $companyId)
            ->with(['transactions.account' => function ($q) {
                $q->select('id', 'code', 'name', 'type');
            }])
            ->select(['id', 'company_id', 'description', 'date', 'status', 'reference', 'created_at', 'updated_at']);

        // Apply filters efficiently
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        // Add ordering by indexed columns
        $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');

        return $query;
    }

    /**
     * Generate trial balance efficiently.
     */
    protected function generateTrialBalance(string $companyId, array $options): array
    {
        $query = DB::table('journal_transactions')
            ->join('journal_entries', 'journal_transactions.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_transactions.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted');

        if (isset($options['date_from'])) {
            $query->where('journal_entries.date', '>=', $options['date_from']);
        }

        if (isset($options['date_to'])) {
            $query->where('journal_entries.date', '<=', $options['date_to']);
        }

        $results = $query->select([
            'accounts.id',
            'accounts.code',
            'accounts.name',
            'accounts.type',
            'accounts.normal_balance',
            DB::raw('SUM(CASE WHEN journal_transactions.debit_credit = "debit" THEN journal_transactions.amount ELSE 0 END) as total_debits'),
            DB::raw('SUM(CASE WHEN journal_transactions.debit_credit = "credit" THEN journal_transactions.amount ELSE 0 END) as total_credits'),
        ])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.normal_balance')
            ->orderBy('accounts.code')
            ->get();

        return [
            'accounts' => $results,
            'summary' => [
                'total_debits' => $results->sum('total_debits'),
                'total_credits' => $results->sum('total_credits'),
                'is_balanced' => abs($results->sum('total_debits') - $results->sum('total_credits')) < 0.01,
            ],
        ];
    }

    /**
     * Generate batch statistics efficiently.
     */
    protected function generateBatchStatistics(string $companyId): array
    {
        return [
            'total_batches' => \App\Models\JournalBatch::where('company_id', $companyId)->count(),
            'pending_batches' => \App\Models\JournalBatch::where('company_id', $companyId)->where('status', 'pending')->count(),
            'approved_batches' => \App\Models\JournalBatch::where('company_id', $companyId)->where('status', 'approved')->count(),
            'posted_batches' => \App\Models\JournalBatch::where('company_id', $companyId)->where('status', 'posted')->count(),
            'total_entries_in_batches' => \App\Models\JournalBatch::where('company_id', $companyId)->sum('total_entries'),
            'created_this_month' => \App\Models\JournalBatch::where('company_id', $companyId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];
    }

    /**
     * Generate cache key.
     */
    protected function generateCacheKey(string $type, string $companyId, array $options = []): string
    {
        $optionsHash = md5(serialize($options));

        return self::CACHE_PREFIX."_{$type}_{$companyId}_{$optionsHash}";
    }
}
