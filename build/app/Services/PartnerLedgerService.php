<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\DocumentDateLock;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one path for partner money. Every partner owns two equity accounts, "Capital - name"
 * (credit normal) and "Drawings - name" (an equity contra, debit normal); their balance is
 * capital - drawings, read from the ledger, never from a counter.
 *
 *  - invest():   Dr cash/bank, Cr Capital - name
 *  - withdraw(): Dr Drawings - name, Cr cash/bank
 *
 * The Daily Close enters partner deposits and withdrawals too, but posts them in its own
 * journal: it uses accountsFor() for the two accounts and recordCloseMovement()/linkClose()
 * for the PartnerTransactions, so the same totals, drawing-limit check and statement apply.
 * Going over the drawing limit warns (limitWarning()); it never blocks.
 */
class PartnerLedgerService
{
    private const POSTED = ['posted', 'locked'];

    private const CAPITAL_CODES = [3300, 3499];

    private const DRAWINGS_CODES = [3500, 3699];

    /** @return array{capital: Account, drawings: Account} */
    public function accountsFor(Partner $partner): array
    {
        $capital = $partner->capital_account_id ? Account::find($partner->capital_account_id) : null;
        $drawings = $partner->drawing_account_id ? Account::find($partner->drawing_account_id) : null;

        // An account the partner points at is theirs only while it is a live equity account;
        // a shared one picked on the old form (e.g. Partner Drawings 3200) is replaced.
        if ($capital && ! $capital->is_active) {
            $capital = null;
        }
        if ($drawings && (! $drawings->is_active || ! $this->isOwnDrawings($partner, $drawings))) {
            $drawings = null;
        }
        if ($capital && $drawings) {
            return ['capital' => $capital, 'drawings' => $drawings];
        }

        return DB::transaction(function () use ($partner, $capital, $drawings) {
            $fresh = Partner::lockForUpdate()->find($partner->id);
            $capital ??= $this->createAccount($fresh, 'Capital', self::CAPITAL_CODES, 'credit', false);
            $drawings ??= $this->createAccount($fresh, 'Drawings', self::DRAWINGS_CODES, 'debit', true);
            $fresh->forceFill(['capital_account_id' => $capital->id, 'drawing_account_id' => $drawings->id])->saveQuietly();
            $partner->forceFill(['capital_account_id' => $capital->id, 'drawing_account_id' => $drawings->id])->syncOriginal();

            return ['capital' => $capital, 'drawings' => $drawings];
        });
    }

    /** The old form let a partner point at any equity account; only a contra equity one nobody else uses is a drawings account. */
    private function isOwnDrawings(Partner $partner, Account $account): bool
    {
        if ($account->type !== 'equity' || $account->normal_balance !== 'debit') {
            return false;
        }

        return Partner::where('company_id', $partner->company_id)->where('id', '!=', $partner->id)
            ->where('drawing_account_id', $account->id)->doesntExist();
    }

    private function createAccount(Partner $partner, string $label, array $range, string $normal, bool $contra): Account
    {
        $used = Account::withTrashed()->where('company_id', $partner->company_id)->pluck('code')->flip();
        for ($code = $range[0]; $code <= $range[1]; $code++) {
            if (! $used->has((string) $code)) {
                return Account::create([
                    'company_id' => $partner->company_id,
                    'code' => (string) $code,
                    'name' => "{$label} \u{2013} {$partner->name}",
                    'type' => 'equity',
                    'subtype' => 'equity',
                    'normal_balance' => $normal,
                    'is_contra' => $contra,
                    'is_active' => true,
                    'is_system' => false,
                    'description' => $label === 'Capital' ? "Money {$partner->name} put in, plus profit shares" : "Money {$partner->name} took out",
                ]);
            }
        }

        throw new \RuntimeException("No free {$label} account code left for {$partner->name}.");
    }

