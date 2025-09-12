<?php

namespace App\Services;

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
        $result = DB::transaction(function () use ($entry) {
            if (! $entry->canBePosted()) {
                throw new \InvalidArgumentException('Journal entry cannot be posted');
            }

            if (! $entry->isBalanced()) {
                throw new \InvalidArgumentException('Journal entry is not balanced');
            }

            $entry->status = 'posted';
            $entry->posted_at = now();
            $entry->posted_by_user_id = auth()->id();
            $entry->save();

            Log::info('Journal entry posted', [
                'entry_id' => $entry->id,
                'company_id' => $entry->company_id,
                'user_id' => $entry->posted_by_user_id,
            ]);

            return $entry->fresh();
        });

        $this->logAudit('ledger.journal_entry.post', [
            'entry_id' => $entry->id,
            'company_id' => $entry->company_id,
            'description' => $entry->description,
        ], auth()->user(), $entry->company_id, result: ['posted_at' => $result->posted_at]);

        return $result;
    }

    public function voidJournalEntry(JournalEntry $entry, string $reason): JournalEntry
    {
        $result = DB::transaction(function () use ($entry, $reason) {
            if (! $entry->canBeVoided()) {
                throw new \InvalidArgumentException('Journal entry cannot be voided');
            }

            $entry->status = 'void';
            $entry->metadata = array_merge($entry->metadata ?? [], [
                'void_reason' => $reason,
                'voided_at' => now()->toISOString(),
                'voided_by_user_id' => auth()->id(),
            ]);
            $entry->save();

            Log::info('Journal entry voided', [
                'entry_id' => $entry->id,
                'company_id' => $entry->company_id,
                'reason' => $reason,
                'user_id' => auth()->id(),
            ]);

            return $entry->fresh();
        });

        $this->logAudit('ledger.journal_entry.void', [
            'entry_id' => $entry->id,
            'company_id' => $entry->company_id,
            'reason' => $reason,
            'description' => $entry->description,
        ], auth()->user(), $entry->company_id, result: ['voided_at' => $result->metadata['voided_at']]);

        return $result;
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

        $totalDebit = Money::of(0, 'USD'); // Assuming USD, replace with company currency
        $totalCredit = Money::of(0, 'USD');

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
            $totalDebit = $totalDebit->plus(Money::of($line['debit_amount'], 'USD'));
            $totalCredit = $totalCredit->plus(Money::of($line['credit_amount'], 'USD'));
        }

        if (! $totalDebit->isEqualTo($totalCredit)) {
            throw new \InvalidArgumentException('Journal entry must balance (debits must equal credits)');
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
