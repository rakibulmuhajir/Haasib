<?php

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class TrialBalanceController extends Controller
{
    /**
     * Generate trial balance for the company.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'uuid',
            'include_zero_balances' => 'boolean',
            'currency' => 'nullable|string|max:3',
            'format' => 'nullable|string|in:summary,detailed',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $companyId = $request->user()->company_id;

        return $this->generateTrialBalance($companyId, $validated);
    }

    /**
     * Generate trial balance for a specific date range.
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'company_id' => 'required|uuid|exists:auth.companies,id',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'uuid',
            'include_zero_balances' => 'boolean',
            'currency' => 'nullable|string|max:3',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return $this->generateTrialBalance($validated['company_id'], $validated);
    }

    /**
     * Export trial balance to CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'format' => 'required|string|in:csv,xlsx',
            'company_id' => 'required|uuid|exists:auth.companies,id',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'uuid',
            'include_zero_balances' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $companyId = $validated['company_id'];

        $trialBalance = $this->generateTrialBalanceData($companyId, $validated);

        // Generate CSV content
        $csv = $this->generateCsvContent($trialBalance);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="trial-balance-'.now()->format('Y-m-d').'.'.($validated['format'] === 'csv' ? 'csv' : 'xlsx').'"');
    }

    /**
     * Get trial balance summary cards.
     */
    public function summary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'company_id' => 'required|uuid|exists:auth.companies,id',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $companyId = $validated['company_id'];

        // Build base query for posted journal entries
        $query = JournalEntry::where('company_id', $companyId)
            ->where('status', 'posted');

        // Apply date filters
        if (isset($validated['date_from'])) {
            $query->where('date', '>=', $validated['date_from']);
        }
        if (isset($validated['date_to'])) {
            $query->where('date', '<=', $validated['date_to']);
        }

        // Get total counts and amounts
        $totalEntries = $query->count();
        $totalAmount = DB::table('journal_transactions')
            ->join('journal_entries', 'journal_transactions.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->when(isset($validated['date_from']), fn ($q) => $q->where('journal_entries.date', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($q) => $q->where('journal_entries.date', '<=', $validated['date_to']))
            ->sum('journal_transactions.amount');

        // Get account counts
        $activeAccounts = Account::where('company_id', $companyId)
            ->where('active', true)
            ->count();

        $totalAccounts = Account::where('company_id', $companyId)->count();

        // Get trial balance data for summary
        $trialBalance = $this->generateTrialBalanceData($companyId, $validated);
        $isBalanced = $trialBalance['summary']['total_debits'] === $trialBalance['summary']['total_credits'];

        return response()->json([
            'period' => [
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? now()->format('Y-m-d'),
            ],
            'counts' => [
                'total_journal_entries' => $totalEntries,
                'active_accounts' => $activeAccounts,
                'total_accounts' => $totalAccounts,
            ],
            'amounts' => [
                'total_debits' => $trialBalance['summary']['total_debits'],
                'total_credits' => $trialBalance['summary']['total_credits'],
                'total_amount' => $totalAmount,
                'balanced' => $isBalanced,
            ],
            'generated_at' => now()->toISOString(),
        ]);
    }

    /**
     * Generate trial balance data.
     */
    private function generateTrialBalance(string $companyId, array $options): array
    {
        $trialBalanceData = $this->generateTrialBalanceData($companyId, $options);

        return response()->json($trialBalanceData);
    }

    /**
     * Generate trial balance data (internal method).
     */
    private function generateTrialBalanceData(string $companyId, array $options): array
    {
        $includeZeroBalances = $options['include_zero_balances'] ?? false;
        $accountIds = $options['account_ids'] ?? null;
        $currency = $options['currency'] ?? null;
        $dateFrom = $options['date_from'] ?? null;
        $dateTo = $options['date_to'] ?? null;

        // Base query for trial balance using posted journal entries
        $query = DB::table('journal_transactions')
            ->join('journal_entries', 'journal_transactions.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_transactions.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', 'posted')
            ->when($dateFrom, fn ($q) => $q->where('journal_entries.date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('journal_entries.date', '<=', $dateTo))
            ->when($accountIds, fn ($q) => $q->whereIn('accounts.id', $accountIds))
            ->when($currency, fn ($q) => $q->where('journal_transactions.currency', $currency))
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.normal_balance')
            ->selectRaw('
                accounts.id,
                accounts.code,
                accounts.name,
                accounts.normal_balance,
                SUM(CASE WHEN journal_transactions.debit_credit = \'debit\' THEN journal_transactions.amount ELSE 0 END) as debit_total,
                SUM(CASE WHEN journal_transactions.debit_credit = \'credit\' THEN journal_transactions.amount ELSE 0 END) as credit_total
            ');

        // Get trial balance data
        $trialBalanceData = $query->get()->map(function ($account) {
            $balance = $account->debit_total - $account->credit_total;

            return [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'normal_balance' => $account->normal_balance,
                'debit_total' => (float) $account->debit_total,
                'credit_total' => (float) $account->credit_total,
                'balance' => (float) $balance,
                'balance_display' => number_format($balance, 2),
                'is_active' => $balance != 0 || $includeZeroBalances,
            ];
        });

        // Filter out zero balance accounts unless requested
        if (! $includeZeroBalances) {
            $trialBalanceData = $trialBalanceData->filter(fn ($account) => $account['is_active']);
        }

        // Calculate totals
        $totalDebits = $trialBalanceData->sum('debit_total');
        $totalCredits = $trialBalanceData->sum('credit_total');
        $isBalanced = abs($totalDebits - $totalCredits) < 0.01;

        return [
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo ?? now()->format('Y-m-d'),
            ],
            'generated_at' => now()->toISOString(),
            'company_id' => $companyId,
            'currency' => $currency,
            'accounts' => $trialBalanceData->values()->toArray(),
            'summary' => [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'total_difference' => $totalDebits - $totalCredits,
                'is_balanced' => $isBalanced,
                'account_count' => $trialBalanceData->count(),
            ],
            'metadata' => [
                'include_zero_balances' => $includeZeroBalances,
                'date_filter_applied' => $dateFrom || $dateTo,
                'account_filter_applied' => $accountIds !== null,
                'currency_filter_applied' => $currency !== null,
            ],
        ];
    }

    /**
     * Generate CSV content for trial balance.
     */
    private function generateCsvContent(array $trialBalance): string
    {
        $csv = "Account Code,Account Name,Normal Balance,Debit Total,Credit Total,Balance\n";

        foreach ($trialBalance['accounts'] as $account) {
            $csv .= implode(',', [
                $account['account_code'],
                '"'.str_replace('"', '""', $account['account_name']).'"',
                $account['normal_balance'],
                number_format($account['debit_total'], 2),
                number_format($account['credit_total'], 2),
                number_format($account['balance'], 2),
            ])."\n";
        }

        $csv .= "\nSummary\n";
        $csv .= 'Total Debits,'.number_format($trialBalance['summary']['total_debits'], 2)."\n";
        $csv .= 'Total Credits,'.number_format($trialBalance['summary']['total_credits'], 2)."\n";
        $csv .= 'Difference,'.number_format($trialBalance['summary']['total_difference'], 2)."\n";
        $csv .= 'Balanced,'.($trialBalance['summary']['is_balanced'] ? 'Yes' : 'No')."\n";
        $csv .= 'Generated At,'.$trialBalance['generated_at']."\n";

        return $csv;
    }
}