    /** Capital less Drawings from the ledger, as of a date (null = all time). */
    public function balance(Partner $partner, ?string $asOf = null): float
    {
        if (! $partner->capital_account_id || ! $partner->drawing_account_id) {
            return 0.0;
        }
        // A partner loaded with only some columns may lack company_id; its account knows it.
        $companyId = $partner->company_id
            ?? DB::table('acct.accounts')->where('id', $partner->capital_account_id)->value('company_id');

        return round(
            $this->accountNet($companyId, $partner->capital_account_id, 'credit', $asOf)
            - $this->accountNet($companyId, $partner->drawing_account_id, 'debit', $asOf),
            2
        );
    }

    /** One account's balance on its own normal side, from posted journals. */
    public function accountNet(string $companyId, string $accountId, string $normal, ?string $asOf = null): float
    {
        $row = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('t.company_id', $companyId)
            ->where('je.account_id', $accountId)
            ->whereIn('t.status', self::POSTED)
            ->when($asOf, fn ($q) => $q->whereDate('t.transaction_date', '<=', $asOf))
            ->selectRaw('COALESCE(SUM(je.debit_amount),0) as d, COALESCE(SUM(je.credit_amount),0) as c')
            ->first();

        return round($normal === 'credit' ? (float) $row->c - (float) $row->d : (float) $row->d - (float) $row->c, 2);
    }

    /**
     * Put money in: Dr cash/bank, Cr the partner's Capital account.
     *
     * @return array{transaction: PartnerTransaction, gl: Transaction, warning: ?string}
     */
    public function invest(Partner $partner, float $amount, string $date, string $accountId, ?string $description = null, string $source = 'page', ?string $reference = null, ?string $userId = null): array
    {
        return $this->move($partner, 'investment', $amount, $date, $accountId, $description, $source, $reference, $userId);
    }

    /**
     * Take money out: Dr the partner's Drawings account, Cr cash/bank. Over the drawing limit it
     * still posts and returns a warning.
     *
     * @return array{transaction: PartnerTransaction, gl: Transaction, warning: ?string}
     */
    public function withdraw(Partner $partner, float $amount, string $date, string $accountId, ?string $description = null, string $source = 'page', ?string $reference = null, ?string $userId = null): array
    {
        return $this->move($partner, 'withdrawal', $amount, $date, $accountId, $description, $source, $reference, $userId);
    }

