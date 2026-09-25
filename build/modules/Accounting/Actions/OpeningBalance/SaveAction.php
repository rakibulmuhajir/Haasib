<?php

namespace App\Modules\Accounting\Actions\OpeningBalance;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\InvoiceLineItem;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\OpeningBalanceAccounts;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\PayrollPostingService;
use App\Services\CommandBus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveAction implements PaletteAction
{
    public const JOURNAL_TYPE = 'opening_balance';
    public const REFERENCE_TYPE = 'acct.opening_balances';
    public const MARK = 'OPENING';

    public function __construct(
        private readonly GlPostingService $posting,
        private readonly OpeningBalanceAccounts $accounts,
        private readonly PostingService $postingService,
    ) {}

    public function rules(): array
    {
        return [
            'as_of_date' => 'required|date',
            // The rows the caller's form was loaded with. The page sends them so a save can
            // tell its own edits from lines changed elsewhere since (see mergeWithCurrent).
            'loaded' => 'nullable|array',
            'cash' => 'nullable|array',
            'cash.amount' => 'nullable|numeric|min:0',
            'banks' => 'nullable|array',
            'banks.*.account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'banks.*.amount' => 'required|numeric|min:0',
            'credit_customers' => 'nullable|array',
            'credit_customers.*.customer_id' => ['required', 'uuid', Rule::exists(Customer::class, 'id')],
            'credit_customers.*.amount' => 'required|numeric|gt:0',
            'employees' => 'nullable|array',
            'employees.*.employee_id' => ['required', 'uuid', Rule::exists(Employee::class, 'id')],
            'employees.*.amount' => 'required|numeric|gt:0',
            'salaries_owed' => 'nullable|array',
            'salaries_owed.*.employee_id' => ['required', 'uuid', Rule::exists(Employee::class, 'id')],
            'salaries_owed.*.amount' => 'required|numeric|gt:0',
            'amanat' => 'nullable|array',
            'amanat.*.customer_id' => ['required', 'uuid', Rule::exists(Customer::class, 'id')],
            'amanat.*.amount' => 'required|numeric|gt:0',
            'suppliers' => 'nullable|array',
            'suppliers.*.vendor_id' => ['required', 'uuid', Rule::exists(Vendor::class, 'id')],
            'suppliers.*.amount' => 'required|numeric|gt:0',
            'partners' => 'nullable|array',
            'partners.*.partner_id' => ['required', 'uuid', Rule::exists(Partner::class, 'id')],
            'partners.*.amount' => 'required|numeric|gt:0',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::OPENING_BALANCE_MANAGE;
    }

    public function handle(array $params): array
    {
        return \App\Services\AccountingWriteTransaction::run(fn () => $this->execute($params));
    }

    private function execute(array $params): array
    {
        $contextCompany = CompanyContext::requireCompany();
        $asOf = $params['as_of_date'];

        return \App\Services\AccountingWriteTransaction::run(function () use ($contextCompany, $params, $asOf) {
            // Lock order (do not invert): (1) advisory 'opening:'||company_id lock --
            // EXCLUSIVE here, SHARED for ordinary writers via acct.protect_locked_opening
            // -- taken as the very first statement, before any reads or row locks;
            // (2) document row locks (company row below, invoices/bills created further
            // down). See the lock-order comment in the protect_locked_openings migration.
            DB::selectOne('select pg_advisory_xact_lock(hashtext(?))', ['opening:'.$contextCompany->id]);
            // Lock the company row first so a concurrent save cannot read the same
            // stale settings and both reverse-then-repost against the same prior
            // generation, duplicating balances. Every read of "prior opening state"
            // below comes from this freshly-locked row, not from the (possibly
            // stale) instance the caller/context handed in.
            $company = Company::whereKey($contextCompany->id)->lockForUpdate()->firstOrFail();
            $priorOpening = $company->settings['opening_balances'] ?? [];

            $this->guardNotLocked($company);
            $params = $this->mergeWithCurrent($contextCompany, $company, $params);
            $this->guardDate($company->id, $asOf, $priorOpening);

            $this->reversePrevious($company, $priorOpening);

            $accounts = $this->accounts->resolve($company->id);
            $currency = strtoupper((string) ($company->base_currency ?: 'PKR'));

            $lines = []; // journal entries: ['account_id','type','amount','description']

            $this->addCashAndBankLines($company->id, $params, $accounts, $lines);
            $pending = [];   // closures run after the journal exists, receiving its per-line entry ids
            $this->addAmanatLines($company->id, $params, $accounts, $lines, $pending);
            $this->addEmployeeAdvanceLines($company->id, $params, $accounts, $asOf, $lines, $pending);
            $this->addSalariesOwedLines($company->id, $params, $currency, $asOf, $lines, $pending);
            $this->addPartnerLines($company->id, $params, $accounts, $asOf, $lines, $pending);

            [$journalId, $entryIdsByLine] = $this->postJournal($company->id, $currency, $asOf, $accounts['equity'], $lines);
            foreach ($pending as $create) {
                $create($entryIdsByLine, $journalId);
            }

            $invoiceIds = $this->createOpeningInvoices($company, $params, $accounts, $asOf, $currency);
            $billIds = $this->createOpeningBills($company, $params, $accounts, $asOf, $currency);

            // The previous generation is now deleted outright (reversePrevious), not
            // voided/reversed, so there is nothing left on disk for a future date guard or
            // view to need to exclude. These keys are kept (written empty) only so older
            // companies whose prior generation still has genuine retired_* entries from
            // before this change keep reading correctly via nonOpeningTransactions/ViewAction.
            $settings = $company->settings ?? [];
            $settings['opening_balances'] = [
                'as_of_date' => $asOf,
                'locked_at' => null,
                'locked_by_user_id' => null,
                'invoice_ids' => $invoiceIds,
                'bill_ids' => $billIds,
                'journal_id' => $journalId,
                'retired_invoice_ids' => [],
                'retired_bill_ids' => [],
                'retired_journal_ids' => [],
            ];
            $company->settings = $settings;
            $company->save();

            // Keep the context-bound Company instance (the one the caller/redirect
            // holds a reference to) in sync with what was just persisted, so code
            // elsewhere in this same request that reads CompanyContext::requireCompany()
            // sees the fresh settings without needing its own DB round trip.
            $contextCompany->settings = $settings;

            $this->syncStatementOpenings($company->id, $params, $accounts, $asOf);

            return [
                'message' => 'Opening balances saved as of '.$asOf.($this->keptFromElsewhere
                    ? " - kept {$this->keptFromElsewhere} ".($this->keptFromElsewhere === 1 ? 'line' : 'lines').' changed elsewhere since this page was opened'
                    : ''),
                'data' => ['journal_id' => $journalId, 'invoice_ids' => $invoiceIds, 'bill_ids' => $billIds],
            ];
        }); // retry on deadlock (40P01): this transaction takes document row locks
        // (company row above, invoices/bills created below) after the exclusive advisory
        // lock; retry is a backstop against the narrow window described in the lock-order
        // comment in the protect_locked_openings migration.
    }

    /**
     * Mirror the opening figures onto the bank-statement side.
     *
     * acct.company_bank_accounts.opening_balance is not a second copy of the ledger truth.
     * It is the statement-side baseline: acct.update_account_balance() adds the imported feed
     * rows to it to get current_balance, and BankReconciliationController uses it as a
     * reconciliation's starting balance. Reconciliation exists to compare that side against
     * the ledger, so neither number can be derived from the other — collapse them and the
     * feature has nothing left to compare.
     *
     * They do have to start equal, and nothing kept them that way. The bank account form
     * wrote the column and never posted a journal; this action posted the journal and never
     * touched the column. Owning both here is what makes one write leave the pair coherent.
     *
     * The trigger only recomputes current_balance when a feed row changes, so moving the
     * baseline has to recompute it too, or the balance stays stale until the next import.
     */
    private function syncStatementOpenings(string $companyId, array $params, array $accounts, string $asOf): void
    {
        $byGlAccount = [];

        if (! empty($accounts['cash'])) {
            $byGlAccount[$accounts['cash']] = (float) ($params['cash']['amount'] ?? 0);
        }

        foreach ($params['banks'] ?? [] as $bank) {
            $byGlAccount[$bank['account_id']] = (float) $bank['amount'];
        }

        $rows = DB::table('acct.company_bank_accounts')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->get(['id', 'gl_account_id', 'opening_balance']);

        foreach ($rows as $row) {
            // A generation replaces the whole set, so an account left out of this one opens
            // at zero. Without that, an account dropped from the form keeps the figure from
            // a previous save and its current_balance stays overstated for good.
            $amount = round((float) ($row->gl_account_id !== null
                ? ($byGlAccount[$row->gl_account_id] ?? 0)
                : 0), 2);

            if ($amount === round((float) $row->opening_balance, 2)) {
                continue;
            }

            $feed = (float) DB::table('acct.bank_transactions')
                ->where('bank_account_id', $row->id)
                ->whereNull('deleted_at')
                ->sum('amount');

            DB::table('acct.company_bank_accounts')
                ->where('id', $row->id)
                ->update([
                    'opening_balance' => $amount,
                    'opening_balance_date' => $amount > 0 ? $asOf : null,
                    'current_balance' => round($feed + $amount, 2),
                    'updated_at' => now(),
                ]);
        }
    }

    private function guardNotLocked($company): void
    {
        if (! empty(($company->settings['opening_balances'] ?? [])['locked_at'])) {
            throw ValidationException::withMessages(['as_of_date' => 'Opening balances are locked.']);
        }
    }

    private const PARTY_KEYS = [
        'banks' => 'account_id',
        'credit_customers' => 'customer_id',
        'employees' => 'employee_id',
        'salaries_owed' => 'employee_id',
        'amanat' => 'customer_id',
        'suppliers' => 'vendor_id',
        'partners' => 'partner_id',
    ];

    private int $keptFromElsewhere = 0;

    /**
     * A save replaces the whole opening position with what the page shows. Opening balances
     * are also set elsewhere - a bank account's form, Quick Add, Add Holder - so a page opened
     * before one of those would post its older copy and drop the change: four bank openings
     * were lost that way. The page sends the rows it was loaded with; each line is then
     * decided three ways:
     *  - changed on this page (differs from what it loaded): the page's figure wins;
     *  - not touched on this page: what is saved now wins, added, changed or removed elsewhere.
     * Callers that read fresh and send no 'loaded' (the one-line setters) are unaffected.
     */
    private function mergeWithCurrent(Company $contextCompany, Company $company, array $params): array
    {
        $loaded = $params['loaded'] ?? null;
        unset($params['loaded']);
        $this->keptFromElsewhere = 0;
        if (! is_array($loaded)) {
            return $params;
        }

        // What is saved now, read from the freshly locked row rather than a stale context copy.
        $contextCompany->settings = $company->settings;
        $current = OpeningSet::payloadFromView(
            app(CommandBus::class)->dispatch('opening_balance.view', [], Auth::user(), true)
        );

        $money = fn ($value) => round((float) ($value ?? 0), 2);

        $page = $money($params['cash']['amount'] ?? 0);
        $now = $money($current['cash']['amount'] ?? 0);
        if ($page === $money($loaded['cash']['amount'] ?? 0) && $now !== $page) {
            $params['cash'] = ['amount' => $now];
            $this->keptFromElsewhere++;
        }

        foreach (self::PARTY_KEYS as $section => $key) {
            $index = fn (array $rows) => collect($rows)
                ->filter(fn ($row) => ! empty($row[$key]))
                ->mapWithKeys(fn ($row) => [$row[$key] => $money($row['amount'] ?? 0)])
                ->all();
            $pageRows = $index($params[$section] ?? []);
            $loadedRows = $index($loaded[$section] ?? []);
            $nowRows = $index($current[$section] ?? []);

            $result = [];
            foreach (array_unique(array_merge(array_keys($pageRows), array_keys($loadedRows), array_keys($nowRows))) as $id) {
                $pageAmount = $pageRows[$id] ?? 0.0;
                $nowAmount = $nowRows[$id] ?? 0.0;
                if ($pageAmount === ($loadedRows[$id] ?? 0.0)) {
                    $amount = $nowAmount;
                    if ($nowAmount !== $pageAmount) {
                        $this->keptFromElsewhere++;
                    }
                } else {
                    $amount = $pageAmount;
                }
                if ($amount > 0) {
                    $result[] = [$key => $id, 'amount' => $amount];
                }
            }
            $params[$section] = $result;
        }

        return $params;
    }

    private function guardDate(string $companyId, string $asOf, array $opening): void
    {
        $earliest = self::nonOpeningTransactions($companyId, $opening)
            ->whereIn('status', ['posted', 'locked'])
            ->min('transaction_date');

        if ($earliest && $asOf >= substr((string) $earliest, 0, 10)) {
            throw ValidationException::withMessages([
                'as_of_date' => "Opening balances must be dated before the first posted transaction ({$earliest}).",
            ]);
        }
    }

    /**
     * Transactions that do NOT belong to the opening-balances feature itself, i.e. the ones
     * that count as "real" activity for the date guard and for the "earliest transaction"
     * display. Shared by SaveAction::guardDate and ViewAction.
     *
     * A genuinely real transaction that later gets reversed (by anything, opening balances or
     * not) still happened and must still bound as_of_date — so this must NOT exclude
     * transactions merely for being reversed/a reversal in general. It excludes exactly:
     *  (a) every 'opening_balance' journal, current or any retired generation (the type never
     *      changes across generations, so this alone covers all of them, live or reversed);
     *  (b) every opening invoice/bill posting, current or retired generation, identified by id
     *      via settings — never by the internal_notes marker, which void actions can overwrite —
     *      matched by reference_id;
     *  (c) any transaction whose reversal_of_id points at a transaction matching (a) or (b) —
     *      i.e. the void reversal of an opening invoice/bill/journal — via a sub-select, so this
     *      holds even if a future reverseTransaction() stops copying reference_id.
     */
    public static function nonOpeningTransactions(string $companyId, array $opening)
    {
        $excludedReferenceIds = array_values(array_unique(array_merge(
            $opening['invoice_ids'] ?? [],
            $opening['retired_invoice_ids'] ?? [],
            $opening['bill_ids'] ?? [],
            $opening['retired_bill_ids'] ?? []
        )));

        // Laravel compiles an empty whereIn to "0 = 1" and an empty whereNotIn to "1 = 1", so
        // these must be called unconditionally — wrapping them in `if (! empty(...))` collapses
        // the whereNotIn branch to nothing on a fresh company (no opening invoices/bills yet),
        // which leaves only `whereNull('reference_id')` and wrongly hides every real posting
        // that carries a reference_id (invoices, bills, payments, ...) from the date guard.
        $openingTransactionIds = fn () => Transaction::where('company_id', $companyId)
            ->where(function ($q) use ($excludedReferenceIds) {
                $q->where('transaction_type', self::JOURNAL_TYPE)
                    ->orWhereIn('reference_id', $excludedReferenceIds);
            })
            ->select('id');

        return Transaction::where('company_id', $companyId)
            ->where('transaction_type', '!=', self::JOURNAL_TYPE)
            ->where(function ($q) use ($excludedReferenceIds) {
                $q->whereNull('reference_id')->orWhereNotIn('reference_id', $excludedReferenceIds);
            })
            ->where(function ($q) use ($openingTransactionIds) {
                $q->whereNull('reversal_of_id')->orWhereNotIn('reversal_of_id', $openingTransactionIds());
            });
    }

    /**
     * Opening balances are re-entered as a whole: every earlier opening record is
     * reversed/voided first. Refuses when any opening record has been used since.
     *
     * Two passes: the first only checks (throws before anything is mutated), the second
     * mutates — so a mid-way refusal never leaves a half-reversed state.
     */
    private function reversePrevious($company, array $opening): void
    {
        $companyId = $company->id;

        $invoiceIds = $opening['invoice_ids'] ?? [];
        $billIds = $opening['bill_ids'] ?? [];
        $journalId = $opening['journal_id'] ?? null;

        $invoices = empty($invoiceIds) ? collect() : Invoice::where('company_id', $companyId)->whereIn('id', $invoiceIds)->where('status', '!=', 'void')->get();
        $bills = empty($billIds) ? collect() : Bill::where('company_id', $companyId)->whereIn('id', $billIds)->where('status', '!=', 'void')->get();

        // The prior generation's own journal, identified by the id tracked in settings — not by
        // scanning for "any transaction_type = opening_balance row", which would also match
        // already-retired generations' journals.
        $journals = collect();
        if ($journalId) {
            $journal = Transaction::where('company_id', $companyId)->where('id', $journalId)->whereNull('reversed_by_id')->with('journalEntries')->first();
            if ($journal) {
                $journals->push($journal);
            }
        }

        $amanatByJournal = [];
        $advancesByJournal = [];
        $salariesOwedByJournal = [];

        // ---- Pass 1: validate only, mutate nothing ----
        foreach ($invoices as $invoice) {
            if ((float) $invoice->paid_amount > 0 || ((float) $invoice->total_amount - (float) $invoice->balance) > 0.005) {
                throw ValidationException::withMessages(['credit_customers' => "Opening invoice {$invoice->invoice_number} already has payments; cannot re-enter opening balances."]);
            }
        }
        foreach ($bills as $bill) {
            if ((float) $bill->paid_amount > 0 || ((float) $bill->total_amount - (float) $bill->balance) > 0.005) {
                throw ValidationException::withMessages(['suppliers' => "Opening bill {$bill->bill_number} already has payments; cannot re-enter opening balances."]);
            }
        }
        foreach ($journals as $journal) {
            // Sub-records link to their own journal LINE (journal_entry_id is an FK to acct.journal_entries).
            $entryIds = $journal->journalEntries->pluck('id')->all();

            $amanats = AmanatTransaction::whereIn('journal_entry_id', $entryIds)->get();
            foreach ($amanats as $amanat) {
                $profile = CustomerProfile::where('company_id', $companyId)->where('customer_id', $amanat->customer_id)->first();
                $balance = $profile ? (float) $profile->amanat_balance : 0.0;
                if ($balance < (float) $amanat->amount) {
                    throw ValidationException::withMessages(['amanat' => 'An opening amanat balance has already been drawn down; cannot re-enter opening balances.']);
                }
            }
            $amanatByJournal[$journal->id] = $amanats;

            $advances = SalaryAdvance::whereIn('journal_entry_id', $entryIds)->get();
            foreach ($advances as $advance) {
                if ((float) $advance->amount_recovered > 0) {
                    throw ValidationException::withMessages(['employees' => 'An opening employee advance has already been partly recovered; cannot re-enter opening balances.']);
                }
            }
            $advancesByJournal[$journal->id] = $advances;

            $salariesOwed = Payslip::where('company_id', $companyId)
                ->where('gl_transaction_id', $journal->id)
                ->where('notes', self::MARK)
                ->get();
            foreach ($salariesOwed as $salaryPayslip) {
                if ($salaryPayslip->status === 'paid' || $salaryPayslip->payment_gl_transaction_id) {
                    throw ValidationException::withMessages(['salaries_owed' => 'An opening salary has already been paid; cannot re-enter opening balances.']);
                }
            }
            $salariesOwedByJournal[$journal->id] = $salariesOwed;
        }

        // ---- Pass 2: mutate — delete outright, never void/reverse ----
        foreach ($invoices as $invoice) {
            $this->deleteOpeningInvoice($companyId, $invoice);
        }
        foreach ($bills as $bill) {
            $this->deleteOpeningBill($companyId, $bill);
        }
        foreach ($journals as $journal) {
            foreach ($amanatByJournal[$journal->id] as $amanat) {
                $profile = CustomerProfile::getOrCreateForCustomer($companyId, $amanat->customer_id);
                $profile->adjustAmanatBalance(-(float) $amanat->amount);
                $amanat->delete();
            }

            foreach ($advancesByJournal[$journal->id] as $advance) {
                // SalaryAdvance uses SoftDeletes; a soft-deleted row would still be found by a
                // later scoped query, so it must be force-deleted on re-entry.
                $advance->forceDelete();
            }

            foreach ($salariesOwedByJournal[$journal->id] as $salaryPayslip) {
                $salaryPayslip->lines()->delete();
                $salaryPayslip->delete();
            }

            PartnerTransaction::whereIn('journal_entry_id', $journal->journalEntries->pluck('id')->all())->delete();

            $this->deleteJournal($journal);
        }
    }

    /**
     * Deletes a transaction's journal entries (hard — acct.journal_entries has no soft-delete
     * column) and soft-deletes the transaction itself, exactly what "delete" means for every
     * other row this action touches. Used instead of PostingService::reverseTransaction, which
     * posts an offsetting reversal pair rather than removing the original.
     */
    private function deleteJournal(Transaction $journal): void
    {
        JournalEntry::where('transaction_id', $journal->id)->delete();
        $journal->delete();
    }

    /**
     * Finds the posted transaction behind an opening invoice the same way Invoice\VoidAction
     * does (transaction_id first, falling back to the reference lookup), then deletes the
     * invoice tree outright: line items, that transaction's journal entries, the transaction,
     * and the invoice.
     */
    private function deleteOpeningInvoice(string $companyId, Invoice $invoice): void
    {
        $transaction = $this->resolvePostedTransaction($companyId, 'acct.invoices', $invoice->id, $invoice->transaction_id);
        if ($transaction) {
            $this->deleteJournal($transaction);
        }
        InvoiceLineItem::where('invoice_id', $invoice->id)->delete();
        $invoice->delete();
    }

    private function deleteOpeningBill(string $companyId, Bill $bill): void
    {
        $transaction = $this->resolvePostedTransaction($companyId, 'acct.bills', $bill->id, $bill->transaction_id);
        if ($transaction) {
            $this->deleteJournal($transaction);
        }
        BillLineItem::where('bill_id', $bill->id)->delete();
        $bill->delete();
    }

    private function resolvePostedTransaction(string $companyId, string $referenceType, string $referenceId, ?string $transactionId): ?Transaction
    {
        if ($transactionId) {
            $transaction = Transaction::where('company_id', $companyId)->where('id', $transactionId)->whereNull('deleted_at')->first();
            if ($transaction) {
                return $transaction;
            }
        }

        return Transaction::where('company_id', $companyId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereNull('reversal_of_id')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function addCashAndBankLines(string $companyId, array $params, array $accounts, array &$lines): void
    {
        $cash = (float) ($params['cash']['amount'] ?? 0);
        if ($cash > 0) {
            if (! $accounts['cash']) {
                throw ValidationException::withMessages(['cash.amount' => 'Set up account 1050 (Cash on Hand) first.']);
            }
            $lines[] = ['account_id' => $accounts['cash'], 'type' => 'debit', 'amount' => $cash, 'description' => 'Opening cash on hand'];
        }

        foreach ($params['banks'] ?? [] as $i => $bank) {
            $amount = (float) $bank['amount'];
            if ($amount <= 0) {
                continue;
            }
            $account = Account::where('company_id', $companyId)->where('id', $bank['account_id'])->where('subtype', 'bank')->first();
            if (! $account) {
                throw ValidationException::withMessages(["banks.{$i}.account_id" => 'Not a bank account of this company.']);
            }
            $lines[] = ['account_id' => $account->id, 'type' => 'debit', 'amount' => $amount, 'description' => "Opening balance — {$account->name}"];
        }
    }

    /**
     * Posts the lines plus a single 3080 line that makes them balance.
     * Returns [journal id, entry ids ordered to match $lines (0-based)] or [null, []] if there are no lines.
     *
     * @return array{0: ?string, 1: array<int, string>}
     */
    private function postJournal(string $companyId, string $currency, string $asOf, string $equityId, array $lines): array
    {
        if (empty($lines)) {
            return [null, []];
        }

        $debits = 0.0;
        $credits = 0.0;
        foreach ($lines as $line) {
            $line['type'] === 'debit' ? $debits += $line['amount'] : $credits += $line['amount'];
        }
        $net = round($debits - $credits, 2);
        if ($net > 0) {
            $lines[] = ['account_id' => $equityId, 'type' => 'credit', 'amount' => $net, 'description' => 'Opening balance equity'];
        } elseif ($net < 0) {
            $lines[] = ['account_id' => $equityId, 'type' => 'debit', 'amount' => abs($net), 'description' => 'Opening balance equity'];
        }

        $transaction = $this->posting->postBalancedTransaction([
            'company_id' => $companyId,
            'transaction_type' => self::JOURNAL_TYPE,
            'date' => $asOf,
            'currency' => $currency,
            'base_currency' => $currency,
            'description' => 'Opening balances as of '.$asOf,
            'reference_type' => self::REFERENCE_TYPE,
            'reference_id' => null,
        ], $lines);

        $entryIdsByLine = $transaction->journalEntries()->orderBy('line_number')->pluck('id')->values()->all();

        return [$transaction->id, $entryIdsByLine];
    }

    private function addAmanatLines(string $companyId, array $params, array $accounts, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['amanat'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }
        if (! $accounts['amanat']) {
            throw ValidationException::withMessages(['amanat' => 'Set up account 2200 (Customer Amanat Deposits) first.']);
        }
        foreach ($rows as $row) {
            $customer = Customer::where('company_id', $companyId)->findOrFail($row['customer_id']);
            $amount = round((float) $row['amount'], 2);
            $lineIndex = count($lines);
            $lines[] = ['account_id' => $accounts['amanat'], 'type' => 'credit', 'amount' => $amount, 'description' => "Opening amanat — {$customer->name}"];
            $pending[] = function (array $entryIdsByLine) use ($companyId, $customer, $amount, $lineIndex) {
                $profile = CustomerProfile::getOrCreateForCustomer($companyId, $customer->id);
                if (! $profile->is_amanat_holder) {
                    $profile->update(['is_amanat_holder' => true]);
                }
                AmanatTransaction::create([
                    'company_id' => $companyId,
                    'customer_id' => $customer->id,
                    'transaction_type' => AmanatTransaction::TYPE_DEPOSIT,
                    'amount' => $amount,
                    'reference' => self::MARK,
                    'notes' => 'Opening balance',
                    'recorded_by_user_id' => Auth::id(),
                    'journal_entry_id' => $entryIdsByLine[$lineIndex],
                ]);
                $profile->adjustAmanatBalance($amount);
            };
        }
    }

    private function addEmployeeAdvanceLines(string $companyId, array $params, array $accounts, string $asOf, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['employees'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }
        if (! $accounts['employee_advances']) {
            throw ValidationException::withMessages(['employees' => 'Set up account 1150 (Employee Advances) first.']);
        }
        foreach ($rows as $row) {
            $employee = Employee::where('company_id', $companyId)->findOrFail($row['employee_id']);
            $amount = round((float) $row['amount'], 2);
            $employeeId = $employee->id;
            $employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));
            $lineIndex = count($lines);
            $lines[] = ['account_id' => $accounts['employee_advances'], 'type' => 'debit', 'amount' => $amount, 'description' => "Opening employee advance — {$employeeName}"];
            $pending[] = function (array $entryIdsByLine) use ($companyId, $employeeId, $amount, $asOf, $accounts, $lineIndex) {
                SalaryAdvance::create([
                    'company_id' => $companyId,
                    'employee_id' => $employeeId,
                    'advance_date' => $asOf,
                    'amount' => $amount,
                    'amount_recovered' => 0,
                    'amount_outstanding' => $amount,
                    'reason' => 'Opening balance',
                    'status' => 'pending',
                    'payment_method' => 'cash',
                    'reference' => self::MARK,
                    'journal_entry_id' => $entryIdsByLine[$lineIndex],
                    'advance_account_id' => $accounts['employee_advances'],
                    'recorded_by_user_id' => Auth::id(),
                ]);
            };
        }
    }

    /**
     * A salary earned before the opening date and not yet paid: posted Cr against the same
     * salaries-payable account a payroll payment later debits (PayrollPostingService's account
     * resolution, e.g. 2211 "Payroll Salaries Payable") so paying it off nets that account back
     * to zero. Unlike the other sections this creates a real, approved payslip the payroll
     * module can pay normally - the opening journal line IS its accrual, so no second accrual is
     * ever posted for it (Payslip::gl_transaction_id points straight at this journal).
     */
    private function addSalariesOwedLines(string $companyId, array $params, string $currency, string $asOf, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['salaries_owed'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }

        $payrollService = app(PayrollPostingService::class);
        $payrollPayableAccountId = $payrollService->ensureDefaultPayrollAccounts($companyId)['payroll_payable']['id'];
        $earningType = $payrollService->ensureBaseSalaryEarningType($companyId);

        foreach ($rows as $row) {
            $employee = Employee::where('company_id', $companyId)->findOrFail($row['employee_id']);
            $amount = round((float) $row['amount'], 2);
            $employeeId = $employee->id;
            $employeeName = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));
            $lines[] = ['account_id' => $payrollPayableAccountId, 'type' => 'credit', 'amount' => $amount, 'description' => "Salary owed at opening — {$employeeName}"];
            $pending[] = function (array $entryIdsByLine, ?string $journalId) use ($companyId, $employeeId, $amount, $currency, $asOf, $earningType, $payrollService) {
                $periodStart = Carbon::parse($asOf)->startOfMonth()->toDateString();
                $period = PayrollPeriod::firstOrCreate(
                    ['company_id' => $companyId, 'period_start' => $periodStart, 'period_end' => $asOf],
                    ['payment_date' => $asOf, 'status' => 'open'],
                );

                $payslip = Payslip::create([
                    'company_id' => $companyId,
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employeeId,
                    'payslip_number' => $payrollService->nextPayslipNumber($companyId),
                    'currency' => $currency,
                    'exchange_rate' => null,
                    'base_currency' => $currency,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by_user_id' => Auth::id(),
                    'gl_transaction_id' => $journalId,
                    'notes' => self::MARK,
                ]);

                $payslip->lines()->create([
                    'line_type' => 'earning',
                    'earning_type_id' => $earningType->id,
                    'description' => 'Salary owed at opening',
                    'quantity' => 1,
                    'rate' => $amount,
                    'amount' => $amount,
                    'sort_order' => 1,
                ]);
            };
        }
    }

    private function addPartnerLines(string $companyId, array $params, array $accounts, string $asOf, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['partners'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }
        if (! $accounts['partner_deposits']) {
            throw ValidationException::withMessages(['partners' => 'Set up account 2210 (Investor Deposits) first.']);
        }
        foreach ($rows as $row) {
            $partner = Partner::where('company_id', $companyId)->findOrFail($row['partner_id']);
            $amount = round((float) $row['amount'], 2);
            $partnerId = $partner->id;
            $lineIndex = count($lines);
            $lines[] = ['account_id' => $accounts['partner_deposits'], 'type' => 'credit', 'amount' => $amount, 'description' => "Opening partner capital — {$partner->name}"];
            $pending[] = function (array $entryIdsByLine) use ($companyId, $partnerId, $amount, $asOf, $lineIndex) {
                PartnerTransaction::create([
                    'company_id' => $companyId,
                    'partner_id' => $partnerId,
                    'transaction_date' => $asOf,
                    'transaction_type' => 'investment',
                    'amount' => $amount,
                    'description' => 'Opening balance',
                    'reference' => self::MARK,
                    'payment_method' => 'cash',
                    'journal_entry_id' => $entryIdsByLine[$lineIndex],
                    'recorded_by_user_id' => Auth::id(),
                ]);
            };
        }
    }

    private function createOpeningInvoices($company, array $params, array $accounts, string $asOf, string $currency): array
    {
        $rows = array_filter($params['credit_customers'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return [];
        }
        if (! $accounts['ar']) {
            throw ValidationException::withMessages(['credit_customers' => 'Set up account 1100 (Accounts Receivable) first.']);
        }
        $bus = app(CommandBus::class);
        $ids = [];
        foreach ($rows as $row) {
            $customer = Customer::where('company_id', $company->id)->findOrFail($row['customer_id']);
            $result = $bus->dispatch('invoice.create', [
                'customer' => $customer->id,
                'currency' => $currency,
                'date' => $asOf,
                'due' => $asOf,
                'send_immediately' => true,
                'internal_notes' => self::MARK,
                'description' => 'Opening balance as of '.$asOf,
                'line_items' => [[
                    'description' => 'Opening balance as of '.$asOf,
                    'quantity' => 1,
                    'unit_price' => round((float) $row['amount'], 2),
                    'tax_rate' => 0,
                    'income_account_id' => $accounts['equity'],
                ]],
            ], Auth::user(), true);
            $ids[] = $result['data']['id'];
        }

        return $ids;
    }

    private function createOpeningBills($company, array $params, array $accounts, string $asOf, string $currency): array
    {
        $rows = array_filter($params['suppliers'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return [];
        }
        if (! $accounts['ap']) {
            throw ValidationException::withMessages(['suppliers' => 'Set up account 2100 (Accounts Payable) first.']);
        }
        $bus = app(CommandBus::class);
        $ids = [];
        foreach ($rows as $row) {
            Vendor::where('company_id', $company->id)->findOrFail($row['vendor_id']);
            $result = $bus->dispatch('bill.create', [
                'vendor_id' => $row['vendor_id'],
                'bill_date' => $asOf,
                'due_date' => $asOf,
                'status' => 'received',
                'currency' => $currency,
                'base_currency' => $currency,
                'internal_notes' => self::MARK,
                'line_items' => [[
                    'description' => 'Opening balance as of '.$asOf,
                    'quantity' => 1,
                    'unit_price' => round((float) $row['amount'], 2),
                    'tax_rate' => 0,
                    'expense_account_id' => $accounts['equity'],
                ]],
            ], Auth::user(), true);
            $ids[] = $result['data']['id'];
        }

        return $ids;
    }
}
