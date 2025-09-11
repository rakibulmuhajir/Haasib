<?php

namespace App\Services;

use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerService
{
    public function createJournalEntry(
        Company $company,
        string $description,
        array $lines,
        ?string $reference = null,
        ?string $date = null,
        ?string $sourceType = null,
        ?string $sourceId = null
    ): JournalEntry {
        return DB::transaction(function () use ($company, $description, $lines, $reference, $date, $sourceType, $sourceId) {
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
    }

    public function postJournalEntry(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry) {
            if (!$entry->canBePosted()) {
                throw new \InvalidArgumentException('Journal entry cannot be posted');
            }

            if (!$entry->isBalanced()) {
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
    }

    public function voidJournalEntry(JournalEntry $entry, string $reason): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reason) {
            if (!$entry->canBeVoided()) {
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
    }

    public function getAccountBalance(LedgerAccount $account, ?string $date = null): array
    {
        $query = $account->journalLines()
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted'));

        if ($date) {
            $query->whereHas('journalEntry', fn($q) => $q->where('date', '<=', $date));
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
            if (!isset($line['account_id']) || !isset($line['debit_amount']) || !isset($line['credit_amount'])) {
                throw new \InvalidArgumentException("Line {$index} is missing required fields");
            }

            if (!isset($dbAccounts[$line['account_id']])) {
                throw new \InvalidArgumentException("Invalid account ID: {$line['account_id']}");
            }

            if ($line['debit_amount'] > 0 && $line['credit_amount'] > 0) {
                throw new \InvalidArgumentException("Line {$index} cannot have both debit and credit amounts");
            }

            // Use Money object for precise calculations
            $totalDebit = $totalDebit->plus(Money::of($line['debit_amount'], 'USD'));
            $totalCredit = $totalCredit->plus(Money::of($line['credit_amount'], 'USD'));
        }

        if (!$totalDebit->isEqualTo($totalCredit)) {
            throw new \InvalidArgumentException('Journal entry must balance (debits must equal credits)');
        }
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
