<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\BankReconciliation;
use App\Services\AccountingWriteTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reconciling a bank account against its statement, from the books themselves: the lines are
 * the journal entries on the bank's ledger account (daily close deposits and withdrawals, card
 * settlements, supplier payments, transfers ...). Each line is ticked as cleared once, ever.
 *
 * Cleared balance = every line cleared in earlier completed reconciliations + the lines ticked
 * in this one. It must equal the statement's closing balance before the reconciliation can be
 * completed. A statement imported from CSV is matched to the book lines automatically (same
 * amount, nearest date within a few days); what is on the statement but not in the books --
 * bank charges, profit, returned cheques -- can be added as an entry from the same screen.
 */
class BankReconciliationService
{
    /** A bank can clear a deposit or cheque some days after the books record it. */
    private const MATCH_DAYS = 5;

    /** @return array{lines:array<int,array<string,mixed>>,statement:array<int,array<string,mixed>>,summary:array<string,float|int>} */
    public function view(BankReconciliation $recon): array
    {
        $bank = $recon->bankAccount;
        $statementDate = $recon->statement_date->toDateString();
        $ticked = $this->ticked($recon);

        $lines = $this->bookLines($recon)->map(fn ($l) => [
            'id' => $l->id,
            'date' => substr((string) $l->transaction_date, 0, 10),
            'reference' => $l->transaction_number,
            'description' => $l->line_description ?: ($l->description ?: 'Journal entry'),
            'amount' => round((float) $l->debit_amount - (float) $l->credit_amount, 2),
            'cleared' => isset($ticked[$l->id]),
            'after_statement' => substr((string) $l->transaction_date, 0, 10) > $statementDate,
            'link' => app(AccountStatementService::class)->link($l->reference_type, $l->reference_id, $l->transaction_id),
        ])->values()->all();

        $statement = DB::table('acct.bank_statement_lines')->where('reconciliation_id', $recon->id)
            ->orderBy('line_number')->get()
            ->map(fn ($s) => [
                'id' => $s->id, 'date' => substr((string) $s->line_date, 0, 10), 'description' => $s->description,
                'reference' => $s->reference, 'amount' => (float) $s->amount, 'balance' => $s->balance !== null ? (float) $s->balance : null,
                'journal_entry_id' => $s->journal_entry_id,
            ])->all();

        $clearedBefore = $this->clearedBefore($recon);
        $clearedNow = round(array_sum($ticked), 2);
        $cleared = round($clearedBefore + $clearedNow, 2);
        $book = round((float) DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('t.company_id', $recon->company_id)
            ->where('je.account_id', $bank->gl_account_id)
            ->whereIn('t.status', ['posted', 'locked'])
            ->whereNull('t.deleted_at')
            ->whereDate('t.transaction_date', '<=', $statementDate)
            ->sum(DB::raw('je.debit_amount - je.credit_amount')), 2);

        return [
            'lines' => $lines,
            'statement' => $statement,
            'summary' => [
                'statement_balance' => (float) $recon->statement_ending_balance,
                'cleared_before' => $clearedBefore,
                'cleared_now' => $clearedNow,
                'cleared_balance' => $cleared,
                'difference' => round((float) $recon->statement_ending_balance - $cleared, 2),
                'book_balance' => $book,
                'unmatched_statement' => count(array_filter($statement, fn ($s) => ! $s['journal_entry_id'])),
            ],
        ];
    }

    public function toggle(BankReconciliation $recon, string $journalEntryId, bool $cleared): void
    {
        $this->assertOpen($recon);
        $line = $this->bookLines($recon)->firstWhere('id', $journalEntryId)
            ?? throw ValidationException::withMessages(['line' => 'That entry is not on this bank or is already cleared.']);

        if ($cleared) {
            DB::table('acct.bank_reconciliation_items')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'company_id' => $recon->company_id, 'reconciliation_id' => $recon->id,
                'journal_entry_id' => $journalEntryId,
                'amount' => round((float) $line->debit_amount - (float) $line->credit_amount, 2),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            DB::table('acct.bank_reconciliation_items')->where('reconciliation_id', $recon->id)->where('journal_entry_id', $journalEntryId)->delete();
            DB::table('acct.bank_statement_lines')->where('reconciliation_id', $recon->id)->where('journal_entry_id', $journalEntryId)->update(['journal_entry_id' => null]);
        }
        $this->refreshHeader($recon);
    }

