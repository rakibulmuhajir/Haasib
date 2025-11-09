<?php

namespace Modules\Accounting\Domain\Ledgers\Actions;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalAudit;
use App\Models\JournalEntry;
use App\Models\JournalEntrySource;
use App\Models\JournalTransaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateManualJournalEntryAction
{
    /**
     * Create a manual journal entry with transaction lines.
     *
     * @throws ValidationException
     * @throws Exception
     */
    public function execute(array $data): array
    {
        // Validate input data
        $validator = Validator::make($data, [
            'company_id' => 'required|uuid|exists:pgsql.auth.companies,id',
            'description' => 'required|string|max:500',
            'date' => 'required|date|before_or_equal:today',
            'type' => 'required|string|in:sales,purchase,payment,receipt,adjustment,closing,opening,reversal,automation',
            'reference' => 'nullable|string|max:100',
            'currency' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'lines' => 'required|array|min:1',
            'lines.*.account_id' => 'required|uuid|exists:pgsql.acct.accounts,id',
            'lines.*.debit_credit' => 'required|string|in:debit,credit',
            'lines.*.amount' => 'required|numeric|min:0.01',
            'lines.*.description' => 'nullable|string|max:500',
            'attachments' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return DB::transaction(function () use ($validated) {
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

            if ($totalDebits == 0) {
                throw new Exception('Journal entry cannot have zero amounts');
            }

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'id' => (string) Str::uuid(),
                'company_id' => $company->id,
                'reference' => $validated['reference'] ?? $this->generateReference($company),
                'description' => $validated['description'],
                'date' => $validated['date'],
                'type' => $validated['type'],
                'status' => 'draft',
                'currency' => $validated['currency'] ?? $company->currency_code,
                'exchange_rate' => $validated['exchange_rate'] ?? 1.0,
                'created_by' => auth()->id(),
                'auto_generated' => false,
                'attachments' => $validated['attachments'] ?? [],
                'metadata' => $validated['metadata'] ?? [],
            ]);

            // Create journal transactions
            $transactions = [];
            $lineNumber = 1;

            foreach ($validated['lines'] as $line) {
                $account = $accounts->firstWhere('id', $line['account_id']);

                $transaction = JournalTransaction::create([
                    'id' => (string) Str::uuid(),
                    'journal_entry_id' => $journalEntry->id,
                    'line_number' => $lineNumber++,
                    'account_id' => $line['account_id'],
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'debit_credit' => $line['debit_credit'],
                    'amount' => $line['amount'],
                    'description' => $line['description'] ?? '',
                    'created_at' => now(),
                ]);

                $transactions[] = $transaction->toArray();
            }

            // Create source record for manual entry
            JournalEntrySource::createSource(
                $journalEntry->id,
                'Manual',
                $journalEntry->id,
                'origin',
                null,
                $journalEntry->reference
            );

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
                    'metadata' => [
                        'action' => 'create_manual',
                        'line_count' => count($validated['lines']),
                    ],
                ],
                auth()->id()
            );

            return [
                'journal_entry' => $journalEntry->toArray(),
                'transactions' => $transactions,
                'totals' => [
                    'total_debits' => $totalDebits,
                    'total_credits' => $totalCredits,
                    'balanced' => true,
                ],
            ];
        });
    }

    /**
     * Generate a unique reference number for the journal entry.
     */
    private function generateReference(Company $company): string
    {
        $year = date('Y');
        $month = date('m');

        // Count existing entries for this month
        $count = JournalEntry::where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        return sprintf('JE-%s-%s-%04d', $company->id, $year.$month, $count + 1);
    }
}
