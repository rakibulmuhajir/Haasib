<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\BankReconciliation;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BankReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = BankReconciliation::where('company_id', $company->id)
            ->with(['bankAccount:id,account_name,account_number,currency'])
            ->orderByDesc('statement_date');

        if ($request->has('bank_account_id') && $request->bank_account_id) {
            $query->where('bank_account_id', $request->bank_account_id);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $reconciliations = $query->paginate(25)->withQueryString();

        $bankAccounts = BankAccount::where('company_id', $company->id)
            ->active()
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'account_number', 'currency', 'current_balance', 'last_reconciled_date']);

        return Inertia::render('accounting/bank-reconciliation/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'reconciliations' => $reconciliations,
            'bankAccounts' => $bankAccounts,
            'filters' => [
                'bank_account_id' => $request->bank_account_id ?? '',
                'status' => $request->status ?? '',
            ],
        ]);
    }

    public function start(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $bankAccounts = BankAccount::where('company_id', $company->id)
            ->active()
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'account_number', 'currency', 'gl_account_id', 'current_balance', 'last_reconciled_date', 'last_reconciled_balance']);
        // The balance the books carry today, from the bank's ledger account.
        $bankAccounts->each(function ($account) use ($company) {
            $account->current_balance = round((float) DB::table('acct.journal_entries as je')
                ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
                ->where('t.company_id', $company->id)->where('je.account_id', $account->gl_account_id)
                ->whereIn('t.status', ['posted', 'locked'])->whereNull('t.deleted_at')
                ->sum(DB::raw('je.debit_amount - je.credit_amount')), 2);
        });

        return Inertia::render('accounting/bank-reconciliation/Start', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function store(Request $request, BankReconciliationService $service): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $validated = $request->validate([
            'bank_account_id' => ['required', 'uuid', Rule::exists(BankAccount::class, 'id')->where('company_id', $company->id)],
            'statement_date' => ['required', 'date_format:Y-m-d'],
            'statement_ending_balance' => ['required', 'numeric'],
            'statement' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $bank = BankAccount::where('company_id', $company->id)->findOrFail($validated['bank_account_id']);
        if (! $bank->gl_account_id) {
            return back()->withErrors(['bank_account_id' => 'This bank has no ledger account.']);
        }
        $open = BankReconciliation::where('bank_account_id', $bank->id)->where('status', 'in_progress')->first();
        if ($open) {
            return redirect()->route('banking.reconciliation.show', ['company' => $company->slug, 'reconciliation' => $open->id])
                ->with('info', 'This bank already has a reconciliation in progress.');
        }
        $last = BankReconciliation::where('bank_account_id', $bank->id)->where('status', 'completed')->max('statement_date');
        if ($last && $validated['statement_date'] <= substr((string) $last, 0, 10)) {
            return back()->withErrors(['statement_date' => 'Must be after the last reconciled statement ('.substr((string) $last, 0, 10).').']);
        }

        $recon = BankReconciliation::create([
            'company_id' => $company->id,
            'bank_account_id' => $bank->id,
            'statement_date' => $validated['statement_date'],
            'statement_ending_balance' => $validated['statement_ending_balance'],
            'book_balance' => 0,
            'reconciled_balance' => 0,
            'difference' => $validated['statement_ending_balance'],
            'status' => 'in_progress',
            'started_at' => now(),
            'created_by_user_id' => Auth::id(),
        ]);
        $show = fn () => redirect()->route('banking.reconciliation.show', ['company' => $company->slug, 'reconciliation' => $recon->id]);

        if (! $request->hasFile('statement')) {
            return $show()->with('success', 'Reconciliation started');
        }
        try {
            $matched = $service->importStatement($recon->fresh('bankAccount'), $request->file('statement')->getRealPath());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $show()->withErrors($e->errors());
        }

        return $show()->with('success', "Statement imported · {$matched} matched");
    }

    public function show(Request $request, string $company, string $reconciliation, BankReconciliationService $service): Response
    {
        $companyModel = CompanyContext::getCompany();
        $recon = BankReconciliation::where('company_id', $companyModel->id)
            ->with(['bankAccount:id,account_name,account_number,currency,gl_account_id'])
            ->findOrFail($reconciliation);

        return Inertia::render('accounting/bank-reconciliation/Show', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
            ],
            'reconciliation' => [
                'id' => $recon->id,
                'status' => $recon->status,
                'statement_date' => $recon->statement_date->toDateString(),
                'statement_ending_balance' => (float) $recon->statement_ending_balance,
                'completed_at' => $recon->completed_at?->toISOString(),
                'bank_account' => [
                    'id' => $recon->bankAccount->id,
                    'name' => $recon->bankAccount->account_name,
                    'number' => $recon->bankAccount->account_number,
                    'currency' => $recon->bankAccount->currency,
                ],
            ],
            ...$service->view($recon),
            // Accounts a missing statement line (charge, profit, returned cheque) can be booked to.
            'entryAccounts' => Account::where('company_id', $companyModel->id)->where('is_active', true)->whereNull('deleted_at')
                ->whereIn('type', ['expense', 'revenue', 'other_income', 'other_expense', 'asset', 'liability', 'equity'])
                ->where('id', '!=', $recon->bankAccount->gl_account_id)
                ->orderBy('code')->get(['id', 'code', 'name', 'type']),
        ]);
    }

    public function toggleTransaction(Request $request, string $company, string $reconciliation, BankReconciliationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'journal_entry_id' => ['required', 'uuid'],
            'cleared' => ['required', 'boolean'],
        ]);
        $service->toggle($this->find($reconciliation), $validated['journal_entry_id'], (bool) $validated['cleared']);

        return back();
    }

    public function import(Request $request, string $company, string $reconciliation, BankReconciliationService $service): RedirectResponse
    {
        $request->validate(['statement' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $matched = $service->importStatement($this->find($reconciliation), $request->file('statement')->getRealPath());

        return back()->with('success', "Statement imported · {$matched} matched");
    }

    public function addEntry(Request $request, string $company, string $reconciliation, BankReconciliationService $service): RedirectResponse
    {
        $companyModel = CompanyContext::getCompany();
        $validated = $request->validate([
            'statement_line_id' => ['required', 'uuid'],
            'account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')->where('company_id', $companyModel->id)],
        ]);
        $service->addEntry($this->find($reconciliation), $validated['statement_line_id'], $validated['account_id'], Auth::id());

        return back()->with('success', 'Entry added');
    }

    public function complete(Request $request, string $company, string $reconciliation, BankReconciliationService $service): RedirectResponse
    {
        $service->complete($this->find($reconciliation), Auth::id());

        return redirect()->route('banking.reconciliation.index', ['company' => CompanyContext::getCompany()->slug])
            ->with('success', 'Reconciliation completed');
    }

    public function cancel(Request $request, string $company, string $reconciliation, BankReconciliationService $service): RedirectResponse
    {
        $service->discard($this->find($reconciliation));

        return redirect()->route('banking.reconciliation.index', ['company' => CompanyContext::getCompany()->slug])
            ->with('success', 'Reconciliation discarded');
    }

    private function find(string $id): BankReconciliation
    {
        return BankReconciliation::where('company_id', CompanyContext::getCompany()->id)->with('bankAccount')->findOrFail($id);
    }
}
