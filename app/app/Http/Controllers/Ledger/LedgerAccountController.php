<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\LedgerAccount;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LedgerAccountController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('ledger.accounts.view');

        $company = $request->user()->currentCompany;

        $accounts = LedgerAccount::query()
            ->where('company_id', $company->id)
            ->withCount(['journalLines' => fn ($q) => $q->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))])
            ->with(['parent', 'children'])
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->active !== null, fn ($q, $active) => $q->where('active', $active))
            ->orderBy('code')
            ->get();

        // Organize accounts hierarchically
        $hierarchicalAccounts = $this->organizeAccountsHierarchically($accounts);

        return Inertia::render('Ledger/Accounts/Index', [
            'accounts' => $hierarchicalAccounts,
            'filters' => $request->only(['type', 'active']),
        ]);
    }

    public function show($id)
    {
        $this->authorize('ledger.accounts.view');

        $company = Auth::user()->currentCompany;

        $account = LedgerAccount::query()
            ->where('company_id', $company->id)
            ->where('id', $id)
            ->with(['parent', 'children', 'journalLines.journalEntry'])
            ->firstOrFail();

        $balance = $this->ledgerService->getAccountBalance($account);

        return Inertia::render('Ledger/Accounts/Show', [
            'account' => $account,
            'balance' => $balance,
        ]);
    }

    private function organizeAccountsHierarchically($accounts)
    {
        $rootAccounts = $accounts->whereNull('parent_id');
        $allAccounts = $accounts->keyBy('id');

        $rootAccounts->each(function ($account) use ($allAccounts) {
            $this->buildAccountHierarchy($account, $allAccounts);
        });

        return $rootAccounts->values();
    }

    private function buildAccountHierarchy($account, $allAccounts)
    {
        $children = $allAccounts->where('parent_id', $account->id);

        if ($children->isNotEmpty()) {
            $account->children = $children;

            $children->each(function ($child) use ($allAccounts) {
                $this->buildAccountHierarchy($child, $allAccounts);
            });
        }
    }
}
