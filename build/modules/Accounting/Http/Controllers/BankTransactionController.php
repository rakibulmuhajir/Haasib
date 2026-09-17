<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreBankTransactionRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
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
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $transactions = Transaction::where('company_id', $company->id)
            ->where('reference_type', 'acct.bank_transactions')
            ->whereIn('status', ['posted', 'locked'])
            ->with('journalEntries.account')
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(function (Transaction $transaction) {
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
                ];
            });

        $accounts = Account::where('company_id', $company->id)->where('is_active', true)
            ->whereNull('deleted_at')->whereIn('subtype', ['cash', 'bank'])->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);

        // Running balance per account, from every posted journal line against it (this
        // page's own transactions included — they are ordinary journals, not separate).
        $balances = $accounts->mapWithKeys(function (Account $account) use ($company) {
            $debit = \Illuminate\Support\Facades\DB::table('acct.journal_entries')
                ->join('acct.transactions', 'acct.transactions.id', '=', 'acct.journal_entries.transaction_id')
                ->where('acct.transactions.company_id', $company->id)
                ->whereIn('acct.transactions.status', ['posted', 'locked'])
                ->where('acct.journal_entries.account_id', $account->id)
                ->selectRaw('COALESCE(SUM(debit_amount),0) as d, COALESCE(SUM(credit_amount),0) as c')
                ->first();
            return [$account->id => round((float) ($debit->d ?? 0) - (float) ($debit->c ?? 0), 2)];
        });

        return Inertia::render('accounting/bank-transactions/Index', [
            'transactions' => $transactions,
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'subtype' => $a->subtype,
                'balance' => $balances[$a->id] ?? 0,
            ]),
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
