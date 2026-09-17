<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreExpenseRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A standalone expense entry point. Today expenses could only be entered inside the Daily
 * Close or as a raw journal entry; this posts the same ordinary journal (transaction_type
 * 'expense') so the close still picks it up automatically for its date.
 */
class ExpenseController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $expenses = Transaction::where('company_id', $company->id)
            ->where('transaction_type', 'expense')
            ->whereIn('status', ['posted', 'locked'])
            ->with('journalEntries.account')
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->transaction_date->toDateString(),
                'transaction_number' => $transaction->transaction_number,
                'description' => $transaction->description,
                'amount' => (float) $transaction->total_debit,
                'expense_account' => $transaction->journalEntries->firstWhere('debit_amount', '>', 0)?->account?->name,
                'paid_from' => $transaction->journalEntries->firstWhere('credit_amount', '>', 0)?->account?->name,
            ]);

        return Inertia::render('accounting/expenses/Index', [
            'expenses' => $expenses,
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $expenseAccounts = Account::where('company_id', $company->id)->where('is_active', true)
            ->whereNull('deleted_at')->where('type', 'expense')->orderBy('code')->get(['id', 'code', 'name']);
        $paymentAccounts = Account::where('company_id', $company->id)->where('is_active', true)
            ->whereNull('deleted_at')->whereIn('subtype', ['cash', 'bank'])->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('accounting/expenses/Create', [
            'expenseAccounts' => $expenseAccounts,
            'paymentAccounts' => $paymentAccounts,
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        try {
            app(CommandBus::class)->dispatch('expense.create', $request->validated(), $request->user());
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.index', ['company' => $company->slug])
            ->with('success', 'Expense recorded.');
    }
}