    /** Replaces this reconciliation's statement with the file's lines and matches them. @return int lines matched */
    public function importStatement(BankReconciliation $recon, string $path): int
    {
        $this->assertOpen($recon);
        $lines = app(BankStatementCsvParser::class)->parse($path);

        return DB::transaction(function () use ($recon, $lines) {
            // Matches from an earlier import of this statement go with it.
            $old = DB::table('acct.bank_statement_lines')->where('reconciliation_id', $recon->id)->whereNotNull('journal_entry_id')->pluck('journal_entry_id');
            DB::table('acct.bank_reconciliation_items')->where('reconciliation_id', $recon->id)->whereIn('journal_entry_id', $old)->delete();
            DB::table('acct.bank_statement_lines')->where('reconciliation_id', $recon->id)->delete();

            $rows = [];
            foreach ($lines as $i => $line) {
                $rows[] = $line + ['id' => (string) Str::uuid(), 'company_id' => $recon->company_id, 'reconciliation_id' => $recon->id,
                    'line_number' => $i + 1, 'journal_entry_id' => null, 'created_at' => now(), 'updated_at' => now()];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('acct.bank_statement_lines')->insert($chunk);
            }

            return $this->autoMatch($recon);
        });
    }

    /** Pairs each unmatched statement line with an unticked book line of the same amount, nearest date. */
    public function autoMatch(BankReconciliation $recon): int
    {
        $ticked = $this->ticked($recon);
        $available = $this->bookLines($recon)->reject(fn ($l) => isset($ticked[$l->id]))->values();
        $statement = DB::table('acct.bank_statement_lines')->where('reconciliation_id', $recon->id)->whereNull('journal_entry_id')->orderBy('line_number')->get();

        $matched = 0;
        foreach ($statement as $s) {
            $date = Carbon::parse($s->line_date);
            $best = $available
                ->filter(fn ($l) => abs(round((float) $l->debit_amount - (float) $l->credit_amount, 2) - (float) $s->amount) < 0.005)
                ->map(fn ($l) => ['line' => $l, 'days' => abs(Carbon::parse($l->transaction_date)->diffInDays($date, false))])
                ->filter(fn ($c) => $c['days'] <= self::MATCH_DAYS)
                ->sortBy('days')
                ->first();
            if (! $best) {
                continue;
            }
            $this->link($recon, $s->id, $best['line']);
            $available = $available->reject(fn ($l) => $l->id === $best['line']->id)->values();
            $matched++;
        }
        $this->refreshHeader($recon);

        return $matched;
    }