    private function move(Partner $partner, string $type, float $amount, string $date, string $accountId, ?string $description, string $source, ?string $reference, ?string $userId): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount above zero.']);
        }
        $company = Company::findOrFail($partner->company_id);
        $cash = Account::where('company_id', $company->id)->where('is_active', true)->whereNull('deleted_at')
            ->whereIn('subtype', ['cash', 'bank'])->find($accountId);
        if (! $cash) {
            throw ValidationException::withMessages(['account_id' => 'Pick a cash or bank account.']);
        }
        app(DocumentDateLock::class)->assertOpen($company->id, $date, $type === 'investment' ? 'This investment' : 'This withdrawal');

        return AccountingWriteTransaction::run(function () use ($partner, $type, $amount, $date, $cash, $company, $description, $source, $reference, $userId) {
            $accounts = $this->accountsFor($partner);
            $invest = $type === 'investment';
            $own = $invest ? $accounts['capital'] : $accounts['drawings'];
            $text = $description ?: ($invest ? 'Capital investment' : 'Partner withdrawal');
            $currency = $company->base_currency ?: 'PKR';

            $gl = app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_type' => $invest ? 'partner_investment' : 'partner_withdrawal',
                'date' => $date,
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => "{$text} - {$partner->name}",
                'reference_type' => 'auth.partners',
                'reference_id' => $partner->id,
                'metadata' => ['partner_id' => $partner->id, 'source' => $source],
            ], [
                ['account_id' => $invest ? $cash->id : $own->id, 'type' => 'debit', 'amount' => $amount, 'description' => $text],
                ['account_id' => $invest ? $own->id : $cash->id, 'type' => 'credit', 'amount' => $amount, 'description' => $text],
            ]);

            $record = PartnerTransaction::create([
                'company_id' => $company->id,
                'partner_id' => $partner->id,
                'transaction_date' => $date,
                'transaction_type' => $type,
                'amount' => $amount,
                'description' => $text,
                'reference' => $reference,
                'payment_method' => $cash->subtype === 'bank' ? 'bank_transfer' : 'cash',
                'bank_account_id' => $cash->id,
                'gl_transaction_id' => $gl->id,
                'journal_entry_id' => $gl->journalEntries()->where('account_id', $own->id)->value('id'),
                'recorded_by_user_id' => $userId ?? auth()->id(),
            ]);
            $partner->refreshTotals();

            return ['transaction' => $record, 'gl' => $gl, 'warning' => $invest ? null : $this->limitWarning($partner, $date)];
        });
    }

    // Daily Close entry points

    /** A close's partner row: the PartnerTransaction now, linked to the close's journal by linkClose() once it posts. */
    public function recordCloseMovement(Partner $partner, string $type, float $amount, string $date, ?string $userId): PartnerTransaction
    {
        $record = PartnerTransaction::create([
            'company_id' => $partner->company_id,
            'partner_id' => $partner->id,
            'transaction_date' => $date,
            'transaction_type' => $type,
            'amount' => round($amount, 2),
            'description' => $type === 'investment' ? 'Daily deposit' : 'Daily withdrawal',
            'payment_method' => 'cash',
            'recorded_by_user_id' => $userId,
        ]);
        $partner->refreshTotals();

        return $record;
    }

    /**
     * Point each of the close's PartnerTransactions at the close journal and at its partner's
     * own Capital or Drawings line, then bring the totals up to date.
     *
     * @param iterable<PartnerTransaction> $records
     */
    public function linkClose(iterable $records, Transaction $close): void
    {
        $partners = [];
        foreach ($records as $record) {
            $partner = $partners[$record->partner_id] ??= Partner::find($record->partner_id);
            $accounts = $this->accountsFor($partner);
            $own = $record->transaction_type === 'investment' ? $accounts['capital'] : $accounts['drawings'];
            $record->update([
                'gl_transaction_id' => $close->id,
                'journal_entry_id' => $close->journalEntries()->where('account_id', $own->id)->value('id'),
            ]);
        }
        foreach ($partners as $partner) {
            $partner->refreshTotals();
        }
    }

    /** Undo a close's partner rows (Edit day / reopen); totals follow. */
    public function undoClose(string $companyId, string $closeId, iterable $journalEntryIds = []): void
    {
        $entryIds = collect($journalEntryIds)->all();
        $rows = PartnerTransaction::where('company_id', $companyId)
            ->where(fn ($q) => $q->where('gl_transaction_id', $closeId)->orWhereIn('journal_entry_id', $entryIds))
            ->get();
        $partnerIds = $rows->pluck('partner_id')->unique();
        foreach ($rows as $row) {
            $row->delete();
        }
        foreach (Partner::whereIn('id', $partnerIds)->get() as $partner) {
            $partner->refreshTotals();
        }
    }

    // Drawing limit

    /** "Over Ali's monthly drawing limit by 5,000", or null when within it (or no limit). */
    public function limitWarning(Partner $partner, string $date): ?string
    {
        if ($partner->drawing_limit_period === 'none' || $partner->drawing_limit_amount === null) {
            return null;
        }
        $over = round($partner->withdrawnThisPeriod($date) - (float) $partner->drawing_limit_amount, 2);
        if ($over <= 0) {
            return null;
        }
        $period = $partner->drawing_limit_period === 'yearly' ? 'yearly' : 'monthly';
        $figure = rtrim(rtrim(number_format($over, 2), '0'), '.');

        return "Over {$partner->name}'s {$period} drawing limit by {$figure}";
    }
}
