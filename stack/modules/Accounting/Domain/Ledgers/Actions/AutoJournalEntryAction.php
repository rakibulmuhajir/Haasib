<?php

namespace Modules\Accounting\Domain\Ledgers\Actions;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalAudit;
use App\Models\JournalEntry;
use App\Models\JournalEntrySource;
use App\Models\JournalTransaction;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AutoJournalEntryAction
{
    /**
     * Create an automatic journal entry from source document.
     *
     * @throws ValidationException
     * @throws Exception
     */
    public function execute(array $data): array
    {
        // Check idempotency first
        if (isset($data['idempotency_key'])) {
            $existingEntry = $this->findExistingEntry($data['idempotency_key']);
            if ($existingEntry) {
                return [
                    'status' => 'duplicate',
                    'journal_entry_id' => $existingEntry->id,
                    'message' => 'Journal entry already exists for this source',
                ];
            }
        }

        // Validate input data
        $validator = Validator::make($data, [
            'company_id' => 'required|uuid|exists:pgsql.auth.companies,id',
            'description' => 'required|string|max:500',
            'date' => 'required|date',
            'type' => 'required|string|in:sales,purchase,payment,receipt,adjustment,automation',
            'currency' => 'required|string|max:3',
            'reference' => 'nullable|string|max:100',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|uuid|exists:pgsql.acct.accounts,id',
            'lines.*.debit_credit' => 'required|string|in:debit,credit',
            'lines.*.amount' => 'required|numeric|min:0.01',
            'lines.*.description' => 'nullable|string|max:500',
            'source_data' => 'required|array',
            'source_data.source_type' => 'required|string|max:100',
            'source_data.source_id' => 'required|string|max:100',
            'idempotency_key' => 'nullable|string|max:255',
            'auto_post' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $autoPost = $validated['auto_post'] ?? true;
        $sourceData = $validated['source_data'];

        return DB::transaction(function () use ($validated, $autoPost, $sourceData) {
            // Verify company exists
            $company = Company::findOrFail($validated['company_id']);

            // Validate that all accounts belong to the same company
            $accountIds = collect($validated['lines'])->pluck('account_id');
            $accounts = Account::whereIn('id', $accountIds)
                ->where('company_id', $company->id)
                ->get();

            if ($accounts->count() !== $accountIds->count()) {
                throw new Exception('One or more accounts do not belong to the specified company');
            }

            // Validate balance
            $totalDebits = collect($validated['lines'])
                ->where('debit_credit', 'debit')
                ->sum('amount');

            $totalCredits = collect($validated['lines'])
                ->where('debit_credit', 'credit')
                ->sum('amount');

            if (abs($totalDebits - $totalCredits) > 0.01) {
                throw new Exception('Journal entry must be balanced (total debits must equal total credits)');
            }

            // Store idempotency key to prevent duplicates
            if (isset($validated['idempotency_key'])) {
                $this->storeIdempotencyKey($validated['idempotency_key'], $company->id);
            }

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'company_id' => $company->id,
                'reference' => $validated['reference'] ?? $this->generateAutoReference($sourceData),
                'description' => $validated['description'],
                'date' => $validated['date'],
                'type' => $validated['type'],
                'status' => $autoPost ? 'posted' : 'approved',
                'currency' => $validated['currency'],
                'approved_at' => $autoPost ? now() : null,
                'posted_at' => $autoPost ? now() : null,
                'auto_generated' => true,
                'notes' => "Auto-generated from {$sourceData['source_type']} #{$sourceData['source_id']}",
            ]);

            // Create journal transactions
            $transactions = [];
            $lineNumber = 1;

            foreach ($validated['lines'] as $line) {
                $account = $accounts->firstWhere('id', $line['account_id']);

                $transaction = JournalTransaction::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number' => $lineNumber++,
                    'account_id' => $line['account_id'],
                    'debit_credit' => $line['debit_credit'],
                    'amount' => $line['amount'],
                    'description' => $line['description'] ?? '',
                    'currency' => $validated['currency'],
                ]);

                $transactions[] = $transaction->toArray();

                // Update account balances if auto-posted
                if ($autoPost) {
                    $this->updateAccountBalance($account, $line, true);
                }
            }

            // Create source record
            JournalEntrySource::create([
                'journal_entry_id' => $journalEntry->id,
                'source_type' => $sourceData['source_type'],
                'source_id' => $sourceData['source_id'],
                'source_data' => $sourceData,
            ]);

            // Create audit record
            JournalAudit::createEvent(
                $journalEntry->id,
                'created',
                [
                    'previous_state' => null,
                    'new_state' => [
                        'status' => $journalEntry->status,
                        'total_debits' => $totalDebits,
                        'total_credits' => $totalCredits,
                    ],
                    'metadata' => array_merge($sourceData, [
                        'action' => 'create_auto',
                        'auto_posted' => $autoPost,
                        'line_count' => count($validated['lines']),
                        'automatic' => true,
                    ]),
                ],
                null // System generated
            );

            if ($autoPost) {
                JournalAudit::createEvent(
                    $journalEntry->id,
                    'posted',
                    [
                        'previous_state' => ['status' => 'approved'],
                        'new_state' => [
                            'status' => 'posted',
                            'posted_at' => now()->toISOString(),
                        ],
                        'metadata' => [
                            'action' => 'auto_post',
                            'auto_generated' => true,
                        ],
                    ],
                    null // System generated
                );
            }

            return [
                'status' => 'created',
                'journal_entry_id' => $journalEntry->id,
                'journal_entry' => $journalEntry->toArray(),
                'transactions' => $transactions,
                'totals' => [
                    'total_debits' => $totalDebits,
                    'total_credits' => $totalCredits,
                    'balanced' => true,
                ],
                'auto_posted' => $autoPost,
            ];
        });
    }

    /**
     * Generate a reference for automatic journal entry.
     */
    private function generateAutoReference(array $sourceData): string
    {
        $sourceType = $sourceData['source_type'];
        $sourceId = substr($sourceData['source_id'], 0, 8);
        $timestamp = now()->format('YmdHis');

        return sprintf('AUTO-%s-%s-%s', strtoupper($sourceType), $sourceId, $timestamp);
    }

    /**
     * Store idempotency key to prevent duplicates.
     */
    private function storeIdempotencyKey(string $key, string $companyId): void
    {
        Cache::put("journal_entry:{$key}", $companyId, now()->addHours(24));
    }

    /**
     * Find existing entry by idempotency key.
     */
    private function findExistingEntry(string $key): ?JournalEntry
    {
        return JournalEntry::whereHas('sources', function ($query) use ($key) {
            $query->whereJsonContains('source_data->idempotency_key', $key);
        })->first();
    }

    /**
     * Update account balance for a transaction.
     */
    private function updateAccountBalance(Account $account, array $line, bool $isAutoPosted): void
    {
        if (! $isAutoPosted) {
            return;
        }

        $currentBalance = $account->current_balance ?? 0;
        $change = $line['amount'];

        if ($account->normal_balance === 'debit') {
            $newBalance = $line['debit_credit'] === 'debit'
                ? $currentBalance + $change
                : $currentBalance - $change;
        } else {
            $newBalance = $line['debit_credit'] === 'credit'
                ? $currentBalance + $change
                : $currentBalance - $change;
        }

        $account->update([
            'current_balance' => $newBalance,
            'last_updated_at' => now(),
        ]);
    }
}