    /**
     * Books a statement line that is missing from the books (bank charge, profit, returned
     * cheque) against the chosen account, and clears it.
     */
    public function addEntry(BankReconciliation $recon, string $statementLineId, string $accountId, ?string $userId): void
    {
        $this->assertOpen($recon);
        $line = DB::table('acct.bank_statement_lines')->where('reconciliation_id', $recon->id)->where('id', $statementLineId)->first()
            ?? throw ValidationException::withMessages(['line' => 'Statement line not found.']);
        if ($line->journal_entry_id) {
            throw ValidationException::withMessages(['line' => 'That statement line is already matched.']);
        }
        $bank = $recon->bankAccount;
        $amount = abs((float) $line->amount);
        $in = (float) $line->amount > 0;
        $text = $line->description ?: ($in ? 'Bank credit' : 'Bank debit');

        AccountingWriteTransaction::run(function () use ($recon, $bank, $line, $accountId, $amount, $in, $text) {
            $transaction = app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $recon->company_id,
                'transaction_type' => 'bank_adjustment',
                'date' => substr((string) $line->line_date, 0, 10),
                'currency' => $bank->currency ?: 'PKR',
                'description' => "{$bank->account_name}: {$text}",
                'reference_type' => 'acct.bank_statement_lines',
                'reference_id' => $line->id,
            ], [
                ['account_id' => $in ? $bank->gl_account_id : $accountId, 'type' => 'debit', 'amount' => $amount, 'description' => $text],
                ['account_id' => $in ? $accountId : $bank->gl_account_id, 'type' => 'credit', 'amount' => $amount, 'description' => $text],
            ]);
            $bankLine = DB::table('acct.journal_entries')->where('transaction_id', $transaction->id)->where('account_id', $bank->gl_account_id)->first();
            $this->link($recon, $line->id, $bankLine);
        });
        $this->refreshHeader($recon);
    }

    public function complete(BankReconciliation $recon, ?string $userId): void
    {
        $this->assertOpen($recon);
        $summary = $this->view($recon)['summary'];
        if (abs($summary['difference']) >= 0.01) {
            throw ValidationException::withMessages(['difference' => 'The cleared balance must equal the statement balance.']);
        }
        $recon->update([
            'status' => 'completed', 'difference' => 0, 'reconciled_balance' => $summary['cleared_balance'],
            'book_balance' => $summary['book_balance'], 'completed_at' => now(), 'completed_by_user_id' => $userId,
        ]);
    }

    public function discard(BankReconciliation $recon): void
    {
        $this->assertOpen($recon);
        $recon->delete(); // cleared ticks and statement lines go with it (cascade)
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * The bank's book lines that are not cleared by another reconciliation: everything up to the
     * statement date, plus a week after it (a deposit the books dated later can still be on it).
     */
    private function bookLines(BankReconciliation $recon)
    {
        $until = $recon->statement_date->copy()->addDays(7)->toDateString();

        return DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->leftJoin('acct.bank_reconciliation_items as i', 'i.journal_entry_id', '=', 'je.id')
            ->where('t.company_id', $recon->company_id)
            ->where('je.account_id', $recon->bankAccount->gl_account_id)
            ->whereIn('t.status', ['posted', 'locked'])
            ->whereNull('t.deleted_at')
            ->whereDate('t.transaction_date', '<=', $until)
            ->where(fn ($q) => $q->whereNull('i.id')->orWhere('i.reconciliation_id', $recon->id))
            ->orderBy('t.transaction_date')
            ->orderByRaw('CASE WHEN je.debit_amount > 0 THEN 0 ELSE 1 END')
            ->orderBy('t.created_at')
            ->get(['je.id', 'je.debit_amount', 'je.credit_amount', 'je.description as line_description', 't.id as transaction_id',
                't.transaction_date', 't.transaction_number', 't.description', 't.reference_type', 't.reference_id']);
    }

    /** @return array<string,float> journal entry id => signed amount */
    private function ticked(BankReconciliation $recon): array
    {
        return DB::table('acct.bank_reconciliation_items')->where('reconciliation_id', $recon->id)
            ->pluck('amount', 'journal_entry_id')->map(fn ($a) => (float) $a)->all();
    }

    private function clearedBefore(BankReconciliation $recon): float
    {
        return round((float) DB::table('acct.bank_reconciliation_items as i')
            ->join('acct.bank_reconciliations as r', 'r.id', '=', 'i.reconciliation_id')
            ->where('r.bank_account_id', $recon->bank_account_id)
            ->where('r.status', 'completed')
            ->sum('i.amount'), 2);
    }

    private function link(BankReconciliation $recon, string $statementLineId, object $bookLine): void
    {
        DB::table('acct.bank_statement_lines')->where('id', $statementLineId)->update(['journal_entry_id' => $bookLine->id, 'updated_at' => now()]);
        DB::table('acct.bank_reconciliation_items')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'company_id' => $recon->company_id, 'reconciliation_id' => $recon->id,
            'journal_entry_id' => $bookLine->id,
            'amount' => round((float) $bookLine->debit_amount - (float) $bookLine->credit_amount, 2),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function refreshHeader(BankReconciliation $recon): void
    {
        $cleared = round($this->clearedBefore($recon) + array_sum($this->ticked($recon)), 2);
        $recon->update(['reconciled_balance' => $cleared, 'difference' => round((float) $recon->statement_ending_balance - $cleared, 2)]);
    }

    private function assertOpen(BankReconciliation $recon): void
    {
        if ($recon->status !== 'in_progress') {
            throw ValidationException::withMessages(['status' => 'This reconciliation is already completed.']);
        }
    }
}
