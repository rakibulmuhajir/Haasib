<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LedgerAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $company = $request->user()->currentCompany();

        $accounts = Account::where('company_id', $company->id)
            ->orderBy('account_number')
            ->get();

        $accountGroups = [];

        return Inertia::render('Ledger/Accounts/Index', [
            'accounts' => $accounts,
            'accountGroups' => $accountGroups,
            'can' => [
                'view' => $user->can('ledger.view'),
                'create' => $user->can('ledger.entries.create'),
                'update' => $user->can('ledger.entries.update'),
                'delete' => $user->can('ledger.entries.delete'),
            ],
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $user = Auth::user();
        $company = $request->session()->get('active_company');

        $account = Account::with(['journalLines' => function ($query) {
            $query->with('journalEntry')
                ->orderBy('created_at', 'desc')
                ->limit(50);
        }])
            ->where('company_id', $company->id)
            ->findOrFail($id);

        return Inertia::render('Ledger/Accounts/Show', [
            'account' => $account,
            'can' => [
                'view' => $user->can('ledger.view'),
                'update' => $user->can('ledger.entries.update'),
                'delete' => $user->can('ledger.entries.delete'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $accountGroups = AccountGroup::all();

        return Inertia::render('Ledger/Accounts/Create', [
            'accountGroups' => $accountGroups,
            'accountTypes' => [
                ['value' => 'asset', 'label' => 'Asset'],
                ['value' => 'liability', 'label' => 'Liability'],
                ['value' => 'equity', 'label' => 'Equity'],
                ['value' => 'revenue', 'label' => 'Revenue'],
                ['value' => 'expense', 'label' => 'Expense'],
            ],
            'normalBalances' => [
                ['value' => 'debit', 'label' => 'Debit'],
                ['value' => 'credit', 'label' => 'Credit'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:acct.accounts,code',
            'description' => 'nullable|string',
            'account_group_id' => 'nullable|exists:account_groups,id',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'required|in:debit,credit',
            'opening_balance' => 'nullable|numeric|min:0',
            'opening_balance_date' => 'nullable|date',
            'allow_manual_entries' => 'boolean',
        ]);

        $company = $request->session()->get('active_company');

        $account = Account::create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'account_group_id' => $validated['account_group_id'] ?? null,
            'type' => $validated['type'],
            'normal_balance' => $validated['normal_balance'],
            'opening_balance' => $validated['opening_balance'] ?? 0,
            'opening_balance_date' => $validated['opening_balance_date'] ?? null,
            'current_balance' => $validated['opening_balance'] ?? 0,
            'allow_manual_entries' => $validated['allow_manual_entries'] ?? true,
            'active' => true,
        ]);

        return redirect()->route('ledger.accounts.index')
            ->with('success', 'Account created successfully.');
    }

    public function edit(Request $request, string $id): Response
    {
        $company = $request->session()->get('active_company');

        $account = Account::where('company_id', $company->id)
            ->findOrFail($id);

        $accountGroups = AccountGroup::all();

        return Inertia::render('Ledger/Accounts/Edit', [
            'account' => $account,
            'accountGroups' => $accountGroups,
            'accountTypes' => [
                ['value' => 'asset', 'label' => 'Asset'],
                ['value' => 'liability', 'label' => 'Liability'],
                ['value' => 'equity', 'label' => 'Equity'],
                ['value' => 'revenue', 'label' => 'Revenue'],
                ['value' => 'expense', 'label' => 'Expense'],
            ],
            'normalBalances' => [
                ['value' => 'debit', 'label' => 'Debit'],
                ['value' => 'credit', 'label' => 'Credit'],
            ],
        ]);
    }

    public function update(Request $request, string $id)
    {
        $company = $request->session()->get('active_company');

        $account = Account::where('company_id', $company->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:acct.accounts,code,'.$id,
            'description' => 'nullable|string',
            'account_group_id' => 'nullable|exists:account_groups,id',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'normal_balance' => 'required|in:debit,credit',
            'allow_manual_entries' => 'boolean',
            'active' => 'boolean',
        ]);

        $account->update($validated);

        return redirect()->route('ledger.accounts.index')
            ->with('success', 'Account updated successfully.');
    }

    public function destroy(Request $request, string $id)
    {
        $company = $request->session()->get('active_company');

        $account = Account::where('company_id', $company->id)
            ->findOrFail($id);

        // Check if account has journal entries
        if ($account->journalLines()->exists()) {
            return back()->withErrors([
                'message' => 'Cannot delete account with existing journal entries.',
            ]);
        }

        $account->delete();

        return redirect()->route('ledger.accounts.index')
            ->with('success', 'Account deleted successfully.');
    }

    public function toggleStatus(Request $request, string $id)
    {
        $company = $request->session()->get('active_company');

        $account = Account::where('company_id', $company->id)
            ->findOrFail($id);

        $account->update([
            'active' => ! $account->active,
        ]);

        return back()->with('success', 'Account status updated successfully.');
    }
}
