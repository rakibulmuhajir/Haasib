<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreBankTransactionRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manual bank movements: deposit, withdrawal, transfer and bank charge. Each posts an
 * ordinary journal through GlPostingService (see BankTransaction\CreateAction) — the same
 * path the bank-feed resolution flow already uses, so a deposit entered here and a deposit
 * entered inside the Daily Close are indistinguishable once posted: the same canonical
 * journal, seen from two entry points, never two records for the same money movement.
 */
class BankTransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        $accountId = $request->query('account_id') ?: null;
        $dateFrom = $request->query('date_from') ?: null;
        $dateTo = $request->query('date_to') ?: null;
        $search = $request->query('search') ?: null;

        $query = Transaction::where('company_id', $company->id)
            ->where('reference_type', 'acct.bank_transactions')
            ->whereIn('status', ['posted', 'locked'])
            ->with('journalEntries.account');

        if ($accountId) {
            $query->whereHas('journalEntries', fn ($q) => $q->where('account_id', $accountId));
        }
        if ($dateFrom) {
            $query->where('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('transaction_date', '<=', $dateTo);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                    ->orWhere('transaction_number', 'ilike', "%{$search}%");
            });
        }

        $paginated = $query->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        // Running balance as of this row across the *whole* ledger for the filtered
        // account, not just the filtered/paginated slice: a bank statement's balance
        // column never resets because you searched or turned the page, so it is computed
        // once from every posted/locked line against that account, oldest first, and the
        // current page's rows just look themselves up in that map. Only meaningful when
        // narrowed to a single account (mixing several accounts' debits/credits into one
        // running number would not correspond to anything on a real statement), so it is
        // left null otherwise and the Vue page hides the column in that case.
        $runningBalances = [];
        if ($accountId) {
            $ledgerRows = DB::table('acct.journal_entries')
                ->join('acct.transactions', 'acct.transactions.id', '=', 'acct.journal_entries.transaction_id')
                ->where('acct.transactions.company_id', $company->id)
                ->whereIn('acct.transactions.status', ['posted', 'locked'])
                ->where('acct.journal_entries.account_id', $accountId)
                ->orderBy('acct.transactions.transaction_date')
                ->orderBy('acct.transactions.created_at')
                ->select('acct.transactions.id as transaction_id', 'acct.journal_entries.debit_amount', 'acct.journal_entries.credit_amount')
                ->get();

            $running = 0.0;
            foreach ($ledgerRows as $row) {
                $running = round($running + (float) $row->debit_amount - (float) $row->credit_amount, 2);
                // A transaction can carry more than one line against the same account; keep
                // the balance as of the *last* line seen for it.
                $runningBalances[$row->transaction_id] = $running;
            }
        }

        $paginated->through(function (Transaction $transaction) use ($runningBalances) {
            $lines = $transaction->journalEntries->map(fn ($line) => [
                'account_name' => $line->account?->name,
                'debit' => (float) $line->debit_amount,
                'credit' => (float) $line->credit_amount,
            ]);
            return [
                'id' => $transaction->id,
                'date' => $transaction->transaction_date->toDateString(),
                'transaction_number' => $transaction->transaction_number,
                'transaction_type' => $transaction->transaction_type,
                'description' => $transaction->description,
                'amount' => (float) $transaction->total_debit,
                'lines' => $lines,
                'running_balance' => $runningBalances[$transaction->id] ?? null,
            ];
        });

        $accounts = Account::where('company_id', $company->id)->where('is_active', true)
            ->whereNull('deleted_at')->whereIn('subtype', ['cash', 'bank'])->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);

        // Ending balance per account, from every posted journal line against it, for the
        // summary cards at the top -- always the whole ledger, never affected by the
        // filters below (those only narrow which transactions the table shows).
        $balances = $accounts->mapWithKeys(function (Account $account) use ($company) {
            $debit = DB::table('acct.journal_entries')
                ->join('acct.transactions', 'acct.transactions.id', '=', 'acct.journal_entries.transaction_id')
                ->where('acct.transactions.company_id', $company->id)
                ->whereIn('acct.transactions.status', ['posted', 'locked'])
                ->where('acct.journal_entries.account_id', $account->id)
                ->selectRaw('COALESCE(SUM(debit_amount),0) as d, COALESCE(SUM(credit_amount),0) as c')
                ->first();
            return [$account->id => round((float) ($debit->d ?? 0) - (float) ($debit->c ?? 0), 2)];
        });

        return Inertia::render('accounting/bank-transactions/Index', [
            'transactions' => $paginated,
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'subtype' => $a->subtype,
                'balance' => $balances[$a->id] ?? 0,
            ]),
            'filters' => [
                'account_id' => $accountId ?? '',
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
                'search' => $search ?? '',
            ],
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $cashAccounts = Account::where('company_id', $company->id)->where('is_active', true)
            ->whereNull('deleted_at')->where('subtype', 'cash')->orderBy('code')->get(['id', 'code', 'name']);
        $bankAccounts = Account::where('company_id', $company->id)->where('is_active', true)
            ->whereNull('deleted_at')->where('subtype', 'bank')->orderBy('code')->get(['id', 'code', 'name']);
        $expenseAccounts = Account::where('company_id', $company->id)->where('is_active', true)
            ->whereNull('deleted_at')->where('type', 'expense')->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('accounting/bank-transactions/Create', [
            'cashAccounts' => $cashAccounts,
            'bankAccounts' => $bankAccounts,
            'expenseAccounts' => $expenseAccounts,
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    public function store(StoreBankTransactionRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        try {
            app(CommandBus::class)->dispatch('bank_transaction.create', $request->validated(), $request->user());
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('banking.transactions.index', ['company' => $company->slug])
            ->with('success', 'Bank transaction recorded.');
    }
}
