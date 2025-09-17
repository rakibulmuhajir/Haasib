<?php

namespace App\Services;

use App\Exceptions\UnbalancedJournalException;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\User;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LedgerService
{
    private function logAudit(string $action, array $params, ?User $user = null, ?string $companyId = null, ?string $idempotencyKey = null, ?array $result = null): void
    {
        try {
            DB::transaction(function () use ($action, $params, $user, $companyId, $idempotencyKey, $result) {
                DB::table('audit.audit_logs')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user?->id,
                    'company_id' => $companyId,
                    'action' => $action,
                    'params' => json_encode($params),
                    'result' => $result ? json_encode($result) : null,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to write audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function createJournalEntry(
        Company $company,
        string $description,
        array $lines,
        ?string $reference = null,
        ?string $date = null,
        ?string $sourceType = null,
        ?string $sourceId = null
    ): JournalEntry {
        $result = DB::transaction(function () use ($company, $description, $lines, $reference, $date, $sourceType, $sourceId) {
            $this->validateJournalLines($company, $lines);

            $entry = new JournalEntry([
                'company_id' => $company->id,
                'reference' => $reference,
                'date' => $date ?? now()->toDateString(),
                'description' => $description,
                'status' => 'draft',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]);

            $entry->save();

            $this->createJournalLines($entry, $lines);

            return $entry->fresh(['journalLines.ledgerAccount']);
        });

        $this->logAudit('ledger.journal_entry.create', [
            'company_id' => $company->id,
            'description' => $description,
            'reference' => $reference,
            'date' => $date,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'lines_count' => count($lines),
        ], auth()->user(), $company->id, result: ['entry_id' => $result->id]);

        return $result;
    }

    public function postJournalEntry(JournalEntry $entry): JournalEntry
    {
        // Delegate posting to the state machine, which handles validation,
        // transactions, and saving.
        $result = DB::transaction(function () use ($entry) {
            $entry->stateMachine()->transitionTo('posted');

            return $entry->fresh(['journalLines.ledgerAccount']);
        });

        $this->logAudit('ledger.journal_entry.post', [
            'entry_id' => $entry->id,
            'company_id' => $entry->company_id,
            'description' => $entry->description,
        ], auth()->user(), $entry->company_id, result: ['posted_at' => $result->posted_at]);

        return $result;
    }

    public function voidJournalEntry(JournalEntry $entry, string $reason): array
    {
        $results = DB::transaction(function () use ($entry, $reason) {
            if (! $entry->canBeVoided()) {
                throw new \InvalidArgumentException('Journal entry cannot be voided');
            }

            // 1. Create the reversing entry by swapping debits and credits
            $reversingLines = $entry->journalLines->map(function (JournalLine $line) {
                return [
                    'account_id' => $line->ledger_account_id,
                    'debit_amount' => $line->credit_amount,
                    'credit_amount' => $line->debit_amount,
                    'description' => 'Reversal of J.E. '.$entry->reference,
                ];
            })->all();

            $reversingEntry = $this->createJournalEntry($entry->company, 'Reversal for entry: '.$entry->description, $reversingLines, null, now()->toDateString(), JournalEntry::class, $entry->id);
            $this->postJournalEntry($reversingEntry);

            // 2. Void the original entry using the state machine
            $entry->stateMachine()->transitionTo('void', ['reason' => $reason, 'reversing_entry_id' => $reversingEntry->id]);

            return ['original' => $entry->fresh(), 'reversing' => $reversingEntry];
        });

        $this->logAudit('ledger.journal_entry.void', [
            'entry_id' => $entry->id,
            'company_id' => $entry->company_id,
            'reason' => $reason,
        ], auth()->user(), $entry->company_id, result: ['voided_at' => $results['original']->metadata['voided_at'], 'reversing_entry_id' => $results['reversing']->id]);

        return $results;
    }

    public function getAccountBalance(LedgerAccount $account, ?string $date = null): array
    {
        $query = $account->journalLines()
            ->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'));

        if ($date) {
            $query->whereHas('journalEntry', fn ($q) => $q->where('date', '<=', $date));
        }

        $totalDebit = $query->sum('debit_amount');
        $totalCredit = $query->sum('credit_amount');

        $balance = $account->normal_balance === 'debit'
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;

        return [
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => $balance,
            'balance_type' => $balance >= 0 ? $account->normal_balance : ($account->normal_balance === 'debit' ? 'credit' : 'debit'),
        ];
    }

    private function validateJournalLines(Company $company, array $lines): void
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('Journal entry must have at least 2 lines');
        }

        $accountIds = array_column($lines, 'account_id');
        $dbAccounts = LedgerAccount::where('company_id', $company->id)
            ->whereIn('id', $accountIds)
            ->where('active', true)
            ->pluck('id')
            ->flip();

        // Use the company's base currency for accurate calculations
        $currencyCode = $company->base_currency ?? 'USD';

        $totalDebit = Money::of(0, $currencyCode);
        $totalCredit = Money::of(0, $currencyCode);

        foreach ($lines as $index => $line) {
            if (! isset($line['account_id']) || ! isset($line['debit_amount']) || ! isset($line['credit_amount'])) {
                throw new \InvalidArgumentException("Line {$index} is missing required fields");
            }

            if (! isset($dbAccounts[$line['account_id']])) {
                throw new \InvalidArgumentException("Invalid account ID: {$line['account_id']}");
            }

            if ($line['debit_amount'] > 0 && $line['credit_amount'] > 0) {
                throw new \InvalidArgumentException("Line {$index} cannot have both debit and credit amounts");
            }

            // Use Money object for precise calculations
            $totalDebit = $totalDebit->plus(Money::of($line['debit_amount'], $currencyCode));
            $totalCredit = $totalCredit->plus(Money::of($line['credit_amount'], $currencyCode));
        }

        if (! $totalDebit->isEqualTo($totalCredit)) {
            throw new UnbalancedJournalException('Journal entry must balance (debits must equal credits)');
        }
    }

    public function createLedgerAccount(
        Company $company,
        string $name,
        string $code,
        string $type,
        string $normalBalance,
        ?string $description = null,
        ?string $parentAccountId = null,
        bool $active = true
    ): LedgerAccount {
        $result = DB::transaction(function () use ($company, $name, $code, $type, $normalBalance, $description, $parentAccountId, $active) {
            $account = new LedgerAccount([
                'company_id' => $company->id,
                'name' => $name,
                'code' => $code,
                'type' => $type,
                'normal_balance' => $normalBalance,
                'description' => $description,
                'parent_account_id' => $parentAccountId,
                'active' => $active,
            ]);

            $account->save();

            return $account;
        });

        $this->logAudit('ledger.account.create', [
            'company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'parent_account_id' => $parentAccountId,
            'active' => $active,
        ], auth()->user(), $company->id, result: ['account_id' => $result->id]);

        return $result;
    }

    public function updateLedgerAccount(LedgerAccount $account, array $data): LedgerAccount
    {
        $oldData = $account->getAttributes();

        $result = DB::transaction(function () use ($account, $data) {
            $account->update($data);

            return $account->fresh();
        });

        $this->logAudit('ledger.account.update', [
            'account_id' => $account->id,
            'company_id' => $account->company_id,
            'old_data' => $oldData,
            'new_data' => $data,
        ], auth()->user(), $account->company_id, result: ['updated_at' => $result->updated_at]);

        return $result;
    }

    public function deleteLedgerAccount(LedgerAccount $account, ?string $reason = null): void
    {
        $accountData = $account->getAttributes();

        DB::transaction(function () use ($account) {
            $account->delete();
        });

        $this->logAudit('ledger.account.delete', [
            'account_id' => $account->id,
            'company_id' => $account->company_id,
            'name' => $account->name,
            'code' => $account->code,
            'reason' => $reason,
        ], auth()->user(), $account->company_id);
    }

    private function createJournalLines(JournalEntry $entry, array $lines): void
    {
        $lineNumber = 1;

        foreach ($lines as $line) {
            JournalLine::create([
                'company_id' => $entry->company_id,
                'journal_entry_id' => $entry->id,
                'ledger_account_id' => $line['account_id'],
                'description' => $line['description'] ?? null,
                'debit_amount' => $line['debit_amount'],
                'credit_amount' => $line['credit_amount'],
                'line_number' => $lineNumber++,
                'metadata' => $line['metadata'] ?? null,
            ]);
        }
    }
}
