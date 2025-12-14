<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\BankReconciliation;
use App\Modules\Accounting\Models\BankTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            ->get(['id', 'account_name', 'account_number', 'currency', 'current_balance', 'last_reconciled_date', 'last_reconciled_balance']);

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

    public function store(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $validated = $request->validate([
            'bank_account_id' => 'required|uuid|exists:acct.company_bank_accounts,id',
            'statement_date' => 'required|date',
            'statement_ending_balance' => 'required|numeric',
        ]);

        // Check for existing reconciliation for this account and date
        $existing = BankReconciliation::where('bank_account_id', $validated['bank_account_id'])
            ->where('statement_date', $validated['statement_date'])
            ->first();

        if ($existing) {
            return redirect()
                ->route('banking.reconciliation.show', ['company' => $company->slug, 'reconciliation' => $existing->id])
                ->with('info', 'Resuming existing reconciliation for this date.');
        }

        // Get the book balance (system balance at statement date)
        $bankAccount = BankAccount::find($validated['bank_account_id']);
        $bookBalance = BankTransaction::where('bank_account_id', $validated['bank_account_id'])
            ->where('transaction_date', '<=', $validated['statement_date'])
            ->whereNull('deleted_at')
            ->sum('amount') + $bankAccount->opening_balance;

        $reconciliation = BankReconciliation::create([
            'company_id' => $company->id,
            'bank_account_id' => $validated['bank_account_id'],
            'statement_date' => $validated['statement_date'],
            'statement_ending_balance' => $validated['statement_ending_balance'],
            'book_balance' => $bookBalance,
            'reconciled_balance' => 0,
            'difference' => $validated['statement_ending_balance'],
            'status' => 'in_progress',
            'started_at' => now(),
            'created_by_user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('banking.reconciliation.show', ['company' => $company->slug, 'reconciliation' => $reconciliation->id]);
    }

    public function show(Request $request, string $company, string $reconciliation): Response
    {
        $companyModel = CompanyContext::getCompany();

        $recon = BankReconciliation::where('company_id', $companyModel->id)
            ->with(['bankAccount:id,account_name,account_number,currency,current_balance'])
            ->findOrFail($reconciliation);

        // Get transactions for this reconciliation period
        $transactions = BankTransaction::where('bank_account_id', $recon->bank_account_id)
            ->where('transaction_date', '<=', $recon->statement_date)
            ->where(function ($q) use ($recon) {
                $q->where('is_reconciled', false)
                    ->orWhere('reconciliation_id', $recon->id);
            })
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get([
                'id',
                'transaction_date',
                'description',
                'transaction_type',
                'amount',
                'is_reconciled',
                'reconciliation_id',
                'payee_name',
                'reference_number',
            ]);

        // Calculate current reconciled balance
        $reconciledBalance = $transactions
            ->where('reconciliation_id', $recon->id)
            ->sum('amount') + $recon->bankAccount->opening_balance;

        // Get transactions that were reconciled with this reconciliation
        $startingBalance = $recon->bankAccount->last_reconciled_balance ?? $recon->bankAccount->opening_balance;

        return Inertia::render('accounting/bank-reconciliation/Show', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
            ],
            'reconciliation' => $recon,
            'transactions' => $transactions,
            'summary' => [
                'starting_balance' => $startingBalance,
                'statement_ending_balance' => (float) $recon->statement_ending_balance,
                'reconciled_balance' => $reconciledBalance,
                'book_balance' => (float) $recon->book_balance,
                'difference' => (float) $recon->statement_ending_balance - $reconciledBalance,
                'cleared_deposits' => $transactions->where('reconciliation_id', $recon->id)->where('amount', '>', 0)->sum('amount'),
                'cleared_withdrawals' => $transactions->where('reconciliation_id', $recon->id)->where('amount', '<', 0)->sum('amount'),
                'uncleared_count' => $transactions->whereNull('reconciliation_id')->count(),
            ],
            'canComplete' => abs((float) $recon->statement_ending_balance - $reconciledBalance) < 0.01,
        ]);
    }

    public function toggleTransaction(Request $request, string $company, string $reconciliation): JsonResponse
    {
        $companyModel = CompanyContext::getCompany();

        $recon = BankReconciliation::where('company_id', $companyModel->id)
            ->where('status', 'in_progress')
            ->findOrFail($reconciliation);

        $validated = $request->validate([
            'transaction_id' => 'required|uuid|exists:acct.bank_transactions,id',
        ]);

        $transaction = BankTransaction::where('bank_account_id', $recon->bank_account_id)
            ->findOrFail($validated['transaction_id']);

        // Toggle reconciliation status
        if ($transaction->reconciliation_id === $recon->id) {
            // Unreconcile
            $transaction->update([
                'reconciliation_id' => null,
                'is_reconciled' => false,
                'reconciled_date' => null,
                'reconciled_by_user_id' => null,
            ]);
        } else {
            // Reconcile
            $transaction->update([
                'reconciliation_id' => $recon->id,
                'is_reconciled' => true,
                'reconciled_date' => now()->toDateString(),
                'reconciled_by_user_id' => Auth::id(),
            ]);
        }

        // Recalculate reconciled balance
        $reconciledBalance = BankTransaction::where('reconciliation_id', $recon->id)
            ->sum('amount') + $recon->bankAccount->opening_balance;

        $recon->update([
            'reconciled_balance' => $reconciledBalance,
            'difference' => $recon->statement_ending_balance - $reconciledBalance,
        ]);

        return response()->json([
            'success' => true,
            'reconciled_balance' => $reconciledBalance,
            'difference' => (float) $recon->statement_ending_balance - $reconciledBalance,
            'can_complete' => abs((float) $recon->statement_ending_balance - $reconciledBalance) < 0.01,
        ]);
    }

    public function complete(Request $request, string $company, string $reconciliation): RedirectResponse
    {
        $companyModel = CompanyContext::getCompany();

        $recon = BankReconciliation::where('company_id', $companyModel->id)
            ->where('status', 'in_progress')
            ->findOrFail($reconciliation);

        // Verify difference is zero
        $reconciledBalance = BankTransaction::where('reconciliation_id', $recon->id)
            ->sum('amount') + $recon->bankAccount->opening_balance;

        $difference = abs((float) $recon->statement_ending_balance - $reconciledBalance);

        if ($difference >= 0.01) {
            return redirect()
                ->back()
                ->with('error', 'Cannot complete reconciliation. Difference must be zero.');
        }

        DB::transaction(function () use ($recon, $reconciledBalance) {
            // Complete the reconciliation
            $recon->update([
                'status' => 'completed',
                'reconciled_balance' => $reconciledBalance,
                'difference' => 0,
                'completed_at' => now(),
                'completed_by_user_id' => Auth::id(),
            ]);

            // Update bank account
            $recon->bankAccount->update([
                'last_reconciled_date' => $recon->statement_date,
                'last_reconciled_balance' => $recon->statement_ending_balance,
            ]);
        });

        return redirect()
            ->route('banking.reconciliation.index', ['company' => $companyModel->slug])
            ->with('success', 'Bank reconciliation completed successfully.');
    }

    public function cancel(Request $request, string $company, string $reconciliation): RedirectResponse
    {
        $companyModel = CompanyContext::getCompany();

        $recon = BankReconciliation::where('company_id', $companyModel->id)
            ->where('status', 'in_progress')
            ->findOrFail($reconciliation);

        DB::transaction(function () use ($recon) {
            // Unreconcile all transactions
            BankTransaction::where('reconciliation_id', $recon->id)
                ->update([
                    'reconciliation_id' => null,
                    'is_reconciled' => false,
                    'reconciled_date' => null,
                    'reconciled_by_user_id' => null,
                ]);

            // Cancel the reconciliation
            $recon->update([
                'status' => 'cancelled',
            ]);
        });

        return redirect()
            ->route('banking.reconciliation.index', ['company' => $companyModel->slug])
            ->with('success', 'Bank reconciliation cancelled.');
    }
}
