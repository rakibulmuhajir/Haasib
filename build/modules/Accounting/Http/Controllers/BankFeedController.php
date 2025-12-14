<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\BankTransaction;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Services\BankFeedResolutionService;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BankFeedController extends Controller
{
    protected BankFeedResolutionService $resolutionService;

    public function __construct(BankFeedResolutionService $resolutionService)
    {
        $this->resolutionService = $resolutionService;
    }

    public function index()
    {
        // Identify company via middleware (already handled by route group)
        $currentCompany = app(CurrentCompany::class)->get();

        $unreconciledTransactions = BankTransaction::where('company_id', $currentCompany->id)
            ->where('is_reconciled', false)
            ->orderBy('transaction_date')
            ->with(['bankAccount'])
            ->get();

        // Prepare suggestions and necessary reference data for the UI
        $transactionsWithSuggestions = $unreconciledTransactions->map(function ($bt) use ($currentCompany) {
            $suggestions = $this->getSuggestions($bt, $currentCompany->id);
            return array_merge($bt->toArray(), [
                'suggestions' => $suggestions,
            ]);
        });

        // Reference data for 'Create' and 'Transfer' modes
        $expenseAccounts = Account::where('company_id', $currentCompany->id)
            ->whereIn('type', ['expense', 'cogs', 'asset', 'liability'])
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'type', 'subtype') // Added type, subtype
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $incomeAccounts = Account::where('company_id', $currentCompany->id)
            ->whereIn('type', ['revenue', 'other_income', 'equity', 'liability'])
            ->where('is_active', true)
            ->select('id', 'name', 'code', 'type', 'subtype') // Added type, subtype
            ->orderBy('type')
            ->orderBy('code')
            ->get();
        
        $bankAccounts = BankAccount::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->select('id', 'account_name', 'account_number', 'current_balance', 'gl_account_id', 'currency')
            ->get();

        // Calculate Balance Explainer Data for the primary (or first) bank account
        // For V1, we focus on the first account in the list or the one being filtered if we add a filter later.
        $activeBankAccount = $bankAccounts->first(); 
        $balanceExplainer = null;

        if ($activeBankAccount) {
            // 1. Bank Feed Balance
            $feedBalance = $activeBankAccount->current_balance;

            // 2. System Ledger Balance
            // Sum of all posted journal entries for the linked GL account
            $ledgerBalance = 0;
            if ($activeBankAccount->gl_account_id) {
                $ledgerBalance = DB::table('acct.journal_entries')
                    ->join('acct.transactions', 'acct.journal_entries.transaction_id', '=', 'acct.transactions.id')
                    ->where('acct.journal_entries.company_id', $currentCompany->id) // Security Fix: Add company_id
                    ->where('acct.journal_entries.account_id', $activeBankAccount->gl_account_id)
                    ->where('acct.transactions.status', 'posted')
                    ->sum(DB::raw('debit_amount - credit_amount'));
                
                // Normal balance for Asset (Bank) is Debit. 
                // If it's a liability (Overdraft), logic might differ, but generally Asset = Dr - Cr.
            }

            // 3. Difference
            $difference = $feedBalance - $ledgerBalance;

            // 4. Explanations
            $explanations = [];
            
            // Count unreconciled items
            $unreconciledCount = $unreconciledTransactions->where('bank_account_id', $activeBankAccount->id)->count();
            $unreconciledSum = $unreconciledTransactions->where('bank_account_id', $activeBankAccount->id)->sum('amount');
            
            if ($unreconciledCount > 0) {
                $explanations[] = [
                    'label' => "{$unreconciledCount} Unreviewed transactions",
                    'amount' => $unreconciledSum,
                ];
            }

            // In a real scenario, we'd also check for future dated payments in GL not yet in feed, etc.

            $balanceExplainer = [
                'feed_balance' => $feedBalance,
                'ledger_balance' => $ledgerBalance,
                'difference' => $difference,
                'is_balanced' => abs($difference) < 0.01,
                'explanations' => $explanations,
                'currency' => $activeBankAccount->currency, // Pass currency
            ];
        }

        return Inertia::render('accounting/bank-feed/Index', [ // Lowercase 'bank-feed' matches directory
            'transactions' => $transactionsWithSuggestions,
            'expenseAccounts' => $expenseAccounts,
            'incomeAccounts' => $incomeAccounts,
            'bankAccounts' => $bankAccounts,
            'balanceExplainer' => $balanceExplainer,
        ]);
    }

    protected function getSuggestions(BankTransaction $bt, string $companyId): array
    {
        $suggestions = [];

        // Simple match logic: amount and date proximity
        // Try to match AR Payments
        $payments = Payment::where('company_id', $companyId)
            ->where('amount', abs($bt->amount)) // Match absolute amount
            ->where('payment_date', '>=', $bt->transaction_date->subDays(7))
            ->where('payment_date', '<=', $bt->transaction_date->addDays(7))
            ->whereNull('transaction_id') // Only payments not yet posted to GL
            ->get();

        if ($payments->isNotEmpty()) {
            foreach ($payments as $payment) {
                $suggestions['match'][] = [
                    'type' => 'payment',
                    'id' => $payment->id,
                    'description' => "Payment {$payment->payment_number} from {$payment->customer->display_name}",
                    'amount' => $payment->amount,
                ];
            }
        }

        // Try to match AP Bill Payments
        $billPayments = BillPayment::where('company_id', $companyId)
            ->where('amount', abs($bt->amount))
            ->where('payment_date', '>=', $bt->transaction_date->subDays(7))
            ->where('payment_date', '<=', $bt->transaction_date->addDays(7))
            ->whereNull('transaction_id')
            ->get();
        
        if ($billPayments->isNotEmpty()) {
            foreach ($billPayments as $billPayment) {
                $suggestions['match'][] = [
                    'type' => 'bill_payment',
                    'id' => $billPayment->id,
                    'description' => "Bill Payment {$billPayment->payment_number} to {$billPayment->vendor->display_name}",
                    'amount' => $billPayment->amount,
                ];
            }
        }
        
        // Auto-categorize (simple rule example: if description contains 'Starbucks', suggest Coffee Expense)
        if (str_contains(strtolower($bt->description), 'starbucks')) {
            $coffeeAccount = Account::where('company_id', $companyId)
                ->where(function ($query) {
                    $query->where('name', 'LIKE', '%Coffee%')
                          ->orWhere('code', 'LIKE', '%6150%');
                })
                ->first();
            
            if ($coffeeAccount) {
                $suggestions['create'] = [
                    'account_id' => $coffeeAccount->id,
                    'account_name' => $coffeeAccount->name,
                    'description' => $bt->description,
                    'amount' => abs($bt->amount),
                ];
            }
        }

        return $suggestions;
    }

    public function resolveMatch(Request $request)
    {
        $currentCompany = app(CurrentCompany::class)->get();

        $request->validate([
            'bank_transaction_id' => 'required|uuid',
            'target_type' => 'required|in:payment,bill_payment',
            'target_id' => 'required|uuid',
        ]);

        $bankTransaction = BankTransaction::where('company_id', $currentCompany->id)
                                          ->findOrFail($request->bank_transaction_id);
        
        if ($request->target_type === 'payment') {
            $target = Payment::where('company_id', $currentCompany->id)
                             ->findOrFail($request->target_id);
        } else {
            $target = BillPayment::where('company_id', $currentCompany->id)
                                 ->findOrFail($request->target_id);
        }

        $this->resolutionService->resolveMatch($bankTransaction, $target);

        return redirect()->back()->with('success', 'Transaction matched successfully.');
    }

    public function resolveCreate(Request $request)
    {
        $currentCompany = app(CurrentCompany::class)->get();

        $request->validate([
            'bank_transaction_id' => 'required|uuid',
            'allocations' => 'required|array|min:1',
            'allocations.*.account_id' => 'required|uuid|exists:acct.accounts,id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
            'allocations.*.description' => 'nullable|string|max:255',
        ]);

        $bankTransaction = BankTransaction::where('company_id', $currentCompany->id)
                                          ->findOrFail($request->bank_transaction_id);
        
        $this->resolutionService->resolveCreate($bankTransaction, $request->allocations);

        return redirect()->back()->with('success', 'Transaction categorized successfully.');
    }

    public function resolveTransfer(Request $request)
    {
        $currentCompany = app(CurrentCompany::class)->get();

        $request->validate([
            'bank_transaction_id' => 'required|uuid',
            'target_bank_account_id' => 'required|uuid|exists:acct.company_bank_accounts,id',
        ]);

        $bankTransaction = BankTransaction::where('company_id', $currentCompany->id)
                                          ->findOrFail($request->bank_transaction_id);
        $targetBankAccount = BankAccount::where('company_id', $currentCompany->id)
                                        ->findOrFail($request->target_bank_account_id);
        
        $this->resolutionService->resolveTransfer($bankTransaction, $targetBankAccount);

        return redirect()->back()->with('success', 'Transfer recorded successfully.');
    }

    public function resolvePark(Request $request)
    {
        $currentCompany = app(CurrentCompany::class)->get();

        $request->validate([
            'bank_transaction_id' => 'required|uuid',
            'note' => 'required|string|max:1000',
        ]);

        $bankTransaction = BankTransaction::where('company_id', $currentCompany->id)
                                          ->findOrFail($request->bank_transaction_id);
        
        $this->resolutionService->resolvePark($bankTransaction, $request->note);

        return redirect()->back()->with('success', 'Transaction parked for review.');
    }
}
